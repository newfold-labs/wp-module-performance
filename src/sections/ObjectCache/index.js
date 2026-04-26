import { useState, useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Container, ToggleField } from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';

import { STORE_NAME } from '../../data/constants';
import getObjectCacheText from './getObjectCacheText';

const {
	objectCacheTitle,
	objectCacheDescription,
	objectCacheToggleLabel,
	objectCacheSaved,
	objectCacheErrorTitle,
	objectCacheOverwrittenNotice,
	getObjectCacheErrorDescription,
} = getObjectCacheText();

const ObjectCache = () => {
	const runtimeEnabled = NewfoldRuntime?.sdk?.cache?.objectCache?.enabled ?? false;
	const overwritten = NewfoldRuntime?.sdk?.cache?.objectCache?.overwritten === true;
	const runtimeCacheLevel = NewfoldRuntime?.sdk?.cache?.level ?? 0;

	const { storeObjectCache, cacheLevel } = useSelect(
		( select ) => ( {
			storeObjectCache: select( STORE_NAME ).getObjectCache(),
			cacheLevel: select( STORE_NAME ).getCacheLevel(),
		} ),
		[]
	);

	const isCacheDisabled = cacheLevel !== null ? cacheLevel <= 0 : runtimeCacheLevel <= 0;

	const [ enabled, setEnabled ] = useState( runtimeEnabled );
	const [ updating, setUpdating ] = useState( false );

	const retryTimerRef = useRef( null );
	const retryAttemptsRef = useRef( 0 );

	const { pushNotification, setObjectCache: dispatchSetObjectCache } = useDispatch( STORE_NAME );
	const apiUrl = NewfoldRuntime.createApiUrl(
		'/newfold-performance/v1/cache/settings'
	);

	// Seed the store from runtime on mount so preflight gating is consistent from the first render.
	useEffect( () => {
		const runtimeObjectCache = NewfoldRuntime?.sdk?.cache?.objectCache;
		if ( runtimeObjectCache && ! storeObjectCache ) {
			dispatchSetObjectCache( runtimeObjectCache );
		}
	}, [] );

	useEffect( () => {
		setEnabled( runtimeEnabled );
	}, [ runtimeEnabled ] );

	// When the store updates (cache level change, refetch), sync local toggle to server truth.
	useEffect( () => {
		if ( storeObjectCache && typeof storeObjectCache.enabled === 'boolean' ) {
			setEnabled( storeObjectCache.enabled );
		}
	}, [ storeObjectCache ] );

	useEffect( () => {
		return () => {
			if ( retryTimerRef.current ) {
				clearTimeout( retryTimerRef.current );
			}
		};
	}, [] );

	const refetchSettings = async () => {
		const settings = await apiFetch( { url: apiUrl } );
		if ( settings?.objectCache ) {
			dispatchSetObjectCache( settings.objectCache );
			if ( 'enabled' in settings.objectCache ) {
				setEnabled( settings.objectCache.enabled === true );
			}
		}
		return settings;
	};

	const scheduleCredentialsRetry = ( desiredEnabled ) => {
		if ( retryAttemptsRef.current >= 4 ) {
			setUpdating( false );
			pushNotification( 'object-cache-error', {
				title: objectCacheErrorTitle,
				description: getObjectCacheErrorDescription(
					'credentials_pending_reload',
					''
				),
				variant: 'error',
				autoDismiss: 7000,
			} );
			return;
		}

		retryAttemptsRef.current += 1;
		retryTimerRef.current = setTimeout( () => {
			requestToggle( desiredEnabled, true );
		}, 2500 );
	};

	const requestToggle = ( desiredEnabled, isRetry = false ) => {
		if ( ! isRetry ) {
			retryAttemptsRef.current = 0;
		}

		setUpdating( true );
		apiFetch( {
			url: apiUrl,
			method: 'POST',
			data: { objectCache: { enabled: desiredEnabled } },
		} )
			.then( async ( response ) => {
				if ( response?.result !== true ) {
					const code = response?.code || '';
					if ( desiredEnabled === true && code === 'credentials_pending_reload' ) {
						pushNotification( 'object-cache-pending', {
							title: objectCacheErrorTitle,
							description: getObjectCacheErrorDescription( code, response?.message || '' ),
							variant: 'warning',
							autoDismiss: 6000,
						} );
						scheduleCredentialsRetry( desiredEnabled );
						return;
					}

					setUpdating( false );
					pushNotification( 'object-cache-error', {
						title: objectCacheErrorTitle,
						description: getObjectCacheErrorDescription( code, response?.message || '' ),
						variant: 'error',
						autoDismiss: 7000,
					} );
					await refetchSettings();
					return;
				}

				setUpdating( false );
				setEnabled( desiredEnabled );
				pushNotification( 'object-cache-saved', {
					title: objectCacheSaved,
					variant: 'success',
					autoDismiss: 5000,
				} );
				await refetchSettings();
			} )
			.catch( async ( err ) => {
				const code = err?.code || '';

				if ( desiredEnabled === true && code === 'credentials_pending_reload' ) {
					pushNotification( 'object-cache-pending', {
						title: objectCacheErrorTitle,
						description: getObjectCacheErrorDescription( code, err?.message || '' ),
						variant: 'warning',
						autoDismiss: 6000,
					} );
					scheduleCredentialsRetry( desiredEnabled );
					return;
				}

				setUpdating( false );
				pushNotification( 'object-cache-error', {
					title: objectCacheErrorTitle,
					description: getObjectCacheErrorDescription( code, err?.message || '' ),
					variant: 'error',
					autoDismiss: 7000,
				} );
				await refetchSettings();
			} );
	};

	const handleChange = () => {
		if ( overwritten || isCacheDisabled ) {
			return;
		}
		const newValue = ! enabled;
		requestToggle( newValue, false );
	};

	const preflight = storeObjectCache?.preflight;
	// Match enable() before provisioning: phpredis + (if wp-config lacks creds, Hiive must be connected).
	const needsProvisioning =
		preflight && preflight.configuredInWpConfig === false;
	const provisioningPreconditionsMet =
		! needsProvisioning ||
		preflight.hiiveConnected === true ||
		preflight.hiiveConnected === undefined;
	const preflightBlocksToggle =
		! overwritten &&
		! isCacheDisabled &&
		preflight &&
		( preflight.extensionLoaded === false || ! provisioningPreconditionsMet );

	return (
		<Container.SettingsField
			title={ objectCacheTitle }
			description={ objectCacheDescription }
		>
			{ overwritten && (
				<p className="nfd-mb-4 nfd-text-sm nfd-text-orange-600">
					{ objectCacheOverwrittenNotice }
				</p>
			) }
			{ ! overwritten && ! isCacheDisabled && preflight?.preflightMessage && (
				<p className="nfd-mb-4 nfd-text-sm nfd-text-orange-600">{ preflight.preflightMessage }</p>
			) }
			<ToggleField
				id="object-cache"
				label={ objectCacheToggleLabel }
				checked={ isCacheDisabled ? false : enabled }
				onChange={ handleChange }
				disabled={ updating || overwritten || isCacheDisabled || preflightBlocksToggle }
			/>
		</Container.SettingsField>
	);
};

export default ObjectCache;

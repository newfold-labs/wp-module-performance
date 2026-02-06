import { useState, useEffect } from '@wordpress/element';
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

	const { pushNotification } = useDispatch( STORE_NAME );
	const apiUrl = NewfoldRuntime.createApiUrl(
		'/newfold-performance/v1/cache/settings'
	);

	useEffect( () => {
		setEnabled( runtimeEnabled );
	}, [ runtimeEnabled ] );

	// When cache level is set to disabled, store receives objectCache state; sync toggle.
	useEffect( () => {
		if ( storeObjectCache && typeof storeObjectCache.enabled === 'boolean' ) {
			setEnabled( storeObjectCache.enabled );
		}
	}, [ storeObjectCache ] );

	const handleChange = () => {
		if ( overwritten || isCacheDisabled ) {
			return;
		}
		setUpdating( true );
		const newValue = ! enabled;
		apiFetch( {
			url: apiUrl,
			method: 'POST',
			data: { objectCache: { enabled: newValue } },
		} )
			.then( () => {
				setUpdating( false );
				setEnabled( newValue );
				pushNotification( 'object-cache-saved', {
					title: objectCacheSaved,
					variant: 'success',
					autoDismiss: 5000,
				} );
				// Refetch so toggle stays in sync with server (e.g. after remount).
				apiFetch( { url: apiUrl } ).then( ( settings ) => {
					if ( settings?.objectCache && 'enabled' in settings.objectCache ) {
						setEnabled( settings.objectCache.enabled === true );
					}
				} );
			} )
			.catch( ( err ) => {
				setUpdating( false );
				pushNotification( 'object-cache-error', {
					title: objectCacheErrorTitle,
					description: err?.message || '',
					variant: 'error',
					autoDismiss: 5000,
				} );
			} );
	};

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
			<ToggleField
				id="object-cache"
				label={ objectCacheToggleLabel }
				checked={ isCacheDisabled ? false : enabled }
				onChange={ handleChange }
				disabled={ updating || overwritten || isCacheDisabled }
			/>
		</Container.SettingsField>
	);
};

export default ObjectCache;

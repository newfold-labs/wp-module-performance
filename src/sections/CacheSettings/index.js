import { useState, useEffect, useRef, Fragment } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Container, RadioGroup } from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';

import { STORE_NAME } from '../../data/constants';
import getCacheSettingsText from './getCacheSettingsText';

const useUpdateEffect = ( effect, deps ) => {
	const isFirstRender = useRef( true );

	useEffect( () => {
		if ( isFirstRender.current ) {
			isFirstRender.current = false;
			return;
		}
		return effect();
	}, deps );
};

const CacheSettings = () => {
	const { title, description, noticeTitle, options } = getCacheSettingsText();

	const runtimeLevel = NewfoldRuntime?.sdk?.cache?.level ?? 0;
	const [ cacheLevel, setCacheLevel ] = useState( runtimeLevel );
	const [ updating, setUpdating ] = useState( false );

	const { pushNotification, setCacheLevel: dispatchSetCacheLevel, setObjectCache: dispatchSetObjectCache } =
		useDispatch( STORE_NAME );

	const apiUrl = NewfoldRuntime.createApiUrl(
		'/newfold-performance/v1/cache/settings'
	);

	const getNoticeText = ( level ) => {
		const option = options.find( ( o ) => o.value === level );
		return option?.notice ?? '';
	};

	const handleCacheLevelChange = ( e ) => {
		const selectedLevel = parseInt( e.target.value, 10 );
		setUpdating( true );

		apiFetch( {
			url: apiUrl,
			method: 'POST',
			data: { cacheLevel: selectedLevel },
		} )
			.then( ( response ) => {
				setUpdating( false );
				setCacheLevel( selectedLevel );
				dispatchSetCacheLevel( selectedLevel );
				if ( selectedLevel <= 0 && response?.objectCache ) {
					dispatchSetObjectCache( response.objectCache );
				}
			} )
			.catch( ( err ) => {
				setUpdating( false );
				pushNotification( 'cache-level-error', {
					title: 'Failed to update cache level',
					description: err.message || 'Something went wrong.',
					variant: 'error',
				} );
			} );
	};

	useUpdateEffect( () => {
		pushNotification( 'cache-level-change-notice', {
			title: noticeTitle,
			description: getNoticeText( cacheLevel ),
			variant: 'success',
			autoDismiss: 5000,
		} );
	}, [ cacheLevel ] );

	useEffect( () => {
		dispatchSetCacheLevel( cacheLevel );
	}, [] );

	return (
		<Container.SettingsField title={ title } description={ description }>
			<RadioGroup
				className="cache-options"
				id="cache-type"
				name="cache-level"
				value=""
			>
				{ options.map(
					( { value, label, description: optionDescription } ) => (
						<Fragment key={ value }>
							<RadioGroup.Radio
								defaultChecked={ value === runtimeLevel }
								id={ `cache-level-${ value }` }
								label={ label }
								value={ value }
								name="cache-level"
								onChange={ handleCacheLevelChange }
								disabled={ updating }
							/>
							<div className="nfd-radio__description">
								{ optionDescription }
							</div>
						</Fragment>
					)
				) }
			</RadioGroup>
		</Container.SettingsField>
	);
};

export default CacheSettings;

import { useState, useEffect } from '@wordpress/element';
import { ToggleField, Container, Alert } from '@newfold/ui-component-library';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../../data/constants';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';
import getFontOptimizationText from './getFontOptimizationText';

const FontOptimization = () => {
	const [ settings, setSettings ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isError, setIsError ] = useState( false );

	const { pushNotification } = useDispatch( STORE_NAME );
	const apiUrl = NewfoldRuntime.createApiUrl( '/wp/v2/settings' );
	const {
		fontOptimizationTitle,
		fontOptimizationDescription,
		fontOptimizationLabel,
		fontOptimizationToggleDescription,
		fontOptimizationLoading,
		fontOptimizationError,
		fontOptimizationUpdatedTitle,
		fontOptimizationUpdatedDescription,
		fontOptimizationErrorTitle,
		fontOptimizationErrorDescription,
	} = getFontOptimizationText();

	const fetchSettings = async () => {
		setIsLoading( true );
		setIsError( false );

		try {
			const res = await apiFetch( { url: apiUrl } );
			const fetched = res?.nfd_fonts_optimization || {};
			setSettings( fetched );
		} catch {
			setIsError( true );
		} finally {
			setIsLoading( false );
		}
	};

	const updateSettings = async ( newSettings ) => {
		try {
			await apiFetch( {
				url: apiUrl,
				method: 'POST',
				data: { nfd_fonts_optimization: newSettings },
			} );
			setSettings( newSettings );
			pushNotification( 'font-opt-updated', {
				title: fontOptimizationUpdatedTitle,
				description: fontOptimizationUpdatedDescription,
				variant: 'success',
				autoDismiss: 5000,
			} );
		} catch {
			setIsError( true );
			pushNotification( 'font-opt-error', {
				title: fontOptimizationErrorTitle,
				description: fontOptimizationErrorDescription,
				variant: 'error',
				autoDismiss: 8000,
			} );
		}
	};

	const handleToggle = ( value ) => {
		const updated = {
			...settings,
			cloudflare: {
				...( settings.cloudflare || {} ),
				fonts: {
					value,
					user_set: true,
				},
				last_updated: Math.floor( Date.now() / 1000 ),
			},
		};
		setSettings( updated );
		updateSettings( updated );
	};

	useEffect( () => {
		fetchSettings();
	}, [] );

	if ( isLoading ) return <p>{ fontOptimizationLoading }</p>;
	if ( isError )
		return <Alert variant="error">{ fontOptimizationError }</Alert>;

	if ( ! settings ) return null;

	const isEnabled = settings?.cloudflare?.fonts?.value || false;

	const hasCapability = NewfoldRuntime.hasCapability( 'hasCloudflareFonts' );

	return (
		hasCapability && (
			<Container.SettingsField
				title={ fontOptimizationTitle }
				description={ fontOptimizationDescription }
			>
				<ToggleField
					id="cloudflare-fonts"
					label={ fontOptimizationLabel }
					description={ fontOptimizationToggleDescription }
					checked={ isEnabled }
					onChange={ () => handleToggle( ! isEnabled ) }
				/>
			</Container.SettingsField>
		)
	);
};

export default FontOptimization;

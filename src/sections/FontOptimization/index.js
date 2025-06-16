import { useState, useEffect } from '@wordpress/element';
import {
	ToggleField,
	Container,
	FeatureUpsell,
	Alert,
} from '@newfold/ui-component-library';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../../data/constants';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';

const FontOptimization = () => {
	const [ settings, setSettings ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isError, setIsError ] = useState( false );

	const { pushNotification } = useDispatch( STORE_NAME );
	const apiUrl = NewfoldRuntime.createApiUrl( '/wp/v2/settings' );

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
				title: 'Fonts optimization updated',
				description: 'Font optimization setting saved successfully.',
				variant: 'success',
				autoDismiss: 5000,
			} );
		} catch {
			setIsError( true );
			pushNotification( 'font-opt-error', {
				title: 'Update failed',
				description: 'Could not save font optimization setting.',
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
				fonts: value,
			},
		};
		setSettings( updated );
		updateSettings( updated );
	};

	useEffect( () => {
		fetchSettings();
	}, [] );

	if ( isLoading ) return <p>Loading font optimization settings…</p>;
	if ( isError )
		return <Alert variant="error">Error loading settings.</Alert>;
	if ( ! settings ) return null;

	const isEnabled = settings?.cloudflare?.fonts || false;

	const hasCapability = NewfoldRuntime.hasCapability( 'hasCloudflareFonts' );

	return (
		<Container.SettingsField
			title="Font Optimization"
			description="Improve load times by replacing Google Fonts with optimized local versions."
		>
			{ hasCapability ? (
				<ToggleField
					id="cloudflare-fonts"
					label="Optimize Fonts via Cloudflare"
					description="Replaces Google Fonts with faster, privacy-friendly versions served locally."
					checked={ isEnabled }
					onChange={ () => handleToggle( ! isEnabled ) }
				/>
			) : (
				<FeatureUpsell
					cardText="Upgrade to enable font optimization using Cloudflare."
					cardLink="https://www.bluehost.com"
				>
					<ToggleField
						id="cloudflare-fonts"
						label="Optimize Fonts via Cloudflare"
						description="Replaces Google Fonts with faster, privacy-friendly versions served locally."
						checked={ false }
						disabled
					/>
				</FeatureUpsell>
			) }
		</Container.SettingsField>
	);
};

export default FontOptimization;

// WordPress
import { useState, useEffect } from '@wordpress/element';

// Components
import { Alert, Container, ToggleField } from '@newfold/ui-component-library';

// Classes and functions
import defaultText from '../performance/defaultText';

const ImageOptimizationSettings = ( { methods } ) => {
	const [ settings, setSettings ] = useState( null ); // Local state for settings
	const [ isError, setIsError ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( true );

	const notify = methods.useNotification();
	const apiUrl = methods.NewfoldRuntime.createApiUrl( '/wp/v2/settings' );

	// Fetch settings from the REST API
	const fetchSettings = async () => {
		setIsLoading( true );
		setIsError( false );
		try {
			const fetchedSettings = await methods.apiFetch( { path: apiUrl } );
			setSettings( fetchedSettings.nfd_image_optimization || {} );
		} catch ( error ) {
			setIsError( true );
		} finally {
			setIsLoading( false );
		}
	};

	// Update settings via the REST API
	const updateSettings = async ( newSettings ) => {
		setIsError( false );
		try {
			const updatedSettings = await methods.apiFetch( {
				path: apiUrl,
				method: 'POST',
				data: { nfd_image_optimization: newSettings },
			} );
			setSettings( updatedSettings.nfd_image_optimization || {} );
			notify.push( 'image-optimization-updated', {
				title: defaultText.imageOptimizationUpdatedTitle,
				description: defaultText.imageOptimizationUpdatedDescription,
				variant: 'success',
				autoDismiss: 5000,
			} );
		} catch ( error ) {
			setIsError( true );
		}
	};

	// Handle toggle changes
	const handleToggleChange = ( field, value ) => {
		const updatedSettings = { ...settings };
		if ( field === 'enabled' ) {
			updatedSettings.enabled = value;
			if ( ! value ) {
				updatedSettings.auto_optimized_uploaded_images = {
					enabled: false,
					auto_delete_original_image: false,
				};
			} else {
				updatedSettings.auto_optimized_uploaded_images = {
					enabled: true,
					auto_delete_original_image: true,
				};
			}
		} else if ( field === 'autoOptimizeEnabled' ) {
			updatedSettings.auto_optimized_uploaded_images.enabled = value;
			if ( ! value ) {
				updatedSettings.auto_optimized_uploaded_images.auto_delete_original_image = false;
			}
		} else if ( field === 'autoDeleteOriginalImage' ) {
			updatedSettings.auto_optimized_uploaded_images.auto_delete_original_image =
				value;
		}
		setSettings( updatedSettings );
		updateSettings( updatedSettings );
	};

	// Fetch settings on component mount
	useEffect( () => {
		fetchSettings();
	}, [] );

	if ( isLoading ) {
		return <p>{ defaultText.imageOptimizationLoadingMessage }</p>;
	}

	if ( isError ) {
		return (
			<Alert variant="error">
				{ defaultText.imageOptimizationErrorMessage }
			</Alert>
		);
	}

	if ( ! settings ) {
		return (
			<Container.SettingsField
				title={ defaultText.imageOptimizationSettingsTitle }
				description={ defaultText.imageOptimizationSettingsDescription }
			>
				<p>{ defaultText.imageOptimizationNoSettings }</p>
			</Container.SettingsField>
		);
	}

	// Destructure settings with camel case for internal use
	const {
		enabled,
		auto_optimized_uploaded_images: autoOptimizedUploadedImages,
	} = settings || {};

	const {
		enabled: autoOptimizeEnabled,
		auto_delete_original_image: autoDeleteOriginalImage,
	} = autoOptimizedUploadedImages || {};

	return (
		<Container.SettingsField
			title={ defaultText.imageOptimizationSettingsTitle }
			description={ defaultText.imageOptimizationSettingsDescription }
		>
			<div className="nfd-flex nfd-flex-col nfd-gap-6">
				<ToggleField
					id="image-optimization-enabled"
					label={ defaultText.imageOptimizationEnabledLabel }
					description={
						defaultText.imageOptimizationEnabledDescription
					}
					checked={ enabled }
					onChange={ () =>
						handleToggleChange( 'enabled', ! enabled )
					}
				/>
				<div className="nfd-flex nfd-flex-col nfd-gap-6">
					<ToggleField
						id="auto-optimize-images"
						label={ defaultText.imageOptimizationAutoOptimizeLabel }
						description={
							defaultText.imageOptimizationAutoOptimizeDescription
						}
						checked={ autoOptimizeEnabled }
						onChange={ () =>
							handleToggleChange(
								'autoOptimizeEnabled',
								! autoOptimizeEnabled
							)
						}
						disabled={ ! enabled } // Grey out when optimization is disabled
					/>
					<div className="nfd-flex nfd-flex-col nfd-gap-6">
						<ToggleField
							id="auto-delete-original"
							label={
								defaultText.imageOptimizationAutoDeleteLabel
							}
							description={
								defaultText.imageOptimizationAutoDeleteDescription
							}
							checked={ autoDeleteOriginalImage }
							onChange={ () =>
								handleToggleChange(
									'autoDeleteOriginalImage',
									! autoDeleteOriginalImage
								)
							}
							disabled={ ! enabled || ! autoOptimizeEnabled } // Grey out when auto-optimize or optimization is disabled
						/>
					</div>
				</div>
			</div>
		</Container.SettingsField>
	);
};

export default ImageOptimizationSettings;

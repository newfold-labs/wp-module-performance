import { useState, useEffect } from '@wordpress/element';
import { Alert, Container, ToggleField } from '@newfold/ui-component-library';

import defaultText from '../performance/defaultText';

const ImageOptimizationSettings = ( { methods } ) => {
	const [ settings, setSettings ] = useState( null );
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

	// Update settings via REST API
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
			notify.push( 'image-optimization-update-error', {
				title: defaultText.imageOptimizationUpdateErrorTitle,
				description: defaultText.imageOptimizationGenericErrorMessage,
				variant: 'error',
				autoDismiss: 8000,
			} );
		}
	};

	// Handle Toggle Changes
	const handleToggleChange = ( field, value ) => {
		const updatedSettings = { ...settings };

		switch ( field ) {
			case 'enabled':
				updatedSettings.enabled = value;
				updatedSettings.auto_optimized_uploaded_images.enabled = value;
				updatedSettings.auto_optimized_uploaded_images.auto_delete_original_image =
					value;
				updatedSettings.bulk_optimization = value;
				updatedSettings.lazy_loading.enabled = value;
				updatedSettings.prefer_optimized_image_when_exists = value;
				break;

			case 'autoOptimizeEnabled':
				updatedSettings.auto_optimized_uploaded_images.enabled = value;
				break;

			case 'bulkOptimize':
				updatedSettings.bulk_optimization = value;
				break;

			case 'lazyLoading':
				updatedSettings.lazy_loading.enabled = value;
				break;

			case 'autoDeleteOriginalImage':
				updatedSettings.auto_optimized_uploaded_images.auto_delete_original_image =
					value;
				break;

			case 'preferOptimizedImageWhenExists':
				updatedSettings.prefer_optimized_image_when_exists = value;
				break;

			default:
				break;
		}

		// Auto-disable Auto Delete Original Image if both options are off
		if (
			field !== 'autoDeleteOriginalImage' &&
			! updatedSettings.bulk_optimization &&
			! updatedSettings.auto_optimized_uploaded_images.enabled
		) {
			updatedSettings.auto_optimized_uploaded_images.auto_delete_original_image = false;
		}

		setSettings( updatedSettings );
		updateSettings( updatedSettings );
	};

	// Fetch settings on mount
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

	const {
		enabled,
		prefer_optimized_image_when_exists:
			preferOptimizedImageWhenExists = true,
		auto_optimized_uploaded_images: autoOptimizedUploadedImages,
		lazy_loading: lazyLoading = { enabled: true },
		bulk_optimization: bulkOptimization = false,
	} = settings || {};

	const {
		enabled: autoOptimizeEnabled,
		auto_delete_original_image: autoDeleteOriginalImage,
	} = autoOptimizedUploadedImages || {};

	const mediaLibraryLink = () => {
		const basePath = window.location.pathname.split( '/wp-admin' )[ 0 ];
		return `${ window.location.origin }${ basePath }/wp-admin/upload.php?autoSelectBulk=true`;
	};

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
					disabled={ ! enabled }
				/>

				<ToggleField
					id="bulk-optimize-images"
					label={ defaultText.imageOptimizationBulkOptimizeLabel }
					description={
						<>
							<p>
								{
									defaultText.imageOptimizationBulkOptimizeDescription
								}
							</p>
							{ bulkOptimization && (
								<a
									href={ mediaLibraryLink() }
									target="_blank"
									rel="noopener noreferrer"
								>
									{
										defaultText.imageOptimizationBulkOptimizeButtonLabel
									}
								</a>
							) }
						</>
					}
					checked={ bulkOptimization }
					onChange={ () =>
						handleToggleChange( 'bulkOptimize', ! bulkOptimization )
					}
					disabled={ ! enabled }
				/>

				<ToggleField
					id="prefer-webp-when-exists"
					label={ defaultText.imageOptimizationPreferWebPLabel }
					description={
						defaultText.imageOptimizationPreferWebPDescription
					}
					checked={ preferOptimizedImageWhenExists }
					onChange={ () =>
						handleToggleChange(
							'preferOptimizedImageWhenExists',
							! preferOptimizedImageWhenExists
						)
					}
					disabled={ ! enabled }
				/>

				<ToggleField
					id="auto-delete-original"
					label={ defaultText.imageOptimizationAutoDeleteLabel }
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
					disabled={
						! enabled ||
						( ! autoOptimizeEnabled && ! bulkOptimization )
					}
				/>

				<ToggleField
					id="lazy-loading-enabled"
					label={ defaultText.imageOptimizationLazyLoadingLabel }
					description={
						defaultText.imageOptimizationLazyLoadingDescription
					}
					checked={ lazyLoading.enabled }
					onChange={ () =>
						handleToggleChange(
							'lazyLoading',
							! lazyLoading.enabled
						)
					}
					disabled={ ! enabled }
				/>
			</div>
		</Container.SettingsField>
	);
};

export default ImageOptimizationSettings;

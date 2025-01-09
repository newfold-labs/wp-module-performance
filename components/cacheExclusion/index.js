import {
	Button,
	Container,
	TextareaField,
	Checkbox,
} from '@newfold/ui-component-library';

const CacheExclusion = ( { methods, constants } ) => {
	const [ isEdited, setIsEdited ] = methods.useState( false );
	const [ isError, setIsError ] = methods.useState( false );
	const [ isSaved, setIsSaved ] = methods.useState( false );

	// Separate states for excluded URLs and error page exclusion
	const [ excludedUrls, setExcludedUrls ] = methods.useState(
		methods.NewfoldRuntime.sdk.performance.excludedUrls || ''
	);
	const [ doNotCacheErrorPages, setDoNotCacheErrorPages ] = methods.useState(
		methods.NewfoldRuntime.sdk.performance.doNotCacheErrorPages || false
	);

	const apiUrl = methods.NewfoldRuntime.createApiUrl(
		'/newfold-performance/v1/cache-exclusion/update'
	);

	// Handle changes to excluded URLs
	const handleExcludedUrlsChange = ( e ) => {
		if ( e.target.value !== excludedUrls ) {
			setIsEdited( true );
		} else {
			setIsEdited( false );
		}
		setExcludedUrls( e.target.value );
	};

	// Handle checkbox toggle for error page exclusion
	const handleDoNotCacheErrorPagesChange = () => {
		const newValue = ! doNotCacheErrorPages;
		setDoNotCacheErrorPages( newValue );
		setIsEdited( true );
	};

	// Save settings to the API
	const handleSaveButton = () => {
		methods
			.apiFetch( {
				url: apiUrl,
				method: 'POST',
				data: {
					excludedUrls,
					doNotCacheErrorPages,
				},
			} )
			.then( () => {
				setIsSaved( true );
				setIsEdited( false );
			} )
			.catch( ( error ) => {
				setIsError( error.message );
			} );
	};

	// Update notices and store on save/error
	methods.useUpdateEffect( () => {
		methods.setStore( {
			...constants.store,
			performance: {
				excludedUrls,
				doNotCacheErrorPages,
			},
		} );

		methods.makeNotice(
			'cache-exclusion-notice',
			constants.text.cacheExclusionTitle,
			! isError ? constants.text.cacheExclusionSaved : isError,
			! isError ? 'success' : 'error',
			5000
		);
	}, [ isSaved, isError ] );

	return (
		<Container.SettingsField
			title={ constants.text.cacheExclusionTitle }
			description={ constants.text.cacheExclusionDescription }
		>
			<TextareaField
				id="excluded-urls"
				name="excluded-urls"
				onChange={ handleExcludedUrlsChange }
				value={ excludedUrls }
				rows="1"
				label={ constants.text.excludedUrlsLabel }
			/>
			<Checkbox
				id="do-not-cache-error-pages"
				name="do-not-cache-error-pages"
				className="nfd-performance-cache-exclusion-checkbox"
				onChange={ handleDoNotCacheErrorPagesChange }
				value={ doNotCacheErrorPages }
				checked={ doNotCacheErrorPages }
				label={ constants.text.doNotCacheErrorPagesLabel }
			/>

			{ isEdited && (
				<Button
					variant="secondary"
					className="nfd-performance-save-cache-exclusion-button"
					disabled={ ! isEdited }
					onClick={ handleSaveButton }
				>
					{ constants.text.cacheExclusionSaveButton }
				</Button>
			) }
		</Container.SettingsField>
	);
};

export default CacheExclusion;

import {
	Toggle,
	TextField,
	SelectField,
	Container,
} from '@newfold/ui-component-library';

let ignoreKeywordsTimer = null;
const LinkPrefetch = ( { methods, constants } ) => {
	const [ settings, setSettings ] = methods.useState(
		methods.NewfoldRuntime.sdk.linkPrefetch.settings
	);
	const [ ignoreKeywords, setIgnoreKeywords ] = methods.useState(
		settings.ignoreKeywords
	);
	const [ isError, setIsError ] = methods.useState( false );
	const apiUrl = methods.NewfoldRuntime.createApiUrl(
		'/newfold-performance/v1/link-prefetch/settings'
	);

	const handleChangeOption = ( option, value ) => {
		if ( ! ( option in settings ) ) return;
		const updatedSettings = { ...settings, [ option ]: value };
		methods
			.apiFetch( {
				url: apiUrl,
				method: 'POST',
				data: { settings: updatedSettings },
			} )
			.then( () => {
				setSettings( ( prev ) => ( { ...prev, [ option ]: value } ) );
			} )
			.catch( ( error ) => {
				setIsError( error.message );
			} );
	};

	const handleChangeOptionIgnoreKeywords = ( value ) => {
		clearTimeout( ignoreKeywordsTimer );
		setIgnoreKeywords( value );
		ignoreKeywordsTimer = setTimeout( function () {
			handleChangeOption( 'ignoreKeywords', value );
		}, 700 );
	};

	methods.useUpdateEffect( () => {
		methods.setStore( {
			...constants.store,
			linkPrefetch: settings,
		} );

		methods.makeNotice(
			'link-prefetch-change-notice',
			constants.text.linkPrefetchTitle,
			isError || constants.text.linkPrefetchNoticeTitle,
			isError ? 'error' : 'success',
			5000
		);
	}, [ settings, isError ] );

	return (
		<>
			<Container.SettingsField
				title={ constants.text.linkPrefetchTitle }
				description={ constants.text.linkPrefetchDescription }
			>
				{ /* Desktop Settings */ }
				<div className="nfd-toggle-field nfd-mb-6">
					<div>
						<label
							className="nfd-label"
							htmlFor="link-prefetch-active-desktop"
						>
							{
								constants.text
									.linkPrefetchActivateOnDesktopLabel
							}
						</label>
						<div className="nfd-select-field__description">
							{
								constants.text
									.linkPrefetchActivateOnDesktopDescription
							}
						</div>
					</div>
					<Toggle
						id="link-prefetch-active-desktop"
						screenReaderLabel={
							constants.text.linkPrefetchActivateOnDesktopLabel
						}
						checked={ settings.activeOnDesktop }
						onChange={ () =>
							handleChangeOption(
								'activeOnDesktop',
								! settings.activeOnDesktop
							)
						}
					/>
				</div>
				{ settings.activeOnDesktop && (
					<SelectField
						id="link-prefetch-behavior"
						label={ constants.text.linkPrefetchBehaviorLabel }
						value={ settings.behavior }
						selectedLabel={
							'mouseDown' === settings.behavior
								? constants.text
										.linkPrefetchBehaviorMouseDownLabel
								: constants.text
										.linkPrefetchBehaviorMouseHoverLabel
						}
						onChange={ ( v ) =>
							handleChangeOption( 'behavior', v )
						}
						description={
							'mouseDown' === settings.behavior
								? constants.text
										.linkPrefetchBehaviorMouseDownDescription
								: constants.text
										.linkPrefetchBehaviorMouseHoverDescription
						}
						className="nfd-mb-6"
					>
						<SelectField.Option
							label={
								constants.text
									.linkPrefetchBehaviorMouseHoverLabel
							}
							value="mouseHover"
						/>
						<SelectField.Option
							label={
								constants.text
									.linkPrefetchBehaviorMouseDownLabel
							}
							value="mouseDown"
						/>
					</SelectField>
				) }
				{ /* Mobile Settings */ }
				<div className="nfd-toggle-field nfd-mb-6">
					<div>
						<label
							className="nfd-label"
							htmlFor="link-prefetch-active-mobile"
						>
							{ constants.text.linkPrefetchActivateOnMobileLabel }
						</label>
						<div className="nfd-select-field__description">
							{
								constants.text
									.linkPrefetchActivateOnMobileDescription
							}
						</div>
					</div>
					<Toggle
						id="link-prefetch-active-mobile"
						screenReaderLabel={
							constants.text.linkPrefetchActivateOnMobileLabel
						}
						checked={ settings.activeOnMobile }
						onChange={ () =>
							handleChangeOption(
								'activeOnMobile',
								! settings.activeOnMobile
							)
						}
					/>
				</div>
				{ settings.activeOnMobile && (
					<SelectField
						id="link-prefetch-behavior-mobile"
						label={ constants.text.linkPrefetchBehaviorLabel }
						value={ settings.mobileBehavior }
						selectedLabel={
							'touchstart' === settings.mobileBehavior
								? constants.text
										.linkPrefetchBehaviorMobileTouchstartLabel
								: constants.text
										.linkPrefetchBehaviorMobileViewportLabel
						}
						onChange={ ( v ) =>
							handleChangeOption( 'mobileBehavior', v )
						}
						description={
							'touchstart' === settings.mobileBehavior
								? constants.text
										.linkPrefetchBehaviorMobileTouchstartDescription
								: constants.text
										.linkPrefetchBehaviorMobileViewportDescription
						}
						className="nfd-mb-6"
					>
						<SelectField.Option
							label={
								constants.text
									.linkPrefetchBehaviorMobileTouchstartLabel
							}
							value="touchstart"
						/>
						<SelectField.Option
							label={
								constants.text
									.linkPrefetchBehaviorMobileViewportLabel
							}
							value="viewport"
						/>
					</SelectField>
				) }
				{ /* Ignore Keywords */ }
				{ ( settings.activeOnMobile || settings.activeOnDesktop ) && (
					<TextField
						id="link-prefetch-ignore-keywords"
						label={ constants.text.linkPrefetchIgnoreKeywordsLabel }
						description={
							constants.text.linkPrefetchIgnoreKeywordsDescription
						}
						onChange={ ( e ) =>
							handleChangeOptionIgnoreKeywords( e.target.value )
						}
						value={ ignoreKeywords }
					/>
				) }
			</Container.SettingsField>
		</>
	);
};

export default LinkPrefetch;

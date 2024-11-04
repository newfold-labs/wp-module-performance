import { Toggle, TextField, TextareaField, SelectField, Container } from "@newfold/ui-component-library";

const LinkPrefetch = ({methods, constants}) => {
	const [settings, setSettings] = methods.useState(constants.store?.linkPrefetch ? constants.store?.linkPrefetch : null)

	if ( !settings ) {
		return;
	}

	console.log(methods.NewfoldRuntime)

	const handleChangeOption = ( option, value ) => {
		if ( option in settings ) {
			const updatedSettings = settings;
			updatedSettings[option] = value;
			methods.newfoldSettingsApiFetch(
				{ linkPrefetch: updatedSettings }, 
				methods.setError, (response) => {
					setSettings( (prev)=> {
						return {
							...prev,
							[option]: value
						}
					});
				}
			);
		}
	}

	methods.useUpdateEffect(() => {
        methods.setStore({
            ...constants.store,
            linkPrefetch: settings,
        });

        methods.makeNotice(
            "link-prefetch-change-notice", 
            constants.text.linkPrefetchTitle,
            constants.text.linkPrefetchNoticeTitle,
            "success",
            5000
        );
    }, [settings]);

	return(
		<>
		<Container.SettingsField
			title={constants.text.linkPrefetchTitle}
			description={constants.text.linkPrefetchDescription}
		>
			<div className="nfd-toggle-field" style={{display: 'flex', flexDirection:'row'}}>
				<div >
					<label className="nfd-label" htmlFor="link-prefetch-active-desktop">{constants.text.linkPrefetchActivateOnDekstopLabel}</label>
					<div className="nfd-select-field__description">
						{constants.text.linkPrefetchActivateOnDekstopDescription}
					</div>
				</div>		
				<Toggle 
					id='link-prefetch-active-desktop'
					screenReaderLabel={constants.text.linkPrefetchActivateOnDekstopLabel}
					checked={settings.activeOnDesktop}
					onChange={() => handleChangeOption( 'activeOnDesktop', !settings.activeOnDesktop) }
				/>
			</div>
			{ settings.activeOnDesktop && (
				<SelectField
					id="link-prefetch-behavior"
					label={constants.text.linkPrefetchBehaviorLabel}
					value={settings.behavior}
					selectedLabel={'mouseDown' === settings.behavior ? constants.text.linkPrefetchBehaviorMouseDownLabel : constants.text.linkPrefetchBehaviorMouseHoverLabel}
					onChange={(v) => handleChangeOption( 'behavior', v) }
					description={ 'mouseDown' === settings.behavior ? constants.text.linkPrefetchBehaviorMouseDownDescription : constants.text.linkPrefetchBehaviorMouseHoverDescription}
				>
					<SelectField.Option
						label={constants.text.linkPrefetchBehaviorMouseHoverLabel}
						value="mouseHover"
					/>
					<SelectField.Option
						label={constants.text.linkPrefetchBehaviorMouseDownLabel}
						value="mouseDown"
					/>
				</SelectField>
				)
			}
			<div className="nfd-toggle-field" style={{display: 'flex', flexDirection:'row'}}>
				<div >
					<label className="nfd-label" htmlFor="link-prefetch-active-mobile">{constants.text.linkPrefetchActivateOnMobileLabel}</label>
					<div className="nfd-select-field__description">
						{constants.text.linkPrefetchActivateOnMobileDescription}
					</div>
				</div>		
				<Toggle 
					id='link-prefetch-active-mobile'
					screenReaderLabel={constants.text.linkPrefetchActivateOnMobileLabel}
					checked={settings.activeOnMobile}
					onChange={() => handleChangeOption('activeOnMobile', !settings.activeOnMobile) }
				/>
			</div>
{/* 			<ToggleField
				id="link-prefetch-active-mobile"
				label={constants.text.linkPrefetchActivateOnMobileLabel}
				checked={settings.activeOnMobile}
				onChange={() => handleChangeOption('activeOnMobile', !settings.activeOnMobile) }
				description={constants.text.linkPrefetchActivateOnMobileDescription}
			/> */}
			{ settings.activeOnMobile && (
				<SelectField
					id="link-prefetch-behavior-mobile"
					label={constants.text.linkPrefetchBehaviorLabel}
					value={settings.mobileBehavior}
					selectedLabel={'touchstart' === settings.mobileBehavior ? constants.text.linkPrefetchBehaviorMobileTouchstartLabel : constants.text.linkPrefetchBehaviorMobileViewportLabel}
					onChange={(v) => handleChangeOption( 'mobileBehavior', v) }
					description={'touchstart' === settings.mobileBehavior ? constants.text.linkPrefetchBehaviorMobileTouchstartDescription : constants.text.linkPrefetchBehaviorMobileViewportDescription}
				>
					<SelectField.Option
						label={constants.text.linkPrefetchBehaviorMobileTouchstartLabel}
						value="touchstart"
					/>
					<SelectField.Option
						label={constants.text.linkPrefetchBehaviorMobileViewportLabel}
						value="viewport"
					/>
				</SelectField>
				)
			}
			{ ( settings.activeOnMobile || settings.activeOnDesktop ) && 
				<TextField
					id="link-prefetch-ignore-keywords"
					label={constants.text.linkPrefetchIgnoreKeywordsLabel}
					description={constants.text.linkPrefetchIgnoreKeywordsDescription}
					onChange={(e) => handleChangeOption('ignoreKeywords', e.target.value)}
					value={settings.ignoreKeywords}
				/>
			}

		</Container.SettingsField>
		</>
	)
}

export default LinkPrefetch;
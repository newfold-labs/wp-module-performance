import { ToggleField, TextField, TextareaField, SelectField, Container } from "@newfold/ui-component-library";
const LinkPrefetch = ({methods, constants}) => {
	const [settings, setSettings] = methods.useState(constants.store.linkPrefetch)

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
		<Container.SettingsField
			title={constants.text.linkPrefetchTitle}
			description={constants.text.linkPrefetchDescription}
		>
			<ToggleField
				id="link-prefetch-active-desktop"
				label={constants.text.linkPrefetchActivateOnDekstopLabel}
				checked={settings.activeOnDesktop}
				onChange={(v) => handleChangeOption( 'activeOnDesktop', !settings.activeOnDesktop) }
				description={constants.text.linkPrefetchActivateOnDekstopDescription}
			/>
			{ settings.activeOnDesktop && (
				<>
					<SelectField
						id="link-prefetch-behavior"
						label={constants.text.linkPrefetchBehaviorLabel}
						value={settings.behavior}
						selectedLabel={'mouseDown' === settings.behavior ? constants.text.linkPrefetchBehaviorMouseDownLabel : constants.text.linkPrefetchBehaviorMouseHoverLabel}
						onChange={(v) => handleChangeOption( 'behavior', v) }
						description={constants.text.linkPrefetchBehaviorDescription}
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
					{
						'mouseDown' === settings.behavior && (
							<ToggleField
								id="link-prefetch-instant-click"
								label={constants.text.linkPrefetchInstantClickLabel}
								checked={settings.instantClick}
								onChange={(v) => handleChangeOption( 'instantClick', !settings.instantClick) }
								description={constants.text.linkPrefetchInstantClickDescription}
							/>
						)
					}
					{
						'mouseHover' === settings.behavior && (
							<TextField
								id="link-prefetch-hover-delay"
								label={constants.text.linkPrefetchHoverDelayLabel}
								onChange={(e) => handleChangeOption( 'hoverDelay', '' === e.target.value ? 60 : e.target.value )}
								type="number"
								value={settings.hoverDelay}
								description={constants.text.linkPrefetchHoverDelayDescription}
							/>
						)
					}
				</>
				)
			}
			<ToggleField
				id="link-prefetch-active-mobile"
				label={constants.text.linkPrefetchActivateOnMobileLabel}
				checked={settings.activeOnMobile}
				onChange={(v) => handleChangeOption( 'activeOnMobile', !settings.activeOnMobile) }
				description={constants.text.linkPrefetchActivateOnMobileDescription}
			/>
			{ settings.activeOnMobile && (
				<SelectField
					id="link-prefetch-behavior-mobile"
					label={constants.text.linkPrefetchBehaviorLabel}
					value={settings.mobileBehavior}
					selectedLabel={'touchstart' === settings.mobileBehavior ? constants.text.linkPrefetchBehaviorMobileTouchstartLabel : constants.text.linkPrefetchBehaviorMobileViewportLabel}
					onChange={(v) => handleChangeOption( 'mobileBehavior', v) }
					description={constants.text.linkPrefetchBehaviorDescription}
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
				<TextareaField
					id="link-prefetch-ignore-keywords"
					label={constants.text.linkPrefetchIgnoreKeywordsLabel}
					description={constants.text.linkPrefetchIgnoreKeywordsDescription}
					onChange={(e) => handleChangeOption('ignoreKeywords', e.target.value)}
					defaultValue={settings.ignoreKeywords}
				/>
			}

		</Container.SettingsField>
	)
}

export default LinkPrefetch;
// WordPress
import apiFetch from '@wordpress/api-fetch';
import { useRef, useState } from '@wordpress/element';

// Newfold
import {
	Button,
	ToggleField,
	Textarea,
	FeatureUpsell,
	Spinner,
	ProgressBar,
} from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';

// Component
import InstallActivatePluginButton from './InstallActivatePluginButton';

// Third-parts
import parse from 'html-react-parser';

const JetpackBoost = ( { methods, constants } ) => {
	const currentUrl = window.location.href;
	const siteUrl = currentUrl.split( '/wp-admin/' )[ 0 ];

	const sdk = NewfoldRuntime.sdk?.jetpackboost || {};
	const isPremiumActive = sdk.jetpack_premium_is_active || false;

	const [ isModuleEnabled, setIsModuleEnabled ] = useState(
		sdk.is_active || false
	);

	const [ isLoading, setIsLoading ] = useState( false );

	const [ cssIsGenerating, setCssIsGenerating ] = useState( false );

	const [ progressBarValue, setProgeassBarValue ] = useState( null );

	const [ settings, setSettings ] = useState( {
		'critical-css': sdk.critical_css || false,
		'critical-css-premium': sdk.jcritical_css || false,
		'render-blocking-js': sdk.blocking_js || false,
		'minify-js': sdk.minify_js || false,
		'minify-js-excludes': sdk.minify_js_excludes || '',
		'minify-css': sdk.minify_css || false,
		'minify-css-excludes': sdk.minify_css_excludes || '',
	} );

	const debounceTimeout = useRef( null ); // Mantiene il timeout tra i render

	const handleOnChangeOption = ( value, id ) => {
		setIsLoading( true );
		if ( typeof value === 'object' ) {
			value = value.target.value;
		}
		const updatedSettings = { ...settings };
		updatedSettings[ id ] = value;
		setSettings( updatedSettings );

		// Clear the previous timeout if user types again.
		if ( debounceTimeout.current ) {
			clearTimeout( debounceTimeout.current );
		}

		// Set a new timeout of 2 seconds.
		debounceTimeout.current = setTimeout( () => {
			apiFetch( {
				path: 'newfold-performance/v1/jetpack/settings',
				method: 'POST',
				data: {
					field: {
						id,
						value,
					},
				},
			} )
				.then( () => {
					methods.makeNotice(
						'cache-level-change-notice',
						constants.text.optionSet,
						'',
						'success',
						5000
					);
				} )
				.catch( () => {
					methods.makeNotice(
						'cache-level-change-notice',
						constants.text.optionNotSet,
						'',
						'error',
						5000
					);
					setIsLoading( false );
				} );
			setIsLoading( false );
		}, 500 );

		if ( id === 'critical-css' && value === true ) {
			handleRegenerateClick();
		}
	};

	const handleRegenerateClick = async () => {
		setCssIsGenerating( true );

		try {
			if ( ! settings[ 'critical-css' ] ) {
				//handleOnChangeOption( true, 'critical-css' );
			}

			await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );
			await apiFetch( {
				path: 'newfold-performance/v1/jetpack/regenerate_critical_css',
				method: 'POST',
			} );

			const adminUrl = siteUrl + '/wp-admin/admin.php?page=jetpack-boost';

			const iframe = document.createElement( 'iframe' );
			iframe.src = adminUrl;

			document.body.appendChild( iframe );

			iframe.onload = function () {
				try {
					const iframeDocument =
						iframe.contentDocument || iframe.contentWindow.document;

					const progressBar = iframeDocument.querySelector(
						'div[role="progressbar"]'
					);

					if ( ! progressBar ) {
						return;
					}
					setCssIsGenerating( false );

					function checkProgress() {
						const progressValue = parseInt(
							progressBar.getAttribute( 'aria-valuenow' ),
							10
						);
						setProgeassBarValue( progressValue );

						if ( progressValue === 100 ) {
							setTimeout( () => {
								setProgeassBarValue( null );
								iframe.remove();
								methods.makeNotice(
									'cache-level-change-notice',
									constants.text
										.jetpackBoostCriticalCssGenerattionSuccess,
									'',
									'success',
									5000
								);
							}, 1000 );
							if ( typeof observer !== 'undefined' ) {
								observer.disconnect();
							}
						}
					}

					if ( typeof MutationObserver !== 'undefined' ) {
						const observer = new MutationObserver( checkProgress );
						observer.observe( progressBar, {
							attributes: true,
							attributeFilter: [ 'aria-valuenow' ],
						} );

						checkProgress();
					} else {
						methods.makeNotice(
							'cache-level-change-notice',
							constants.text
								.jetpackBoostCriticalCssGenerationIssue,
							'',
							'error',
							5000
						);
						console.log(
							'Error showing progress bar: MutationObserver not supported.'
						);
						checkProgress = () => null;
					}
				} catch ( error ) {
					console.error(
						'Error occurred while accessing the iFrame',
						error
					);
				}
			};
		} catch ( error ) {
			console.log( error );
		}
	};

	const cssPremiumField = (
		<ToggleField
			id="critical-css-premium"
			label={
				isPremiumActive
					? constants.text.jetpackBoostCriticalCssPremiumTitle
					: constants.text.jetpackBoostCriticalCssUpgradeTitle
			}
			description={ parse(
				constants.text.jetpackBoostCriticalCssPremiumDescription
			) }
			checked={ !! settings[ 'critical-css-premium' ] }
			onChange={ ( value ) =>
				handleOnChangeOption( value, 'critical-css-premium' )
			}
		/>
	);

	return (
		<div
			className={ `nfd-performance-jetpack-boost-container-options ${
				isLoading ? 'is-loading' : ''
			} ${
				! isModuleEnabled
					? 'module-disabled nfd-inset-0 nfd-ring-1 nfd-ring-black nfd-ring-opacity-5 nfd-shadow-lg nfd-rounded-md'
					: ''
			}` }
		>
			{ ! isModuleEnabled && (
				<>
					<InstallActivatePluginButton
						methods={ methods }
						constants={ constants }
						setModuleStatus={ setIsModuleEnabled }
					/>
				</>
			) }

			{ isLoading && (
				<Spinner
					size="8"
					variant="primary"
					className="nfd-performance-jetpack-boost-loader"
				/>
			) }

			{ ! isPremiumActive && (
				<div className="section" style={ { marginBottom: '20px' } }>
					<ToggleField
						id="critical-css"
						label={ constants.text.jetpackBoostCriticalCssTitle }
						description={ parse(
							constants.text.jetpackBoostCriticalCssDescription
						) }
						checked={ !! settings[ 'critical-css' ] }
						onChange={ ( value ) => {
							handleOnChangeOption( value, 'critical-css' );
						} }
					/>
					<div>
						{ settings[ 'critical-css' ] &&
							progressBarValue === null && (
								<Button
									size="small"
									variant="secondary"
									onClick={ handleRegenerateClick }
									disabled={
										progressBarValue > 0 ? true : false
									}
									type="button"
									aria-disabled="false"
									className="nfd-performance-jetpack-boost-regenerate-critical-css"
									isLoading={ cssIsGenerating }
								>
									{
										constants.text
											.jetpackBoostCriticalCssButton
									}
								</Button>
							) }

						{ progressBarValue > 0 && (
							<>
								<ProgressBar
									max={ 100 }
									min={ 0 }
									progress={ progressBarValue }
								/>
								<p>
									{
										constants.text
											.jetpackBoostCriticalCssGenerationText
									}
								</p>
							</>
						) }
					</div>
				</div>
			) }

			<div
				className="section automatic-critical-css"
				style={ { marginBottom: '20px' } }
			>
				{ isModuleEnabled && ! isPremiumActive ? (
					<FeatureUpsell
						cardText={ constants.text.upgradeModule }
						cardLink={ `${ siteUrl }/wp-admin/admin.php?page=jetpack-boost#upgrade` }
					>
						{ cssPremiumField }
					</FeatureUpsell>
				) : (
					cssPremiumField
				) }
			</div>

			<div className="section" style={ { marginBottom: '20px' } }>
				<ToggleField
					id="render-blocking-js"
					label={ constants.text.jetpackBoostRenderBlockingTitle }
					description={ parse(
						constants.text.jetpackBoostRenderBlockingDescription
					) }
					checked={ !! settings[ 'render-blocking-js' ] }
					onChange={ ( value ) => {
						handleOnChangeOption( value, 'render-blocking-js' );
					} }
				/>
			</div>

			<div className="section" style={ { marginBottom: '20px' } }>
				<ToggleField
					id="minify-js"
					label={ constants.text.jetpackBoostMinifyJsTitle }
					description={ parse(
						constants.text.jetpackBoostMinifyJsDescription
					) }
					checked={ !! settings[ 'minify-js' ] }
					onChange={ ( value ) => {
						handleOnChangeOption( value, 'minify-js' );
					} }
				/>

				{ settings[ 'minify-js' ] && (
					<div className="subsection" style={ { marginTop: '15px' } }>
						<p className="field-label">
							{ constants.text.jetpackBoostExcludeJsTitle }
						</p>
						<Textarea
							id="minify-js-excludes"
							description=""
							value={ settings[ 'minify-js-excludes' ] }
							onChange={ ( value ) => {
								handleOnChangeOption(
									value,
									'minify-js-excludes'
								);
							} }
						/>
					</div>
				) }
			</div>

			<div className="section" style={ { marginBottom: '20px' } }>
				<ToggleField
					id="minify-css"
					label={ constants.text.jetpackBoostMinifyCssTitle }
					description={ parse(
						constants.text.jetpackBoostMinifyCssDescription
					) }
					checked={ !! settings[ 'minify-css' ] }
					onChange={ ( value ) => {
						handleOnChangeOption( value, 'minify-css' );
					} }
				/>
				{ settings[ 'minify-css' ] && (
					<div className="subsection" style={ { marginTop: '15px' } }>
						<p className="field-label">
							{ constants.text.jetpackBoostExcludeCssTitle }
						</p>
						<Textarea
							id="minify-css-excludes"
							description=""
							value={ settings[ 'minify-css-excludes' ] }
							onChange={ ( value ) => {
								handleOnChangeOption(
									value,
									'minify-css-excludes'
								);
							} }
						/>
					</div>
				) }
			</div>
		</div>
	);
};

export default JetpackBoost;

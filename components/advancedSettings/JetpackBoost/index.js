// WordPress
import apiFetch from '@wordpress/api-fetch';
import { useRef, useState } from '@wordpress/element';

// Newfold
import {
	Button,
	Modal,
	ToggleField,
	Textarea,
	FeatureUpsell,
	Spinner,
} from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';

// Component
import InstallActivatePluginButton from './InstallActivatePluginButton';

// Third-parts
import parse from 'html-react-parser';

const JetpackBoost = ( { methods, constants } ) => {
	const currentUrl = window.location.href;
	const siteUrl = currentUrl.split( '/wp-admin/' )[ 0 ];

	const performance = NewfoldRuntime.sdk?.performance || {};
	const isPremiumActive = performance.jetpack_premium_is_active || false;

	const [ isModuleEnabled, setIsModuleEnabled ] = useState(
		performance.jetpack_boost_is_active || false
	);

	const [ isLoading, setIsLoading ] = useState( false );

	const [ isLoadingModal, setIsLoadingModal ] = useState( false );
	const [ messageModal, setMessageModal ] = useState( null );
	const [ isOpenModal, setIsOpenModal ] = useState( false );

	const [ settings, setSettings ] = useState( {
		'critical-css': performance.jetpack_boost_critical_css || false,
		'critical-css-premium': performance.jetpack_boost_critical_css || false,
		'render-blocking-js': performance.jetpack_boost_blocking_js || false,
		'minify-js': performance.jetpack_boost_minify_js || false,
		'minify-js-excludes':
			performance.jetpack_boost_minify_js_excludes || '',
		'minify-css': performance.jetpack_boost_minify_css || false,
		'minify-css-excludes':
			performance.jetpack_boost_minify_css_excludes || '',
	} );

	const debounceTimeout = useRef( null ); // Mantiene il timeout tra i render

	const handleOnChangeOption = ( value, id ) => {
		! isOpenModal && setIsLoading( true );
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
	};

	const handleRegenerateClick = async () => {
		setIsLoadingModal( true );
		setMessageModal( null );

		try {
			if ( !settings['critical-css']) {
				handleOnChangeOption(true, 'critical-css');
			}
		
			// Aspetta 3 secondi prima di eseguire apiFetch
			await new Promise(resolve => setTimeout(resolve, 1000));
		
			await apiFetch({
				path: 'newfold-performance/v1/jetpack/regenerate_critical_css',
				method: 'POST',
			});
		
			// Apri la nuova scheda dopo la chiamata API
			window.open(siteUrl + '/wp-admin/admin.php?page=jetpack-boost', '_blank');
		} catch (error) {
			setMessageModal(
				constants.text.jetpackBoostCriticalCssGenerationIssue + ' ' + error.message
			);
		}
		setIsOpenModal( false );
		setIsLoadingModal( false );
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
							if ( value === true ) {
								setIsOpenModal( true )
							} else {
								handleOnChangeOption( value, 'critical-css' );
							}
						} }
					/>
					<div>
						{ settings[ 'critical-css' ] && (
							<button
								onClick={ () => setIsOpenModal( true ) }
								disabled={ isLoadingModal }
								type="button"
								aria-disabled="false"
								className="nfd-performance-jetpack-boost-regenerate-critical-css"
							>
								<svg
									className="gridicon gridicons-refresh"
									height="15"
									width="15"
									xmlns="http://www.w3.org/2000/svg"
									viewBox="0 0 24 24"
								>
									<g>
										<path d="M17.91 14c-.478 2.833-2.943 5-5.91 5-3.308 0-6-2.692-6-6s2.692-6 6-6h2.172l-2.086 2.086L13.5 10.5 18 6l-4.5-4.5-1.414 1.414L14.172 5H12c-4.418 0-8 3.582-8 8s3.582 8 8 8c4.08 0 7.438-3.055 7.93-7h-2.02z"></path>
									</g>
								</svg>
								{ constants.text.jetpackBoostCriticalCssButton }
							</button>
						) }
						<Modal
							isOpen={ isOpenModal }
							onClose={ () => setIsOpenModal( false ) }
							className="nfd-performance-jetpack-boost-critical-css-modal"
						>
							<Modal.Panel>
								{ parse(
									constants.text
										.jetpackBoostCriticalCssModalDescription
								) }
								<div className="nfd-performance-jetpack-boost-action-buttons-container">
									<Button onClick={ handleRegenerateClick }>
										{
											constants.text
												.jetpackBoostCriticalCssModalConfirm
										}
									</Button>
									<Button
										variant="secondary"
										onClick={ () =>
											setIsOpenModal( false )
										}
									>
										{
											constants.text
												.jetpackBoostCriticalCssModalReject
										}
									</Button>
								</div>
							</Modal.Panel>
						</Modal>
						{ messageModal && <p>{ messageModal }</p> }
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

// Wordpress
import { useState } from '@wordpress/element';
import { sprintf, __ } from '@wordpress/i18n';

// Newfold
import { FeatureUpsell } from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold-labs/wp-module-runtime';

// Component
import SingleOption from './SingleOption';
import InstallActivatePluginButton from './InstallActivatePluginButton';

const JetpackBoost = ( { methods, constants } ) => {
	const currentUrl = window.location.href;
	const siteUrl = currentUrl.split( '/wp-admin/' )[ 0 ];

	const fields = [
		{
			id: 'critical-css',
			label: constants.text.jetpackBoostCriticalCssTitle,
			description: constants.text.jetpackBoostCriticalCssDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_critical_css,
			type: 'toggle',
			externalText: sprintf(
				constants.text.jetpackBoostDiscoverMore,
				'<a href="' +
					siteUrl +
					'/wp-admin/admin.php?page=jetpack-boost">',
				'</a>'
			),
			hideOnPremium: true,
			showOnModuleDisabled: true,
		},
		{
			id: 'critical-css-premium',
			label: NewfoldRuntime.sdk.performance
				.jetpack_boost_premium_is_active
				? constants.text.jetpackBoostCriticalCssPremiumTitle
				: constants.text.jetpackBoostCriticalCssUpgradeTitle,
			description:
				constants.text.jetpackBoostCriticalCssPremiumDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_critical_css,
			type: 'toggle',
			premiumUrl:
				siteUrl + '/wp-admin/admin.php?page=jetpack-boost#upgrade',
			showOnModuleDisabled: false,
		},
		{
			id: 'render-blocking-js',
			label: constants.text.jetpackBoostRenderBlockingTitle,
			description: constants.text.jetpackBoostRenderBlockingDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_blocking_js,
			type: 'toggle',
			showOnModuleDisabled: true,
		},
		{
			id: 'minify-js',
			label: constants.text.jetpackBoostMinifyJsTitle,
			description: constants.text.jetpackBoostMinifyJsDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_minify_js,
			type: 'toggle',
			showOnModuleDisabled: true,
			children: [
				{
					id: 'minify-js-excludes',
					label: constants.text.jetpackBoostExcludeJsTitle,
					description: '',
					value: NewfoldRuntime.sdk.performance
						.jetpack_boost_minify_js_excludes,
					type: 'textarea',
				},
			],
		},
		{
			id: 'minify-css',
			label: constants.text.jetpackBoostMinifyCssTitle,
			description: constants.text.jetpackBoostMinifyCssDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_minify_css,
			type: 'toggle',
			showOnModuleDisabled: true,
			children: [
				{
					id: 'minify-css-excludes',
					label: constants.text.jetpackBoostExcludeCssTitle,
					description: '',
					value: NewfoldRuntime.sdk.performance
						.jetpack_boost_minify_css_excludes,
					type: 'textarea',
				},
			],
		},
	];

	const [ moduleStatus, setModuleStatus ] = useState(
		NewfoldRuntime.sdk.performance.jetpack_boost_is_active
	);

	return (
		<>
			{ ! moduleStatus ? (
				<div className="nfd-performance-jetpack-boost-upsell">
					<InstallActivatePluginButton
						methods={ methods }
						constants={ constants }
						setModuleStatus={ setModuleStatus }
					/>
					<FeatureUpsell
						cardText={ __( 'Installing', 'wp-module-performance' ) }
					>
						{ fields.map( ( field ) => {
							if ( field.showOnModuleDisabled ) {
								return (
									<SingleOption
										key={ field.id }
										params={ field }
										methods={ methods }
										constants={ constants }
									/>
								);
							}
							return null;
						} ) }
					</FeatureUpsell>
				</div>
			) : (
				fields.map( ( field ) => {
					if (
						field.hideOnPremium &&
						NewfoldRuntime.sdk.performance
							.jetpack_boost_premium_is_active
					) {
						return null;
					}

					return (
						<div
							className={ `nfd-performance-jetpack-boost-single-option ${
								! NewfoldRuntime.sdk.performance
									.jetpack_boost_premium_is_active
									? 'margin20'
									: ''
							}` }
							key={ field.id }
						>
							<SingleOption
								params={ field }
								methods={ methods }
								constants={ constants }
							/>
							{ field.children?.map( ( subfield ) => (
								<div key={ subfield.id }>
									<SingleOption
										params={ subfield }
										isChild
										methods={ methods }
										constants={ constants }
									/>
								</div>
							) ) }
						</div>
					);
				} )
			) }
		</>
	);
};

export default JetpackBoost;

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
	const fields = [
		{
			id: 'critical-css',
			label: constants.text.jetpackBoostCriticalCssTitle,
			description: constants.text.jetpackBoostCriticalCssDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_critical_css,
			type: 'toggle',
			externalText: sprintf(
				// translators: %1$s is the opening <a> tag, %2$s is the closing </a> tag.
				__( 'Discover more %1$shere%2$s', 'wp-module-performance' ),
				'<a href="' +
					window.location.origin +
					'/wp-admin/admin.php?page=jetpack-boost">',
				'</a>'
			),
			hideOnPremium: true,
		},
		{
			id: 'critical-css-premium',
			label: constants.text.jetpackBoostCriticalCssPremiumTitle,
			description:
				constants.text.jetpackBoostCriticalCssPremiumDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_critical_css,
			type: 'toggle',
			premiumUrl:
				window.location.origin +
				'/wp-admin/admin.php?page=jetpack-boost',
		},
		{
			id: 'render-blocking-js',
			label: constants.text.jetpackBoostRenderBlockingTitle,
			description: constants.text.jetpackBoostRenderBlockingDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_blocking_js,
			type: 'toggle',
		},
		{
			id: 'minify-js',
			label: constants.text.jetpackBoostMinifyJsTitle,
			description: constants.text.jetpackBoostMinifyJsDescription,
			value: NewfoldRuntime.sdk.performance.jetpack_boost_minify_js,
			type: 'toggle',
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
					<FeatureUpsell>
						{ fields.map( ( field ) => (
							<SingleOption
								key={ field.id }
								params={ field }
								methods={ methods }
								constants={ constants }
							/>
						) ) }
					</FeatureUpsell>
				</div>
			) : (
				fields.map( ( field ) => {
					if (
						field.hideOnPremium &&
						NewfoldRuntime.sdk.performance
							.jetpack_boost_premium_is_active
					) {
						return null; // Salta questo elemento
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

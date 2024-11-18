// Wordpress
import { useState } from '@wordpress/element';

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
			externalLink: true,
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
				fields.map( ( field ) => (
					<div
						className="nfd-performance-jetpack-boost-single-option"
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
				) )
			) }
		</>
	);
};

export default JetpackBoost;

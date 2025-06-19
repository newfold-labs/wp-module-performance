import { __ } from '@wordpress/i18n';

const getFontOptimizationText = () => ( {
	fontOptimizationTitle: __( 'Font Optimization', 'wp-module-performance' ),
	fontOptimizationDescription: __(
		'Improve load times by replacing Google Fonts with optimized local versions.',
		'wp-module-performance'
	),
	fontOptimizationLabel: __(
		'Optimize Fonts via Cloudflare',
		'wp-module-performance'
	),
	fontOptimizationToggleDescription: __(
		'Replaces Google Fonts with faster, privacy-friendly versions served locally.',
		'wp-module-performance'
	),
	fontOptimizationUpsellText: __(
		'Upgrade to enable font optimization using Cloudflare.',
		'wp-module-performance'
	),
	fontOptimizationUpsellLink: __(
		'https://www.bluehost.com/hosting/shared',
		'wp-module-performance'
	),
	fontOptimizationLoading: __(
		'Loading font optimization settings…',
		'wp-module-performance'
	),
	fontOptimizationError: __(
		'Error loading settings.',
		'wp-module-performance'
	),
	fontOptimizationUpdatedTitle: __(
		'Fonts optimization updated',
		'wp-module-performance'
	),
	fontOptimizationUpdatedDescription: __(
		'Font optimization setting saved successfully.',
		'wp-module-performance'
	),
	fontOptimizationErrorTitle: __( 'Update failed', 'wp-module-performance' ),
	fontOptimizationErrorDescription: __(
		'Could not save font optimization setting.',
		'wp-module-performance'
	),
} );

export default getFontOptimizationText;

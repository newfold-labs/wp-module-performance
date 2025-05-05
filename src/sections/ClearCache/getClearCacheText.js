import { __ } from '@wordpress/i18n';

const getClearCacheText = () => ( {
	clearCacheTitle: __( 'Clear Cache', 'wp-module-performance' ),
	clearCacheDescription: __(
		'We automatically clear your cache as you work (creating content, changing settings, installing plugins and more). But you can manually clear it here to be confident it is fresh.',
		'wp-module-performance'
	),
	clearCacheButton: __( 'Clear All Cache Now', 'wp-module-performance' ),
	clearCacheNoticeTitle: __( 'Cache cleared', 'wp-module-performance' ),
} );

export default getClearCacheText;

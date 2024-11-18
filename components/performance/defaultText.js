import { __ } from '@wordpress/i18n';

const defaultText = {
	cacheLevel0Description: __(
		'No cache enabled. Every page load is fresh. ',
		'wp-module-performance'
	),
	cacheLevel0Label: __( 'Disabled', 'wp-module-performance' ),
	cacheLevel0NoticeText: __( 'Caching disabled.', 'wp-module-performance' ),
	cacheLevel0Recommendation: __(
		'Not recommended.',
		'wp-module-performance'
	),
	cacheLevel1Description: __(
		'Cache static assets like images and the appearance of your site for 1 hour. ',
		'wp-module-performance'
	),
	cacheLevel1Label: __( 'Assets Only', 'wp-module-performance' ),
	cacheLevel1NoticeText: __(
		'Cache enabled for assets only.',
		'wp-module-performance'
	),
	cacheLevel1Recommendation: __(
		'Tuned for online stores and member sites that need to be fresh.',
		'wp-module-performance'
	),
	cacheLevel2Description: __(
		'Cache static assets for 24 hours and web pages for 2 hours. ',
		'wp-module-performance'
	),
	cacheLevel2Label: __( 'Assets & Web Pages', 'wp-module-performance' ),
	cacheLevel2NoticeText: __(
		'Cache enabled for assets and pages.',
		'wp-module-performance'
	),
	cacheLevel2Recommendation: __(
		'Tuned for sites that change at least weekly.',
		'wp-module-performance'
	),
	cacheLevel3Description: __(
		'Cache static assets for 1 week and web pages for 8 hours. ',
		'wp-module-performance'
	),
	cacheLevel3Label: __(
		'Assets & Web Pages - Extended',
		'wp-module-performance'
	),
	cacheLevel3NoticeText: __(
		'Cache enabled for assets and pages (extended).',
		'wp-module-performance'
	),
	cacheLevel3Recommendation: __(
		'Tuned for sites that update a few times a month or less.',
		'wp-module-performance'
	),
	cacheLevelDescription: __(
		'Boost speed and performance by storing a copy of your website content, files, and images online so the pages of your website load faster for your visitors.',
		'wp-module-performance'
	),
	cacheLevelNoticeTitle: __( 'Cache setting saved', 'wp-module-performance' ),
	cacheLevelTitle: __( 'Cache Level', 'wp-module-performance' ),
	clearCacheButton: __( 'Clear All Cache Now', 'wp-module-performance' ),
	clearCacheDescription: __(
		'We automatically clear your cache as you work (creating content, changing settings, installing plugins and more). But you can manually clear it here to be confident it is fresh.',
		'wp-module-performance'
	),
	clearCacheNoticeTitle: __( 'Cache cleared', 'wp-module-performance' ),
	clearCacheTitle: __( 'Clear Cache', 'wp-module-performance' ),
	imageOptimizationSettingsTitle: __(
		'Image Optimization',
		'wp-module-performance'
	),
	imageOptimizationSettingsDescription: __(
		'We automatically optimize your uploaded images to WebP format for faster performance and reduced file sizes. You can also choose to delete the original images to save storage space.',
		'wp-module-performance'
	),
	imageOptimizationEnabledLabel: __(
		'Enable Image Optimization',
		'wp-module-performance'
	),
	imageOptimizationEnabledDescription: __(
		'Enable or disable image optimization globally.',
		'wp-module-performance'
	),
	imageOptimizationAutoOptimizeLabel: __(
		'Automatically Optimize Uploaded Images',
		'wp-module-performance'
	),
	imageOptimizationAutoOptimizeDescription: __(
		'When enabled, all your image uploads will be automatically optimized to WebP format, ensuring faster page loading and reduced file sizes.',
		'wp-module-performance'
	),
	imageOptimizationAutoDeleteLabel: __(
		'Auto Delete Original Image',
		'wp-module-performance'
	),
	imageOptimizationAutoDeleteDescription: __(
		'Automatically delete the original uploaded image after optimization to save storage space.',
		'wp-module-performance'
	),
	imageOptimizationNoSettings: __(
		'No settings available.',
		'wp-module-performance'
	),
	imageOptimizationErrorMessage: __(
		'Oops! Something went wrong. Please try again.',
		'wp-module-performance'
	),
	imageOptimizationLoadingMessage: __(
		'Loading settings…',
		'wp-module-performance'
	),
	imageOptimizationUpdatedTitle: __(
		'Settings updated successfully',
		'wp-module-performance'
	),
	imageOptimizationUpdatedDescription: __(
		'Your image optimization settings have been saved.',
		'wp-module-performance'
	),
};

export default defaultText;

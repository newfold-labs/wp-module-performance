import { sprintf, __ } from '@wordpress/i18n';

const defaultText = {
	cacheLevel0Description: __(
		'No cache enabled. Every page load is fresh.',
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

	// Image Optimization
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
		'When enabled, all your new image uploads will be automatically optimized to WebP format, ensuring faster page loading and reduced file sizes.',
		'wp-module-performance'
	),
	imageOptimizationAutoDeleteLabel: __(
		'Auto Delete Original Image',
		'wp-module-performance'
	),
	imageOptimizationAutoDeleteDescription: __(
		'When enabled, the original uploaded image is deleted and replaced with the optimized version, helping to save storage space. If disabled, the optimized image is saved as a separate file, retaining the original.',
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
	imageOptimizationLazyLoadingLabel: __(
		'Enable Lazy Loading',
		'wp-module-performance'
	),
	imageOptimizationLazyLoadingDescription: __(
		'Lazy loading defers the loading of images until they are visible on the screen, improving page load speed and performance.',
		'wp-module-performance'
	),
	imageOptimizationLazyLoadingNoticeText: __(
		'Lazy loading has been updated.',
		'wp-module-performance'
	),
	imageOptimizationLazyLoadingErrorMessage: __(
		'Oops! There was an error updating the lazy loading settings.',
		'wp-module-performance'
	),
	imageOptimizationBulkOptimizeLabel: __(
		'Enable Bulk Optimization of Images',
		'wp-module-performance'
	),
	imageOptimizationBulkOptimizeDescription: __(
		'When enabled, allows bulk optimization of images in the media library.',
		'wp-module-performance'
	),
	imageOptimizationBulkOptimizeButtonLabel: __(
		'Go to Media Library',
		'wp-module-performance'
	),
	imageOptimizationUpdateErrorTitle: __(
		'Error Updating Settings',
		'wp-module-performance'
	),
	imageOptimizationPreferWebPLabel: __(
		'Prefer Optimized Image When Exists',
		'wp-module-performance'
	),
	imageOptimizationPreferWebPDescription: __(
		'When enabled, optimized images will be served in place of original images when they exist, improving performance.',
		'wp-module-performance'
	),
	imageOptimizationGenericErrorMessage: __(
		'Something went wrong while updating the settings. Please try again.',
		'wp-module-performance'
	),
	linkPrefetchDescription: __(
		'Asks the browser to download and cache links on the page ahead of them being clicked on, so that when they are clicked they load almost instantly. ',
		'wp-module-performance'
	),
	linkPrefetchNoticeTitle: __(
		'Link prefetching setting saved',
		'wp-module-performance'
	),
	linkPrefetchTitle: __( 'Link Prefetch', 'wp-module-performance' ),
	linkPrefetchActivateOnDesktopDescription: __(
		'Enable link prefetching on desktop',
		'wp-module-performance'
	),
	linkPrefetchActivateOnDesktopLabel: __(
		'Activate on desktop',
		'wp-module-performance'
	),
	linkPrefetchBehaviorDescription: __(
		'Behavior of the prefetch',
		'wp-module-performance'
	),
	linkPrefetchBehaviorLabel: __( 'Behavior', 'wp-module-performance' ),
	linkPrefetchBehaviorMouseDownLabel: __(
		'Prefetch on Mouse down',
		'wp-module-performance'
	),
	linkPrefetchBehaviorMouseDownDescription: __(
		'Prefetch on Mouse Down: Starts loading the page as soon as you click down, for faster response when you release the click.',
		'wp-module-performance'
	),
	linkPrefetchBehaviorMouseHoverLabel: __(
		'Prefetch on Mouse Hover (Recommended)',
		'wp-module-performance'
	),
	linkPrefetchBehaviorMouseHoverDescription: __(
		'Prefetch on Mouse Hover: Begins loading the page the moment your cursor hovers over a link',
		'wp-module-performance'
	),
	linkPrefetchActivateOnMobileDescription: __(
		'Enable link prefetching on Mobile',
		'wp-module-performance'
	),
	linkPrefetchActivateOnMobileLabel: __(
		'Activate on mobile',
		'wp-module-performance'
	),
	linkPrefetchBehaviorMobileTouchstartLabel: __(
		'Prefetch on Touchstart (Recommended)',
		'wp-module-performance'
	),
	linkPrefetchBehaviorMobileTouchstartDescription: __(
		'Prefetch on Touch Start: Instantly starts loading the page as soon as you tap the screen, ensuring a quicker response when you lift your finger.',
		'wp-module-performance'
	),
	linkPrefetchBehaviorMobileViewportLabel: __(
		'Prefetch Above the Fold',
		'wp-module-performance'
	),
	linkPrefetchBehaviorMobileViewportDescription: __(
		"Prefetch Above the Fold: Loads links in your current view instantly, ensuring they're ready when you need them.",
		'wp-module-performance'
	),
	linkPrefetchIgnoreKeywordsDescription: __(
		'Exclude Keywords: A comma separated list of words or strings that will exclude a link from being prefetched. For example, excluding "app" will prevent https://example.com/apple from being prefetched.',
		'wp-module-performance'
	),
	linkPrefetchIgnoreKeywordsLabel: __(
		'Exclude keywords',
		'wp-module-performance'
	),
	performanceAdvancedSettingsTitle: __(
		'Advanced settings',
		'wp-module-performance'
	),
	performanceAdvancedSettingsDescription: __(
		'Additional speed and scalability features powered by Jetpack Boost to make your site as fast as it can be.',
		'wp-module-performance'
	),
	jetpackBoostCriticalCssTitle: __(
		'Optimize Critical CSS Loading (manual)',
		'wp-module-performance'
	),
	jetpackBoostCriticalCssDescription: __(
		'Move important styling information to the start of the page, which helps pages display your content sooner, so your users don’t have to wait for the entire page to load.',
		'wp-module-performance'
	),
	jetpackBoostCriticalCssPremiumTitle: __(
		'Optimize Critical CSS Loading (UPGRADED)',
		'wp-module-performance'
	),
	jetpackBoostCriticalCssUpgradeTitle: __(
		'Generate Critical CSS Automatically',
		'wp-module-performance'
	),
	jetpackBoostCriticalCssPremiumDescription: sprintf(
		// translators: %1$s is a line break (<br>), %2$s is the opening <strong> tag, %3$s is the closing </strong> tag.
		__(
			'Move important styling information to the start of the page, which helps pages display your content sooner, so your users don’t have to wait for the entire page to load.%1$s %2$sBoost will automatically generate your Critical CSS%3$s whenever you make changes to the HTML or CSS structure of your site.',
			'wp-module-performance'
		),
		'<br>',
		'<strong>',
		'</strong>'
	),
	jetpackBoostRenderBlockingTitle: __(
		'Defer Non-Essential JavaScript',
		'wp-module-performance'
	),
	jetpackBoostRenderBlockingDescription: __(
		'Run non-essential JavaScript after the page has loaded so that styles and images can load more quickly.',
		'wp-module-performance'
	),
	jetpackBoostMinifyJsTitle: __( 'Concatenate JS', 'wp-module-performance' ),
	jetpackBoostMinifyJsDescription: __(
		'Scripts are grouped by their original placement, concatenated and minified to reduce site loading time and reduce the number of requests.',
		'wp-module-performance'
	),
	jetpackBoostExcludeJsTitle: __(
		'Exclude JS Strings',
		'wp-module-performance'
	),
	jetpackBoostMinifyCssTitle: __(
		'Concatenate CSS',
		'wp-module-performance'
	),
	jetpackBoostMinifyCssDescription: __(
		'Styles are grouped by their original placement, concatenated and minified to reduce site loading time and reduce the number of requests.',
		'wp-module-performance'
	),
	jetpackBoostExcludeCssTitle: __(
		'Exclude CSS Strings',
		'wp-module-performance'
	),
	jetpackBoostShowMore: __( 'Show more', 'wp-module-performance' ),
	jetpackBoostShowLess: __( 'Show less', 'wp-module-performance' ),
	jetpackBoostDicoverMore: __( 'Discover More', 'wp-module-performance' ),
	jetpackBoostCtaText: __(
		'Install Jetpack Boost to unlock',
		'wp-module-performance'
	),
	jetpackBoostInstalling: __(
		'Installing Jetpack Boost…',
		'wp-module-performance'
	),
	jetpackBoostActivated: __(
		'Jetpack Boost is now active',
		'wp-module-performance'
	),
	jetpackBoostActivationFailed: __(
		'Activation failed',
		'wp-module-performance'
	),
	// translators: %1$s is the opening <a> tag, %2$s is the closing </a> tag.
	jetpackBoostDiscoverMore: __(
		'Discover more %1$shere%2$s',
		'wp-module-performance'
	),
	optionSet: __( 'Option saved correctly', 'wp-module-performance' ),
	optionNotSet: __( 'Error saving option', 'wp-module-performance' ),
};

export default defaultText;

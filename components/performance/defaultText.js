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
};

export default defaultText;

import { __ } from '@wordpress/i18n';

const getAppText = () => ( {
	title: __( 'Performance', 'wp-module-performance' ),
	description: __(
		'Optimize your website by managing cache and performance settings',
		'wp-module-performance'
	),
} );

export default getAppText;

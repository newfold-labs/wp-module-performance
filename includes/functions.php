<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\CacheTypes\Skip404;

/**
 * Return defaul exclusions.
 *
 * @return array
 */
function get_default_cache_exclusions() {
	return join( ',', array( 'cart', 'checkout', 'wp-admin', rest_get_url_prefix() ) );
}

/**
 * Get the current cache level.
 *
 * @return int Cache level.
 */
function getCacheLevel() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return absint( get_option( CacheManager::OPTION_CACHE_LEVEL, 2 ) );
}

/**
 * Get available cache levels.
 *
 * @return string[] Cache levels.
 */
function getCacheLevels() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return array(
		0 => 'Off',         // Disable caching.
		1 => 'Assets Only', // Cache assets only.
		2 => 'Normal',      // Cache pages and assets for a shorter time range.
		3 => 'Advanced',    // Cache pages and assets for a longer time range.
	);
}

/**
 * Output the cache level select field.
 */
function getCacheLevelDropdown() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid

	$cacheLevels       = getCacheLevels();
	$currentCacheLevel = getCacheLevel();

	$name  = CacheManager::OPTION_CACHE_LEVEL;
	$label = __( 'Cache Level', 'wp-module-performance' );
	?>
	<select name="<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
		<?php foreach ( $cacheLevels as $cacheLevel => $optionLabel ) : ?>
			<option value="<?php echo absint( $cacheLevel ); ?>"<?php selected( $cacheLevel, $currentCacheLevel ); ?>>
				<?php echo esc_html( $optionLabel ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Get the "Skip WordPress 404 Handling for Static Files" option.
 *
 * @return bool Whether to skip 404 handling for static files.
 */
function getSkip404Option() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return (bool) get_option( Skip404::OPTION_SKIP_404, true );
}

/**
 * Output the "Skip WordPress 404 Handling for Static Files" input field.
 */
function getSkip404InputField() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	$name  = Skip404::OPTION_SKIP_404;
	$value = getSkip404Option();
	$label = __( 'Skip WordPress 404 Handling For Static Files', 'wp-module-performance' );
	?>
	<input
		type="checkbox"
		name="<?php echo esc_attr( $name ); ?>"
		value="1"
		aria-label="<?php echo esc_attr( $label ); ?>"
		<?php checked( $value, true ); ?>
	/>
	<?php
}

/**
 * Check if page caching is enabled.
 *
 * @return bool
 */
function shouldCachePages() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return getCacheLevel() > 1;
}

/**
 * Check if asset caching is enabled.
 *
 * @return bool
 */
function shouldCacheAssets() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return getCacheLevel() > 0;
}

/**
 * Remove a directory.
 *
 * @param string $path Path to the directory.
 */
function removeDirectory( $path ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	if ( ! is_dir( $path ) ) {
		return;
	}
	$files = glob( $path . '/*' );
	foreach ( $files as $file ) {
		is_dir( $file ) ? removeDirectory( $file ) : wp_delete_file( $file );
	}
	rmdir( $path );
}

/**
 * Convert a string to snake case.
 *
 * @param string $value     String to be converted.
 * @param string $delimiter Delimiter (can be a dash for conversion to kebab case).
 *
 * @return string
 */
function toSnakeCase( string $value, string $delimiter = '_' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	if ( ! ctype_lower( $value ) ) {
		$value = preg_replace( '/(\s+)/u', '', ucwords( $value ) );
		$value = trim( mb_strtolower( preg_replace( '/([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)/u', '$1' . $delimiter, $value ), 'UTF-8' ), $delimiter );
	}

	return $value;
}

/**
 * Convert a string to studly case.
 *
 * @param string $value String to be converted.
 *
 * @return string
 */
function toStudlyCase( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $value ) ) );
}

/**
 * Get styles path.
 *
 * return string
 */
function get_styles_path() {
	return 'vendor/newfold-labs/wp-module-performance/styles/styles.css';
}

/**
 * Get js script path.
 *
 * @param string $script_name Script name.
 * return string
 */
function get_scripts_path( $script_name = '' ) {
	$basePath = 'vendor/newfold-labs/wp-module-performance/scripts/';
	if ( empty( $script_name ) ) {
		return $basePath;
	}
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	return "vendor/newfold-labs/wp-module-performance/scripts/$script_name$suffix.js";
}

/**
 * Detect if the current page is a Brand Plugin settings page.
 *
 * @param string $brand The expected settings page identifier.
 * @return boolean True if the current page matches the brand settings page, false otherwise.
 */
function is_settings_page( $brand ) {

	$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) .
	'://' .
	( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) .
	( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );

	$parsed_url = wp_parse_url( $current_url );

	if ( ! isset( $parsed_url['query'] ) ) {
		return false;
	}

	parse_str( $parsed_url['query'], $query_params );

	if ( ! isset( $query_params['page'] ) || $query_params['page'] !== $brand ) {
		return false;
	}

	return true;
}

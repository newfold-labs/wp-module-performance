<?php

namespace NewfoldLabs\WP\Module\Performance;

/**
 * Get the current cache level.
 *
 * @return int
 */
function getCacheLevel() {
	return absint( get_option( Performance::OPTION_CACHE_LEVEL, 2 ) );
}

/**
 * Get available cache levels.
 *
 * @return string[]
 */
function getCacheLevels() {
	return array(
		0 => 'Off',         // Disable caching
		1 => 'Assets Only', // Cache assets only
		2 => 'Normal',      // Cache pages and assets for a shorter time range
		3 => 'Advanced',    // Cache pages and assets for a longer time range
	);
}

/**
 * Output the cache level select field.
 */
function getCacheLevelDropdown() {

	$cacheLevels       = getCacheLevels();
	$currentCacheLevel = getCacheLevel();

	$name  = Performance::OPTION_CACHE_LEVEL;
	$label = __( 'Cache Level', 'newfold-performance-module' );
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
 * @return bool
 */
function getSkip404Option() {
	return (bool) get_option( Performance::OPTION_SKIP_404, true );
}

/**
 * Output the "Skip WordPress 404 Handling for Static Files" input field.
 */
function getSkip404InputField() {
	$name  = Performance::OPTION_SKIP_404;
	$value = getSkip404Option();
	$label = __( 'Skip WordPress 404 Handling for Static Files', 'newfold-performance-module' );
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
function shouldCachePages() {
	return getCacheLevel() > 1;
}

/**
 * Check if asset caching is enabled.
 *
 * @return bool
 */
function shouldCacheAssets() {
	return getCacheLevel() > 0;
}

/**
 * Remove a directory.
 *
 * @param string $path
 */
function removeDirectory( $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$files = glob( $path . '/*' );
	foreach ( $files as $file ) {
		is_dir( $file ) ? removeDirectory( $file ) : unlink( $file );
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
function toSnakeCase( string $value, string $delimiter = '_' ) {
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
function toStudlyCase( $value ) {
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
 * @param string $script_name script name.
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
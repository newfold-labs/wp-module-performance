<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types\Fragments;

use NewfoldLabs\WP\Module\Htaccess\Fragment;
use NewfoldLabs\WP\Module\Htaccess\Context;
use NewfoldLabs\WP\Module\Performance\Cache\Types\Browser;

/**
 * Fragment: Browser Cache Rules
 *
 * Renders cache-control and expires headers into .htaccess
 * based on the configured cache level and optional URI exclusions.
 *
 * This fragment is exclusive and runs after the core WordPress block.
 *
 * @package NewfoldLabs\WP\Module\Performance\Cache\Types\Fragments
 * @since 1.0.0
 */
final class BrowserCacheFragment implements Fragment {
	/**
	 * Globally-unique fragment identifier used by the Registry.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Human-friendly marker label printed in BEGIN/END comments.
	 *
	 * @var string
	 */
	private $marker_label;

	/**
	 * Current cache level (0–3). Level 0 renders an explicit off switch.
	 *
	 * @var int
	 */
	private $cache_level;

	/**
	 * Optional pipe-separated set of URI path prefixes to exclude from caching.
	 * Example: "wp-admin|checkout|cart"
	 *
	 * @var string
	 */
	private $exclusion_pattern;

	/**
	 * Site base path (parsed from home_url('/')).
	 *
	 * @var string
	 */
	private $base_path;

	/**
	 * Constructor.
	 *
	 * @param string $id                Unique fragment ID.
	 * @param string $marker_label      Marker label for readability in the file.
	 * @param int    $cache_level       Cache level (0–3). Higher = longer TTLs.
	 * @param string $exclusion_pattern Pipe-separated pattern to exclude, or empty string.
	 * @param string $base_path         Site base path from home_url('/'), e.g. "/".
	 */
	public function __construct( $id, $marker_label, $cache_level, $exclusion_pattern = '', $base_path = '/' ) {
		$this->id                = (string) $id;
		$this->marker_label      = (string) $marker_label;
		$this->cache_level       = (int) $cache_level;
		$this->exclusion_pattern = (string) $exclusion_pattern;
		$this->base_path         = (string) $base_path;
	}

	/**
	 * Unique ID for this fragment.
	 *
	 * @return string
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Execution priority relative to other fragments.
	 * Runs after the WordPress core rules.
	 *
	 * @return int
	 */
	public function priority() {
		return self::PRIORITY_POST_WP;
	}

	/**
	 * Whether this fragment is exclusive (single instance in output).
	 *
	 * @return bool
	 */
	public function exclusive() {
		return true;
	}

	/**
	 * Whether this fragment is enabled for the given context.
	 * Upper-layer logic (Browser::maybeAddRules) decides what gets registered,
	 * so this always returns true once instantiated.
	 *
	 * @param Context $context Context snapshot (unused).
	 * @return bool
	 */
	public function is_enabled( $context ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return true;
	}

	/**
	 * Render the htaccess block.
	 *
	 * @param Context $context Context snapshot (unused).
	 * @return string Rendered fragment text including BEGIN/END comments.
	 */
	public function render( $context ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Level 0 still renders a block. Leaving the file empty of one would let a
		// site in a subdirectory inherit the parent directory's mod_expires rules,
		// so the off state is written out rather than implied by an absence.
		if ( $this->cache_level < 1 ) {
			return implode(
				"\n",
				array(
					'# BEGIN ' . $this->marker_label,
					'<IfModule mod_expires.c>',
					"\tExpiresActive Off",
					'</IfModule>',
					'# END ' . $this->marker_label,
				)
			);
		}

		$expirations = Browser::getFileTypeExpirations( $this->cache_level );

		$lines   = array();
		$lines[] = '# BEGIN ' . $this->marker_label;
		$lines[] = '<IfModule mod_expires.c>';
		$lines[] = "\tExpiresActive On";

		foreach ( $expirations as $type => $ttl ) {
			if ( 'default' === $type ) {
				$lines[] = "\tExpiresDefault \"access plus {$ttl}\"";
			} else {
				$lines[] = "\tExpiresByType {$type} \"access plus {$ttl}\"";
			}
		}

		$lines[] = '</IfModule>';

		// Optional cache-exclusion rules.

		/*
		* Clear both Apache response-header tables. "always" is not a
		* superset of "onsuccess" for existing headers, so both are
		* cleared to prevent conflicting cache headers from surviving.
		*/
		if ( '' !== $this->exclusion_pattern ) {
			$condition = $this->get_exclusion_condition();

			$lines[] = '<IfModule mod_headers.c>';

			$lines[] = 'Header onsuccess unset Cache-Control ' . $condition;
			$lines[] = 'Header always unset Cache-Control ' . $condition;

			$lines[] = 'Header onsuccess unset Expires ' . $condition;
			$lines[] = 'Header always unset Expires ' . $condition;

			$lines[] = 'Header onsuccess unset Pragma ' . $condition;
			$lines[] = 'Header always unset Pragma ' . $condition;

			$lines[] = 'Header always set Cache-Control "no-cache, no-store, must-revalidate" ' . $condition;
			$lines[] = 'Header always set Pragma "no-cache" ' . $condition;

			$lines[] = '</IfModule>';
		}

		$lines[] = '# END ' . $this->marker_label;

		return implode( "\n", $lines );
	}

	/**
	 * Build the Apache expression used for browser-cache exclusions.
	 *
	 * THE_REQUEST covers raw request lines, while REQUEST_URI covers URL-decoded
	 * paths. Using both ensures exclusions apply to encoded request paths.
	 *
	 * The site base path supports WordPress installations in a subdirectory.
	 * Trailing boundaries prevent "team" from matching paths such as
	 * "team-available".
	 *
	 * @return string
	 */
	private function get_exclusion_condition() {
		$base_path   = trim( $this->base_path, '/' );
		$path_prefix = '' !== $base_path ? preg_quote( $base_path, '#' ) . '/' : '';

		$the_request_pattern = '^[A-Z]+[[:space:]]+/' . $path_prefix . '('
			. $this->exclusion_pattern
			. ')(/|\?|[[:space:]])';
		$request_uri_pattern = '^/' . $path_prefix . '('
			. $this->exclusion_pattern
			. ')(/|\?|$)';

		return '"expr=( %{THE_REQUEST} =~ m#' . $the_request_pattern
			. '#i || %{REQUEST_URI} =~ m#' . $request_uri_pattern
			. '#i )"';
	}

	/**
	 * Optional regex patches (none for this fragment).
	 *
	 * @param Context $context Context snapshot (unused).
	 * @return array
	 */
	public function patches( $context ) {
		return array();
	}
}

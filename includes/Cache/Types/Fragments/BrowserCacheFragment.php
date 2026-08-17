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
	 * Current cache level (1–3). Level 0 is handled by unregistering the fragment.
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
	 * Constructor.
	 *
	 * @param string $id                Unique fragment ID.
	 * @param string $marker_label      Marker label for readability in the file.
	 * @param int    $cache_level       Cache level (1–3). Higher = longer TTLs.
	 * @param string $exclusion_pattern Pipe-separated pattern to exclude, or empty string.
	 */
	public function __construct( $id, $marker_label, $cache_level, $exclusion_pattern = '' ) {
		$this->id                = (string) $id;
		$this->marker_label      = (string) $marker_label;
		$this->cache_level       = (int) $cache_level;
		$this->exclusion_pattern = (string) $exclusion_pattern;
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
	 * Upper-layer logic (Browser::maybeAddRules) registers/unregisters this,
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
	 * THE_REQUEST is used instead of a SetEnvIf environment variable because
	 * WordPress front-controller processing may make that variable unavailable
	 * by the time response headers are applied.
	 *
	 * The trailing boundary allows a path separator, query string, or the
	 * whitespace before the HTTP version, preventing "team" from matching
	 * paths such as "team-available".
	 *
	 * @return string
	 */
	private function get_exclusion_condition() {
		return '"expr=%{THE_REQUEST} =~ m#^[A-Z]+[[:space:]]+/('
			. $this->exclusion_pattern
			. ')(/|\?|[[:space:]])#i"';
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

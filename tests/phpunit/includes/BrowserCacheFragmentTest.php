<?php
// phpcs:disable

namespace NewfoldLabs\WP\Module\Performance\Cache\Types\Fragments {

	use WP_Mock;
	use WP_Mock\Tools\TestCase;
	use NewfoldLabs\WP\Module\Performance\Cache\Types\Fragments\BrowserCacheFragment;

	/**
	 * Test BrowserCacheFragment htaccess rendering.
	 */
	class BrowserCacheFragmentTest extends TestCase {

		/**
		 * Set up the test environment.
		 */
		public function setUp(): void {
			WP_Mock::setUp();
		}

		/**
		 * Tear down the test environment.
		 */
		public function tearDown(): void {
			WP_Mock::tearDown();
		}

		/**
		 * Build a fragment instance for testing.
		 *
		 * @param int    $cache_level       Cache level.
		 * @param string $exclusion_pattern Pipe-separated exclusion slugs.
		 * @return BrowserCacheFragment
		 */
		private function make_fragment( $cache_level, $exclusion_pattern = '' ) {
			return new BrowserCacheFragment(
				'nfd.cache.browser',
				'Newfold Browser Cache',
				$cache_level,
				$exclusion_pattern
			);
		}

		/**
		 * Simulates Apache THE_REQUEST expr matching for boundary tests.
		 *
		 * @param string $pattern      Pipe-separated slug alternation.
		 * @param string $the_request  Simulated request line, e.g. "GET /team/ HTTP/1.1".
		 * @return bool
		 */
		private function the_request_matches( $pattern, $the_request ) {
			return 1 === preg_match(
				'#^[A-Z]+\s+/(' . $pattern . ')(/|\?|\s)#i',
				$the_request
			);
		}

		/**
		 * Test render output without exclusions.
		 */
		public function test_render_without_exclusions() {
			$output = $this->make_fragment( 3, '' )->render( null );

			$this->assertStringContainsString( '<IfModule mod_expires.c>', $output );
			$this->assertStringContainsString( 'ExpiresByType text/html "access plus 8 hours"', $output );
			$this->assertStringNotContainsString( 'expr=%{THE_REQUEST}', $output );
			$this->assertStringNotContainsString( 'RewriteCond', $output );
			$this->assertStringNotContainsString( 'SetEnvIf', $output );
			$this->assertStringNotContainsString( 'NFD_NOCACHE', $output );
		}

		/**
		 * Test render output contains THE_REQUEST expression when exclusions exist.
		 */
		public function test_render_with_exclusions_contains_the_request_expr() {
			$pattern = 'cart|checkout|wp-admin|wp-json|team';
			$output  = $this->make_fragment( 3, $pattern )->render( null );

			$expected_expr = 'expr=%{THE_REQUEST} =~ m#^[A-Z]+[[:space:]]+/('
				. $pattern
				. ')(/|\?|[[:space:]])#i';

			$this->assertStringContainsString( $expected_expr, $output );
		}

		/**
		 * Test render output contains required header directives for exclusions.
		 */
		public function test_render_with_exclusions_contains_header_directives() {
			$pattern = 'cart|checkout|wp-admin|wp-json|team';
			$output  = $this->make_fragment( 3, $pattern )->render( null );

			$this->assertStringContainsString( 'Header onsuccess unset Cache-Control', $output );
			$this->assertStringContainsString( 'Header always unset Cache-Control', $output );
			$this->assertStringContainsString( 'Header onsuccess unset Expires', $output );
			$this->assertStringContainsString( 'Header always unset Expires', $output );
			$this->assertStringContainsString( 'Header onsuccess unset Pragma', $output );
			$this->assertStringContainsString( 'Header always unset Pragma', $output );
			$this->assertStringContainsString(
				'Header always set Cache-Control "no-cache, no-store, must-revalidate"',
				$output
			);
			$this->assertStringContainsString( 'Header always set Pragma "no-cache"', $output );
			$this->assertStringNotContainsString( 'Header set Expires 0', $output );
		}

		/**
		 * Test render output does not contain removed implementations.
		 */
		public function test_render_does_not_contain_removed_implementations() {
			$output = $this->make_fragment( 3, 'cart|team' )->render( null );

			$this->assertStringNotContainsString( 'RewriteEngine', $output );
			$this->assertStringNotContainsString( 'RewriteCond', $output );
			$this->assertStringNotContainsString( 'SetEnvIf', $output );
			$this->assertStringNotContainsString( 'NFD_NOCACHE', $output );
			$this->assertStringNotContainsString( 'env=NFD_NOCACHE', $output );
		}

		/**
		 * Test mod_expires TTLs are preserved for the configured cache level.
		 */
		public function test_render_preserves_mod_expires_for_cache_level() {
			$output = $this->make_fragment( 3, 'team' )->render( null );

			$this->assertStringContainsString( 'ExpiresByType text/html "access plus 8 hours"', $output );
			$this->assertStringContainsString( 'ExpiresDefault "access plus 1 week"', $output );
		}

		/**
		 * Test exclusion regex matches intended request lines.
		 *
		 * @dataProvider matching_the_request_provider
		 * @param string $the_request Simulated THE_REQUEST value.
		 */
		public function test_exclusion_regex_matches_intended_requests( $the_request ) {
			$pattern = 'cart|checkout|wp-admin|wp-json|team';
			$this->assertTrue( $this->the_request_matches( $pattern, $the_request ) );
		}

		/**
		 * Data provider for requests that should match exclusions.
		 *
		 * @return array
		 */
		public function matching_the_request_provider() {
			return array(
				array( 'GET /team HTTP/1.1' ),
				array( 'GET /team/ HTTP/1.1' ),
				array( 'GET /team/member HTTP/1.1' ),
				array( 'GET /team?foo=bar HTTP/1.1' ),
				array( 'GET /wp-admin HTTP/1.1' ),
				array( 'GET /wp-admin/ HTTP/1.1' ),
				array( 'GET /wp-admin/admin.php HTTP/1.1' ),
				array( 'GET /wp-admin?foo=bar HTTP/1.1' ),
			);
		}

		/**
		 * Test exclusion regex does not match false-positive request lines.
		 *
		 * @dataProvider non_matching_the_request_provider
		 * @param string $the_request Simulated THE_REQUEST value.
		 */
		public function test_exclusion_regex_does_not_match_false_positives( $the_request ) {
			$pattern = 'cart|checkout|wp-admin|wp-json|team';
			$this->assertFalse( $this->the_request_matches( $pattern, $the_request ) );
		}

		/**
		 * Data provider for requests that should not match exclusions.
		 *
		 * @return array
		 */
		public function non_matching_the_request_provider() {
			return array(
				array( 'GET /team-available HTTP/1.1' ),
				array( 'GET /teamwork HTTP/1.1' ),
				array( 'GET /team2 HTTP/1.1' ),
				array( 'GET /teams HTTP/1.1' ),
				array( 'GET /wp-admin-available HTTP/1.1' ),
				array( 'GET /wp-administrator HTTP/1.1' ),
			);
		}
	}
}
// phpcs:enable

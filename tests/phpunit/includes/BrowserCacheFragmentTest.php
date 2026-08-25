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
		 * @param string $base_path         Site base path.
		 * @return BrowserCacheFragment
		 */
		private function make_fragment( $cache_level, $exclusion_pattern = '', $base_path = '/' ) {
			return new BrowserCacheFragment(
				'nfd.cache.browser',
				'Newfold Browser Cache',
				$cache_level,
				$exclusion_pattern,
				$base_path
			);
		}

		/**
		 * Invoke the fragment's private exclusion-condition builder.
		 *
		 * @param BrowserCacheFragment $fragment Fragment under test.
		 * @return string
		 */
		private function get_exclusion_condition( BrowserCacheFragment $fragment ) {
			$method = new \ReflectionMethod( BrowserCacheFragment::class, 'get_exclusion_condition' );
			$method->setAccessible( true );

			return $method->invoke( $fragment );
		}

		/**
		 * Extract a regex from the rendered Apache expression.
		 *
		 * @param BrowserCacheFragment $fragment         Fragment under test.
		 * @param string               $request_variable Apache request variable.
		 * @return string
		 */
		private function get_exclusion_regex( BrowserCacheFragment $fragment, $request_variable ) {
			$condition = $this->get_exclusion_condition( $fragment );
			$pattern   = '/%\{' . preg_quote( $request_variable, '/' ) . '\}\s*=~\s*m#(.+?)#i/';

			if ( 1 !== preg_match( $pattern, $condition, $matches ) ) {
				$this->fail( 'Could not extract the exclusion regex from: ' . $condition );
			}

			return '#' . $matches[1] . '#i';
		}

		/**
		 * Match a simulated raw request line against the production THE_REQUEST regex.
		 *
		 * @param BrowserCacheFragment $fragment    Fragment under test.
		 * @param string               $the_request Simulated request line.
		 * @return bool
		 */
		private function the_request_matches( BrowserCacheFragment $fragment, $the_request ) {
			return 1 === preg_match(
				$this->get_exclusion_regex( $fragment, 'THE_REQUEST' ),
				$the_request
			);
		}

		/**
		 * Match a simulated decoded URI against the production REQUEST_URI regex.
		 *
		 * @param BrowserCacheFragment $fragment    Fragment under test.
		 * @param string               $request_uri Simulated decoded request URI.
		 * @return bool
		 */
		private function request_uri_matches( BrowserCacheFragment $fragment, $request_uri ) {
			return 1 === preg_match(
				$this->get_exclusion_regex( $fragment, 'REQUEST_URI' ),
				$request_uri
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
		 * Test render output contains the generated exclusion expression.
		 */
		public function test_render_with_exclusions_contains_generated_expr() {
			$fragment  = $this->make_fragment( 3, 'cart|checkout|wp-admin|wp-json|team' );
			$output    = $fragment->render( null );
			$condition = trim( $this->get_exclusion_condition( $fragment ), '"' );

			$this->assertStringContainsString( $condition, $output );
			$this->assertStringContainsString( '%{THE_REQUEST}', $condition );
			$this->assertStringContainsString( '%{REQUEST_URI}', $condition );
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
		 * Test level 0 renders an explicit off switch rather than nothing.
		 */
		public function test_render_at_level_zero_turns_expires_off() {
			$output = $this->make_fragment( 0, '' )->render( null );

			$this->assertStringContainsString( '# BEGIN Newfold Browser Cache', $output );
			$this->assertStringContainsString( '<IfModule mod_expires.c>', $output );
			$this->assertStringContainsString( 'ExpiresActive Off', $output );
			$this->assertStringContainsString( '# END Newfold Browser Cache', $output );
			$this->assertStringNotContainsString( 'ExpiresActive On', $output );
			$this->assertStringNotContainsString( 'ExpiresDefault', $output );
			$this->assertStringNotContainsString( 'ExpiresByType', $output );
		}

		/**
		 * Exclusions carve exceptions out of caching we applied. At level 0 there
		 * is none to carve out of, so the header rules stay out of the block.
		 */
		public function test_render_at_level_zero_omits_exclusion_headers() {
			$output = $this->make_fragment( 0, 'cart|checkout|wp-admin' )->render( null );

			$this->assertStringNotContainsString( 'mod_headers.c', $output );
			$this->assertStringNotContainsString( 'Cache-Control', $output );
			$this->assertStringNotContainsString( '%{THE_REQUEST}', $output );
		}

		/**
		 * Test exclusion regex matches intended request lines.
		 *
		 * @dataProvider matching_the_request_provider
		 * @param string $the_request Simulated THE_REQUEST value.
		 */
		public function test_exclusion_regex_matches_intended_requests( $the_request ) {
			$fragment = $this->make_fragment( 3, 'cart|checkout|wp-admin|wp-json|team' );
			$this->assertTrue( $this->the_request_matches( $fragment, $the_request ) );
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
			$fragment = $this->make_fragment( 3, 'cart|checkout|wp-admin|wp-json|team' );
			$this->assertFalse( $this->the_request_matches( $fragment, $the_request ) );
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

		/**
		 * Test exclusion regex includes the site base path for subfolder installs.
		 */
		public function test_exclusion_regex_matches_subfolder_install_requests() {
			$fragment  = $this->make_fragment( 3, 'cart|checkout|wp-admin|wp-json|team', '/blog/' );
			$condition = $this->get_exclusion_condition( $fragment );

			$this->assertStringContainsString( '/blog/(cart|checkout|wp-admin|wp-json|team)', $condition );
			$this->assertTrue( $this->the_request_matches( $fragment, 'GET /blog/wp-admin/ HTTP/1.1' ) );
			$this->assertFalse( $this->the_request_matches( $fragment, 'GET /wp-admin/ HTTP/1.1' ) );
		}

		/**
		 * Test REQUEST_URI covers decoded paths that THE_REQUEST leaves encoded.
		 */
		public function test_request_uri_regex_matches_decoded_encoded_path() {
			$fragment = $this->make_fragment( 3, 'cart|checkout|wp-admin|wp-json|team' );

			$this->assertFalse( $this->the_request_matches( $fragment, 'GET /%77p-admin/ HTTP/1.1' ) );
			$this->assertTrue( $this->request_uri_matches( $fragment, '/wp-admin/' ) );
		}
	}
}
// phpcs:enable

<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types\WPCLI;

use NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheDiagnostics;
use WP_CLI;

/**
 * Handles "wp nfd performance object_cache" WP-CLI commands.
 */
class ObjectCacheCommandHandler {

	/**
	 * Maps a diagnostic line status to a colorized tag for terminal output.
	 *
	 * @var array<string, string>
	 */
	private const STATUS_TAGS = array(
		ObjectCacheDiagnostics::STATUS_OK   => '%G[ OK ]%n',
		ObjectCacheDiagnostics::STATUS_WARN => '%Y[WARN]%n',
		ObjectCacheDiagnostics::STATUS_FAIL => '%R[FAIL]%n',
		ObjectCacheDiagnostics::STATUS_INFO => '%C[INFO]%n',
	);

	/**
	 * Runs read-only Redis / object cache diagnostics.
	 *
	 * Reports phpredis availability, Redis connection constants, wp-config state,
	 * unix socket reachability, a live Redis PING, and the object-cache drop-in
	 * status, followed by a diagnosis summary. The report is read-only: it never
	 * writes files or options, and it never prints Redis credentials (the password
	 * and username are shown as presence only).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render format for the report.
	 * ---
	 * default: report
	 * options:
	 *   - report
	 *   - json
	 * ---
	 *
	 * [--strict]
	 * : Exit with a non-zero status when diagnostics find issues (useful in scripts and CI).
	 *
	 * ## EXAMPLES
	 *
	 *     # Print a human-readable diagnostics report.
	 *     wp nfd performance object_cache diagnose
	 *
	 *     # Emit the report as JSON for scripting or support tooling.
	 *     wp nfd performance object_cache diagnose --format=json
	 *
	 *     # Fail the command (non-zero exit) when issues are found.
	 *     wp nfd performance object_cache diagnose --strict
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function diagnose( $args, $assoc_args ) {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'report' );
		$strict = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'strict', false );

		// WP-CLI validates --format against the synopsis options above; this guards direct/programmatic calls too.
		$allowed_formats = array( 'report', 'json' );
		if ( ! in_array( $format, $allowed_formats, true ) ) {
			WP_CLI::error(
				sprintf(
					/* translators: %s is the comma-separated list of valid --format values. */
					__( 'Invalid value for --format. Use one of: %s.', 'wp-module-performance' ),
					implode( ', ', $allowed_formats )
				)
			);
		}

		$report = ObjectCacheDiagnostics::run();
		$passed = ! empty( $report['summary']['ok'] );

		if ( 'json' === $format ) {
			WP_CLI::line( (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			if ( $strict && ! $passed ) {
				WP_CLI::halt( 1 );
			}
			return;
		}

		$this->render_report( $report );
		$this->render_verdict( $passed, $strict );
	}

	/**
	 * Renders the report sections as readable terminal output.
	 *
	 * @param array $report Report from ObjectCacheDiagnostics::run().
	 *
	 * @return void
	 */
	private function render_report( array $report ) {
		WP_CLI::line( WP_CLI::colorize( '%BRedis Object Cache Diagnostics%n' ) );
		WP_CLI::line( 'Generated: ' . ( $report['generated'] ?? '' ) );

		foreach ( $report['sections'] as $section ) {
			$title = (string) $section['title'];
			$width = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%Y' . $title . '%n' ) );
			WP_CLI::line( str_repeat( '-', max( 8, $width ) ) );
			foreach ( $section['lines'] as $line ) {
				$tag = self::STATUS_TAGS[ $line['status'] ] ?? self::STATUS_TAGS[ ObjectCacheDiagnostics::STATUS_INFO ];
				WP_CLI::line( WP_CLI::colorize( $tag ) . ' ' . $line['message'] );
			}
		}

		WP_CLI::line( '' );
	}

	/**
	 * Emits the final pass/fail verdict. In strict mode a failure exits non-zero.
	 *
	 * @param bool $passed Whether all diagnostics passed.
	 * @param bool $strict Whether --strict was supplied.
	 *
	 * @return void
	 */
	private function render_verdict( $passed, $strict ) {
		if ( $passed ) {
			WP_CLI::success( __( 'Object cache diagnostics passed.', 'wp-module-performance' ) );
			return;
		}

		$message = __( 'Object cache diagnostics found issues. See the "Diagnosis summary" section above.', 'wp-module-performance' );
		if ( $strict ) {
			WP_CLI::error( $message ); // Non-zero exit for scripts/CI.
		}
		WP_CLI::warning( $message );
	}
}

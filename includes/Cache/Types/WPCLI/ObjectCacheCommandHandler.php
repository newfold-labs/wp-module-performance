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
	 * ## EXAMPLES
	 *
	 *     # Print a human-readable diagnostics report.
	 *     wp nfd performance object_cache diagnose
	 *
	 *     # Emit the report as JSON for scripting or support tooling.
	 *     wp nfd performance object_cache diagnose --format=json
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function diagnose( $args, $assoc_args ) {
		$report = ObjectCacheDiagnostics::run();

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'report';
		if ( 'json' === $format ) {
			WP_CLI::line( (string) wp_json_encode( $report, JSON_PRETTY_PRINT ) );
			return;
		}

		$this->render_report( $report );
	}

	/**
	 * Renders the structured report as a readable terminal report.
	 *
	 * @param array $report Report from ObjectCacheDiagnostics::run().
	 *
	 * @return void
	 */
	private function render_report( array $report ) {
		WP_CLI::line( WP_CLI::colorize( '%BRedis Object Cache Diagnostics%n' ) );
		WP_CLI::line( 'Generated: ' . ( $report['generated'] ?? '' ) );

		foreach ( $report['sections'] as $section ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%Y' . $section['title'] . '%n' ) );
			WP_CLI::line( str_repeat( '-', max( 8, strlen( $section['title'] ) ) ) );
			foreach ( $section['lines'] as $line ) {
				$tag = self::STATUS_TAGS[ $line['status'] ] ?? self::STATUS_TAGS[ ObjectCacheDiagnostics::STATUS_INFO ];
				WP_CLI::line( WP_CLI::colorize( $tag ) . ' ' . $line['message'] );
			}
		}

		WP_CLI::line( '' );
		if ( ! empty( $report['summary']['ok'] ) ) {
			WP_CLI::success( __( 'Object cache diagnostics passed.', 'wp-module-performance' ) );
		} else {
			WP_CLI::warning( __( 'Object cache diagnostics found issues. See the "Diagnosis summary" section above.', 'wp-module-performance' ) );
		}
	}
}

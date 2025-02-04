<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Images\WPCLI\ImageCommandHandler;

/**
 * Manages all "wp nfd performance" WP-CLI commands.
 */
class PerformanceWPCLI {
	/**
	 * Command namespace.
	 *
	 * @var string
	 */
	private static $cmd_namespace = 'performance';

	/**
	 * List of performance-related WP-CLI commands.
	 *
	 * @var array
	 */
	private static $commands = array(
		'images' => ImageCommandHandler::class,
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		foreach ( self::$commands as $command => $handler ) {
			if ( class_exists( $handler ) ) {
				NFD_WPCLI::add_command( self::$cmd_namespace, $command, $handler );
			}
		}

		new NFD_WPCLI();
	}
}

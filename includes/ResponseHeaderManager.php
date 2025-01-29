<?php

namespace NewfoldLabs\WP\Module\Performance;

use WP_Forge\WP_Htaccess_Manager\htaccess;

use function WP_Forge\WP_Htaccess_Manager\convertContentToLines;

/**
 * Manage response headers.
 */
class ResponseHeaderManager {

	/**
	 * The file marker name.
	 *
	 * @var string
	 */
	const MARKER = 'Newfold Headers';

	/**
	 * The htaccess manager.
	 *
	 * @var htaccess
	 */
	public $htaccess;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->htaccess = new htaccess( self::MARKER );
	}

	/**
	 * Parse existing headers.
	 *
	 * @return array
	 */
	public function parseHeaders() {

		$headers = array();

		$content = $this->htaccess->readContent();
		$lines   = array_map( 'trim', convertContentToLines( $content ) );

		array_shift( $lines ); // Remove opening IfModule
		array_pop( $lines ); // Remove closing IfModule

		$pattern = '/^Header set (.*) "(.*)"$/';

		foreach ( $lines as $line ) {
			if ( preg_match( $pattern, trim( $line ), $matches ) && isset( $matches[1], $matches[2] ) ) {
				$headers[ $matches[1] ] = $matches[2];
			}
		}

		return $headers;
	}

	/**
	 * Add a header.
	 *
	 * @param string $name  Header name
	 * @param string $value Header value
	 */
	public function addHeader( string $name, string $value ) {
		$this->setHeaders(
			array_merge(
				$this->parseHeaders(),
				array( $name => $value )
			)
		);
	}

	/**
	 * Add multiple headers at once.
	 *
	 * @param string[] $headers Headers to add.
	 */
	public function addHeaders( array $headers ) {
		$headers = array_merge( $this->parseHeaders(), $headers );
		$this->setHeaders( $headers );
	}

	/**
	 * Remove a header.
	 *
	 * @param string $name Header name
	 */
	public function removeHeader( $name ) {
		$headers = $this->parseHeaders();
		unset( $headers[ $name ] );
		$this->setHeaders( $headers );
	}

	/**
	 * Remove all headers.
	 */
	public function removeAllHeaders() {
		$this->setHeaders( array() );
	}

	/**
	 * Set headers.
	 *
	 * @param array $headers Headers to set.
	 */
	public function setHeaders( array $headers ) {

		if ( empty( $headers ) ) {
			$this->htaccess->removeContent();

			return;
		}

		$content = '<IfModule mod_headers.c>' . PHP_EOL;
		foreach ( $headers as $key => $value ) {
			$content .= "\tHeader set {$key} \"{$value}\"" . PHP_EOL;
		}
		$content .= '</IfModule>';

		$this->htaccess->addContent( $content );
	}
}

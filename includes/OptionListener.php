<?php

namespace NewfoldLabs\WP\Module\Performance;

class OptionListener {

	/**
	 * Callback function to be called on change.
	 *
	 * @var callable
	 */
	protected $callable;

	/**
	 * Name of the option to monitor.
	 *
	 * @var string
	 */
	protected $option;

	/**
	 * Constructor
	 *
	 * @param string   $optionName The name of the option to monitor.
	 * @param callable $callable   The callback function to be called on change.
	 */
	public function __construct( string $optionName, callable $callable ) {

		$this->callable = $callable;
		$this->option   = $optionName;

		add_action( "add_option_{$optionName}", [ $this, 'onAdd' ], 10, 2 );
		add_action( "update_option_{$optionName}", [ $this, 'onUpdate' ], 10, 2 );
		add_action( "delete_option_{$optionName}", [ $this, 'onDelete' ] );

	}

	/**
	 * Call function when a new option value is added.
	 *
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 */
	public function onAdd( $option, $value ) {
		call_user_func( $this->callable, $value, $option );
	}

	/**
	 * Call function when an option value is updated.
	 *
	 * @param mixed $oldValue The old option value.
	 * @param mixed $newValue The new option value.
	 */
	public function onUpdate( $oldValue, $newValue ) {
		if ( $oldValue !== $newValue ) {
			call_user_func( $this->callable, $newValue, $this->option );
		}
	}

	/**
	 * Call function when an option is deleted.
	 */
	public function onDelete() {
		call_user_func( $this->callable, null, $this->option );
	}

}

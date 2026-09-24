<?php
/**
 * Minimal WP-CLI stubs for PHPStan (only the API starter-core uses).
 *
 * php-stubs/wp-cli-stubs pins php-stubs/wordpress-stubs to <= 6.x, which conflicts with the
 * WordPress 7.x stubs used here, so the few signatures we need are declared locally.
 * Add a method here when core code starts using it.
 */

// phpcs:ignoreFile

class WP_CLI {
	/**
	 * @param string          $name
	 * @param callable|string $callable
	 * @param array<string, mixed> $args
	 */
	public static function add_command( $name, $callable, $args = array() ): bool {}

	/** @param string $message */
	public static function log( $message ): void {}

	/** @param string $message */
	public static function success( $message ): void {}

	/** @param string $message */
	public static function warning( $message ): void {}

	/**
	 * @param string|\WP_Error $message
	 * @param bool|int         $exit
	 * @return never
	 */
	public static function error( $message, $exit = true ) {}
}

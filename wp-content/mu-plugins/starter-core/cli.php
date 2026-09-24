<?php
/**
 * WP-CLI: wp starter seed [--only=<targets>] [--dry-run] [--dir=<path>]
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once STARTER_CORE_PATH . '/class-starter-cli-command.php';

WP_CLI::add_command( 'starter', 'Starter_CLI_Command' );

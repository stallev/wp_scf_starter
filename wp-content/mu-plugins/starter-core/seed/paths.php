<?php
/**
 * Seed directory and run context (dir override, dry-run).
 *
 * The seed JSON lives in the repo root `seed/`, which is not under wp-content and is not deployed.
 * Locally wp-env maps it to wp-content/starter-seed (.wp-env.json → mappings). Elsewhere define
 * STARTER_SEED_DIR in wp-config.php or pass `--dir=` to `wp starter seed`.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'STARTER_SEED_DIR' ) ) {
	define( 'STARTER_SEED_DIR', WP_CONTENT_DIR . '/starter-seed' );
}

/**
 * Get or set the context of the current seed run.
 *
 * @param array{dir?: string, dry_run?: bool}|null $set New context (merged); null = read.
 * @return array{dir: string, dry_run: bool}
 */
function starter_seed_context( ?array $set = null ): array {
	static $context = array(
		'dir'     => '',
		'dry_run' => false,
	);

	if ( null !== $set ) {
		$context = array(
			'dir'     => (string) ( $set['dir'] ?? $context['dir'] ),
			'dry_run' => (bool) ( $set['dry_run'] ?? $context['dry_run'] ),
		);
	}

	return $context;
}

/**
 * Whether the current run must not write anything.
 *
 * @return bool
 */
function starter_seed_is_dry_run(): bool {
	return starter_seed_context()['dry_run'];
}

/**
 * Absolute seed directory without a trailing slash.
 *
 * Priority: run context (`--dir=`), filter `starter_seed_path`, STARTER_SEED_DIR.
 *
 * @return string
 */
function starter_seed_path(): string {
	$dir = starter_seed_context()['dir'];

	if ( '' === $dir ) {
		/**
		 * Filter the seed directory.
		 *
		 * @param string $dir Absolute path.
		 */
		$dir = (string) apply_filters( 'starter_seed_path', (string) STARTER_SEED_DIR );
	}

	return untrailingslashit( $dir );
}

/**
 * Absolute path of a file inside the seed directory.
 *
 * @param string $filename Relative name, e.g. faq.json.
 * @return string
 */
function starter_seed_file( string $filename ): string {
	return starter_seed_path() . '/' . ltrim( $filename, '/' );
}

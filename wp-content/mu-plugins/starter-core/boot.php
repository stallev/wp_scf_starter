<?php
/**
 * Boot starter-core modules.
 *
 * Order matters: config and helpers first, then data model (CPT, options, fields), then features.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

require_once STARTER_CORE_PATH . '/config.php';
require_once STARTER_CORE_PATH . '/helpers.php';
require_once STARTER_CORE_PATH . '/defaults.php';
require_once STARTER_CORE_PATH . '/post-types.php';
require_once STARTER_CORE_PATH . '/options.php';

// SCF field groups: one file = one group.
require_once STARTER_CORE_PATH . '/fields/company.php';
require_once STARTER_CORE_PATH . '/fields/lead.php';
require_once STARTER_CORE_PATH . '/fields/review.php';
require_once STARTER_CORE_PATH . '/fields/project.php';
require_once STARTER_CORE_PATH . '/fields/faq.php';
require_once STARTER_CORE_PATH . '/fields/service-card.php';

require_once STARTER_CORE_PATH . '/queries.php';
require_once STARTER_CORE_PATH . '/forms.php';
require_once STARTER_CORE_PATH . '/comments.php';
require_once STARTER_CORE_PATH . '/admin-leads.php';
require_once STARTER_CORE_PATH . '/admin-faq.php';
require_once STARTER_CORE_PATH . '/seo.php';
require_once STARTER_CORE_PATH . '/llms-txt.php';

// Seed importer: shared runner for Tools → Starter Seed and `wp starter seed`.
require_once STARTER_CORE_PATH . '/seed/paths.php';
require_once STARTER_CORE_PATH . '/seed/loader.php';
require_once STARTER_CORE_PATH . '/seed/mappers.php';
require_once STARTER_CORE_PATH . '/seed/entities/company.php';
require_once STARTER_CORE_PATH . '/seed/entities/pages.php';
require_once STARTER_CORE_PATH . '/seed/entities/faq.php';
require_once STARTER_CORE_PATH . '/seed/entities/reviews.php';
require_once STARTER_CORE_PATH . '/seed/entities/projects.php';
require_once STARTER_CORE_PATH . '/seed/entities/posts.php';
require_once STARTER_CORE_PATH . '/seed/entities/service-cards.php';
require_once STARTER_CORE_PATH . '/seed/entities/menus.php';
require_once STARTER_CORE_PATH . '/seed/runner.php';
require_once STARTER_CORE_PATH . '/admin-seed.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once STARTER_CORE_PATH . '/cli.php';
}

starter_core_load_modules();

/**
 * Load optional business modules enabled in project.config.json → modules.
 *
 * Extension point: modules/<name>/module.php inside this package. A flag set to true without the
 * file is ignored (the module is not installed in this project).
 */
function starter_core_load_modules(): void {
	$modules = starter_core_config( 'modules' );
	if ( ! is_array( $modules ) ) {
		return;
	}

	foreach ( $modules as $name => $enabled ) {
		if ( true !== $enabled || ! preg_match( '/^[a-z0-9_-]+$/', (string) $name ) ) {
			continue;
		}

		$file = STARTER_CORE_PATH . '/modules/' . $name . '/module.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}

/**
 * Flush rewrite rules once after STARTER_CORE_VERSION changes.
 *
 * MU-plugins have no activation hook, and rewrites registered here (/llms.txt, CPT slugs) need a
 * flush. Runs late on init so every rule is registered.
 * IMPORTANT: bump STARTER_CORE_VERSION (starter-core.php) whenever CPT args (rewrite slug, public,
 * has_archive), rewrite rules or query vars change — otherwise live sites keep stale rules.
 */
function starter_core_maybe_flush_rewrites(): void {
	if ( get_option( 'starter_rewrite_version' ) === STARTER_CORE_VERSION ) {
		return;
	}

	update_option( 'starter_rewrite_version', STARTER_CORE_VERSION, true );
	flush_rewrite_rules( false );
}
add_action( 'init', 'starter_core_maybe_flush_rewrites', 999 );

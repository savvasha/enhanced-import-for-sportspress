<?php
/**
 * Plugin Name: Enhanced Import for SportsPress
 * Description: Extends SportsPress Importers with extra abilities.
 * Version: 1.0
 * Author: Savvas
 * Author URI: https://savvasha.com
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl.html
 *
 * @package EnhancedImportForSportsPress
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
if ( ! defined( 'EIFS_PLUGIN_BASE' ) ) {
	define( 'EIFS_PLUGIN_BASE', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'EIFS_PLUGIN_DIR' ) ) {
	define( 'EIFS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'EIFS_PLUGIN_URL' ) ) {
	define( 'EIFS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Load enhanced importer integration.
require_once EIFS_PLUGIN_DIR . 'includes/class-eifs-plugin.php';


// Bootstrap after all other plugins.
add_action(
	'plugins_loaded',
	static function (): void {
		if ( eifs_is_sportspress_active() ) {
			new EIFS_Plugin();
		}
	},
	PHP_INT_MAX
);

/**
 * Check if SportsPress plugin is active.
 *
 * @return bool
 */
function eifs_is_sportspress_active(): bool {
	// Check for SportsPress main class or function.
	if ( class_exists( 'SportsPress' ) || function_exists( 'SP' ) ) {
		return true;
	}

	// Check if SportsPress plugin is active by looking for its autoloader.
	if ( class_exists( 'SP_Importer' ) ) {
		return true;
	}

	// Check if SportsPress Pro is active.
	if ( class_exists( 'SportsPress_Pro' ) ) {
		return true;
	}

	return false;
}

/**
 * Retrieve a post object by exact title for any post type.
 *
 * This function replaces the deprecated get_page_by_title() call by running a WP_Query
 * that looks for an exact match on post_title within the specified post type(s).
 *
 * @param string          $title       The exact post title to search for.
 * @param string|string[] $post_types  Post type slug (or array of slugs) to search in.
 * @param string|string[] $post_status Post status or array of statuses to include. Default 'publish'.
 * @return WP_Post|null   WP_Post object if found; null otherwise.
 */
function eifs_get_post_by_title( $title, $post_types, $post_status = 'publish' ) {
	// If the input $title came from a source that applied magic‐quotes, reverse it.
	$post_title = wp_unslash( $title );

	// Force $post_types and $post_status into arrays so WP_Query accepts them.
	$post_type_arg   = (array) $post_types;
	$post_status_arg = (array) $post_status;

	// Build query args. We're matching post_title exactly via 'title' (available since WP 4.4).
	$args = array(
		'post_type'              => $post_type_arg,       // Query one or more post types.
		'post_status'            => $post_status_arg,     // Query one or more statuses.
		'posts_per_page'         => 1,                    // Only need one match.
		'no_found_rows'          => true,                 // Skip pagination count for performance.
		'ignore_sticky_posts'    => true,                 // Not relevant, but good practice.
		'update_post_term_cache' => false,                // Skip term cache—unneeded here.
		'update_post_meta_cache' => false,                // Skip meta cache—unneeded here.
		'title'                  => $post_title,          // Exact title match.
		'orderby'                => 'post_date ID',       // Ensure deterministic ordering.
		'order'                  => 'ASC',
	);

	// Execute the query.
	$query = new WP_Query( $args );

	// If a post is found, grab it; otherwise $query->posts will be empty.
	if ( $query->have_posts() ) {
		$post_obj = $query->posts[0];
		wp_reset_postdata(); // Reset global $post if it was changed by WP_Query.
		return $post_obj;
	}

	// No match—reset global state and return null.
	wp_reset_postdata();
	return null;
}

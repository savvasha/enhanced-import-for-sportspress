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
add_action( 'plugins_loaded', static function (): void {
	if ( eifs_is_sportspress_active() ) {
		new EIFS_Plugin();
	}
}, PHP_INT_MAX );

/**
 * Check if SportsPress plugin is active.
 *
 * @return bool
 */
function eifs_is_sportspress_active(): bool {
	// Check for SportsPress main class or function
	if ( class_exists( 'SportsPress' ) || function_exists( 'SP' ) ) {
		return true;
	}

	// Check if SportsPress plugin is active by looking for its autoloader
	if ( class_exists( 'SP_Importer' ) ) {
		return true;
	}

	// Check if SportsPress Pro is active
	if ( class_exists( 'SportsPress_Pro' ) ) {
		return true;
	}

	return false;
}
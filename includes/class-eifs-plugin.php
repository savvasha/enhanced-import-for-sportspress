<?php
/**
 * Core bootstrap for Enhanced Import for SportsPress.
 *
 * @package EnhancedImportForSportsPress
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin coordinator.
 */
class EIFS_Plugin {

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		// Extend importer UI: add score columns and behaviors.
		add_filter( 'sportspress_importers', array( $this, 'eifs_replace_importer_callbacks' ), 99 );
	}

	/**
	 * Replace the built-in importer callbacks so we can extend their functionality.
	 *
	 * Fixtures (CSV): adds score columns and auto-creates `sp_table` and `sp_calendar`.
	 * Players (CSV): adds a Player ID column for update-by-ID and dynamic metric columns.
	 *
	 * @param array $importers Importers map passed through filter.
	 * @return array
	 */
	public function eifs_replace_importer_callbacks( array $importers ): array {
		if ( isset( $importers['sp_fixture_csv'] ) ) {
			$importers['sp_fixture_csv']['callback'] = array( $this, 'eifs_fixtures_importer' );
			$importers['sp_fixture_csv']['name']     = esc_attr__( 'Import Fixtures (CSV) Enhanced', 'enhanced-import-for-sportspress' );
		}
		if ( isset( $importers['sp_player_csv'] ) ) {
			$importers['sp_player_csv']['callback'] = array( $this, 'eifs_players_importer' );
			$importers['sp_player_csv']['name']     = esc_attr__( 'Import Players (CSV) Enhanced', 'enhanced-import-for-sportspress' );
		}
		return $importers;
	}

	/**
	 * Callback used by Tools > Import for Fixtures (CSV).
	 * Loads our extended importer that supports scores and auto-creates related posts.
	 */
	public function eifs_fixtures_importer(): void {
		// Load dependencies.
		$this->load_sportspress_importer_classes();

		if ( ! class_exists( 'SP_Importer' ) ) {
			wp_die( esc_html__( 'SportsPress importer base class is not available. Ensure SportsPress is active.', 'enhanced-import-for-sportspress' ) );
		}

		if ( ! class_exists( 'EIFS_Fixture_Importer' ) ) {
			require_once EIFS_PLUGIN_DIR . 'includes/class-eifs-fixture-importer.php';
		}

		if ( ! class_exists( 'EIFS_Fixture_Importer' ) ) {
			wp_die( esc_html__( 'Enhanced Importer class could not be loaded.', 'enhanced-import-for-sportspress' ) );
		}

		$importer = new EIFS_Fixture_Importer();
		$importer->dispatch();
	}

	/**
	 * Callback used by Tools > Import for Players (CSV).
	 * Loads our extended importer that supports update-by-ID and metric columns.
	 */
	public function eifs_players_importer(): void {
		// Load dependencies.
		$this->load_sportspress_importer_classes();

		if ( ! class_exists( 'SP_Importer' ) ) {
			wp_die( esc_html__( 'SportsPress importer base class is not available. Ensure SportsPress is active.', 'enhanced-import-for-sportspress' ) );
		}

		if ( ! class_exists( 'EIFS_Player_Importer' ) ) {
			require_once EIFS_PLUGIN_DIR . 'includes/class-eifs-player-importer.php';
		}

		if ( ! class_exists( 'EIFS_Player_Importer' ) ) {
			wp_die( esc_html__( 'Enhanced Player Importer class could not be loaded.', 'enhanced-import-for-sportspress' ) );
		}

		$importer = new EIFS_Player_Importer();
		$importer->dispatch();
	}

	/**
	 * Load SportsPress importer classes the same way SportsPress does.
	 */
	private function load_sportspress_importer_classes(): void {
		// Ensure WP Importer API is available.
		if ( ! function_exists( 'wp_import_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/import.php';
		}
		if ( ! class_exists( 'WP_Importer' ) ) {
			$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
			if ( file_exists( $class_wp_importer ) ) {
				require_once $class_wp_importer;
			}
		}

		// Ask SportsPress to load its importer classes without hardcoding paths.
		if ( ! class_exists( 'SP_Importer' ) ) {
			if ( class_exists( 'SP_Admin_Importers' ) && method_exists( 'SP_Admin_Importers', 'includes' ) ) {
				// This mirrors SportsPress' own loading routine.
				\SP_Admin_Importers::includes();
			}
		}
	}
}

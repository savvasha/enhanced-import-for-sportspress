<?php
/**
 * Enhanced Import for SportsPress - Player importer.
 * Based on the original Player importer from SportsPress.
 *
 * @author      Savvas
 * @category    Admin
 * @package     EnhancedImportForSportsPress
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'EIFS_Player_Importer' ) ) {
	/**
	 * Class EIFS_Player_Importer
	 *
	 * Player importer for Enhanced Import for SportsPress. Extends the core
	 * SportsPress importer to support an optional Player ID column (update by ID)
	 * and dynamic columns for every registered metric (`sp_metric`).
	 *
	 * @since 1.0
	 * @package EnhancedImportForSportsPress
	 * @subpackage Importers
	 * @extends SP_Importer
	 */
	class EIFS_Player_Importer extends SP_Importer {

		/**
		 * Map of metric column keys to metric slugs.
		 *
		 * Keys are the importer column keys (e.g. `sp_metric_height`) and values
		 * are the matching `sp_metric` post slugs (e.g. `height`). Used during
		 * import to build the `sp_metrics` meta array.
		 *
		 * @since 1.0
		 * @var array
		 */
		public $metric_slugs = array();

		/**
		 * Constructor.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			$this->import_page  = 'sp_player_csv';
			$this->import_label = esc_attr__( 'Import Players (Enhanced)', 'enhanced-import-for-sportspress' );

			// Player ID is added as the first column so it can be used to update existing players.
			$this->columns = array(
				'sp_player_id'   => esc_attr__( 'Player ID', 'enhanced-import-for-sportspress' ),
				'sp_number'      => esc_attr__( 'Squad Number', 'enhanced-import-for-sportspress' ),
				'post_title'     => esc_attr__( 'Name', 'enhanced-import-for-sportspress' ),
				'sp_position'    => esc_attr__( 'Positions', 'enhanced-import-for-sportspress' ),
				'sp_team'        => esc_attr__( 'Teams', 'enhanced-import-for-sportspress' ),
				'sp_league'      => esc_attr__( 'Leagues', 'enhanced-import-for-sportspress' ),
				'sp_season'      => esc_attr__( 'Seasons', 'enhanced-import-for-sportspress' ),
				'sp_nationality' => esc_attr__( 'Nationality', 'enhanced-import-for-sportspress' ),
				'post_date'      => esc_attr__( 'Date of Birth', 'enhanced-import-for-sportspress' ),
			);

			// Player ID is optional.
			$this->optionals = array( 'sp_player_id' );

			// Append a column for every registered metric.
			$this->append_metric_columns();

			parent::__construct();
		}

		/**
		 * Append a dynamic column for each registered `sp_metric`.
		 *
		 * Each metric column key is prefixed with `sp_metric_` followed by the
		 * metric slug, while the label is the metric title. Metric columns are
		 * always optional.
		 *
		 * @access private
		 * @return void
		 */
		private function append_metric_columns(): void {
			$metrics = get_posts(
				array(
					'post_type'      => 'sp_metric',
					'numberposts'    => -1,
					'posts_per_page' => -1,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
					'post_status'    => 'publish',
				)
			);

			if ( empty( $metrics ) ) {
				return;
			}

			foreach ( $metrics as $metric ) {
				$column_key                        = 'sp_metric_' . $metric->post_name;
				$this->columns[ $column_key ]      = esc_attr( $metric->post_title );
				$this->optionals[]                 = $column_key;
				$this->metric_slugs[ $column_key ] = $metric->post_name;
			}
		}

		/**
		 * Import function.
		 *
		 * @access public
		 * @param array $array Array of data to import.
		 * @param array $columns Array of columns to import.
		 * @return void
		 */
		public function import( $array = array(), $columns = array( 'post_title' ) ): void {

			$this->imported = 0;
			$this->skipped  = 0;

			if ( ! is_array( $array ) || 0 === count( $array ) ) :
				$this->footer();
				die();
			endif;

			$rows = array_chunk( $array, count( $columns ) );

			// Verify nonce.
			if ( ! isset( $_POST['eifs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eifs_nonce'] ) ), 'eifs_player_import' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'enhanced-import-for-sportspress' ) );
			}

			// Get Date of Birth format from post vars.
			$date_format = isset( $_POST['sp_date_format'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_date_format'] ) ) : 'yyyy/mm/dd';

			// Whether to merge duplicates by title when no Player ID is provided.
			$merge = (bool) sp_array_value( $_POST, 'merge', 0 );

			// Terms that should be preserved (appended) when updating an existing player.
			$preservable_metas_keys = array(
				'sp_league',
				'sp_position',
				'sp_season',
			);

			foreach ( $rows as $row ) :

				// Determine if the row is effectively empty while preserving numeric zeros (e.g. "0").
				$non_empty_values = array_filter(
					$row,
					static function ( $value ) {
						return '' !== trim( (string) $value );
					}
				);

				if ( empty( $non_empty_values ) ) {
					continue;
				}

				$meta = array();

				// Initialize preservable metas keys so they can be safely appended to later.
				foreach ( $preservable_metas_keys as $p ) {
					$meta[ $p ] = '';
				}

				foreach ( $columns as $index => $key ) :
					$meta[ $key ] = sp_array_value( $row, $index );
				endforeach;

				$name = sp_array_value( $meta, 'post_title' );
				$date = sp_array_value( $meta, 'post_date' );

				// Format date of birth.
				$date       = str_replace( '/', '-', trim( $date ) );
				$date_array = explode( '-', $date );
				switch ( $date_format ) :
					case 'dd/mm/yyyy':
						$date = substr( str_pad( sp_array_value( $date_array, 2, '0000' ), 4, '0', STR_PAD_LEFT ), 0, 4 ) . '-' .
							substr( str_pad( sp_array_value( $date_array, 1, '00' ), 2, '0', STR_PAD_LEFT ), 0, 2 ) . '-' .
							substr( str_pad( sp_array_value( $date_array, 0, '00' ), 2, '0', STR_PAD_LEFT ), 0, 2 );
						break;
					case 'mm/dd/yyyy':
						$date = substr( str_pad( sp_array_value( $date_array, 2, '0000' ), 4, '0', STR_PAD_LEFT ), 0, 4 ) . '-' .
							substr( str_pad( sp_array_value( $date_array, 0, '00' ), 2, '0', STR_PAD_LEFT ), 0, 2 ) . '-' .
							substr( str_pad( sp_array_value( $date_array, 1, '00' ), 2, '0', STR_PAD_LEFT ), 0, 2 );
						break;
					default:
						$date = substr( str_pad( sp_array_value( $date_array, 0, '0000' ), 4, '0', STR_PAD_LEFT ), 0, 4 ) . '-' .
							substr( str_pad( sp_array_value( $date_array, 1, '00' ), 2, '0', STR_PAD_LEFT ), 0, 2 ) . '-' .
							substr( str_pad( sp_array_value( $date_array, 2, '00' ), 2, '0', STR_PAD_LEFT ), 0, 2 );
				endswitch;

				$id              = false;
				$is_update       = false;
				$resolved_by_id  = false;
				$player_id_input = trim( (string) sp_array_value( $meta, 'sp_player_id' ) );

				// 1. Try to resolve the player by the provided Player ID.
				if ( '' !== $player_id_input && is_numeric( $player_id_input ) ) {
					$candidate = get_post( (int) $player_id_input );
					if ( $candidate && 'sp_player' === $candidate->post_type ) {
						$id             = $candidate->ID;
						$is_update      = true;
						$resolved_by_id = true;

						// Make sure player is published.
						if ( 'publish' !== $candidate->post_status ) {
							wp_update_post(
								array(
									'ID'          => $id,
									'post_status' => 'publish',
								)
							);
						}
					}
				}

				// 2. If not resolved by ID, fall back to the native behavior (merge by title or create).
				if ( ! $id ) {

					if ( ! $name ) :
						$this->skipped++;
						continue;
					endif;

					$player_object = $merge ? eifs_get_post_by_title( stripslashes( $name ), 'sp_player', array( 'publish', 'pending', 'draft', 'future', 'private' ) ) : false;

					if ( $player_object ) :
						if ( 'publish' !== $player_object->post_status ) :
							wp_update_post(
								array(
									'ID'          => $player_object->ID,
									'post_status' => 'publish',
								)
							);
						endif;
						$id        = $player_object->ID;
						$is_update = true;
					else :
						$args = array(
							'post_type'   => 'sp_player',
							'post_status' => 'publish',
							'post_title'  => wp_strip_all_tags( $name ),
						);
						// Check if a DoB was set.
						if ( '0000-00-00' !== $date ) {
							$args['post_date'] = $date;
						}
						$id = wp_insert_post( $args );

						// Flag as import.
						update_post_meta( $id, '_sp_import', 1 );
					endif;
				}

				// When resolved by ID and a name is provided, update the player title.
				if ( $resolved_by_id && '' !== $name ) {
					wp_update_post(
						array(
							'ID'         => $id,
							'post_title' => wp_strip_all_tags( $name ),
						)
					);
				}

				// Handle preservable data on updates.
				if ( $is_update ) {
					foreach ( $preservable_metas_keys as $p ) {
						$terms       = wp_get_object_terms( $id, $p, array( 'fields' => 'names' ) );
						$meta[ $p ] .= '|' . implode( '|', $terms );
					}
				}

				// Update number.
				update_post_meta( $id, 'sp_number', sp_array_value( $meta, 'sp_number' ) );

				// Update positions.
				$positions = explode( '|', sp_array_value( $meta, 'sp_position' ) );
				wp_set_object_terms( $id, $positions, 'sp_position', false );

				// Update leagues.
				$leagues = explode( '|', sp_array_value( $meta, 'sp_league' ) );
				wp_set_object_terms( $id, $leagues, 'sp_league', false );

				// Update seasons.
				$seasons = explode( '|', sp_array_value( $meta, 'sp_season' ) );
				wp_set_object_terms( $id, $seasons, 'sp_season', false );

				// Update teams.
				$teams = (array) explode( '|', sp_array_value( $meta, 'sp_team' ) );
				$i     = 0;
				foreach ( $teams as $team ) :
					if ( '' === trim( (string) $team ) ) {
						continue;
					}

					// Get or insert team.
					$team_object = eifs_get_post_by_title( stripslashes( $team ), 'sp_team', array( 'publish', 'pending', 'draft', 'future', 'private' ) );
					if ( $team_object ) :
						if ( 'publish' !== $team_object->post_status ) :
							wp_update_post(
								array(
									'ID'          => $team_object->ID,
									'post_status' => 'publish',
								)
							);
						endif;
						$team_id = $team_object->ID;
					else :
						$team_id = wp_insert_post(
							array(
								'post_type'   => 'sp_team',
								'post_status' => 'publish',
								'post_title'  => wp_strip_all_tags( $team ),
							)
						);
						// Flag as import.
						update_post_meta( $team_id, '_sp_import', 1 );
						wp_set_object_terms( $team_id, $leagues, 'sp_league', false );
						wp_set_object_terms( $team_id, $seasons, 'sp_season', false );
					endif;

					// Add team to player.
					add_post_meta( $id, 'sp_team', $team_id );

					// Update current team if first in array, otherwise use as past team.
					if ( 0 === $i ) :
						update_post_meta( $id, 'sp_current_team', $team_id );
					else :
						add_post_meta( $id, 'sp_past_team', $team_id );
					endif;

					$i++;
				endforeach;

				// Update nationality.
				$nationality = trim( strtolower( sp_array_value( $meta, 'sp_nationality' ) ) );
				if ( '*' === $nationality ) {
					$nationality = '';
				}
				update_post_meta( $id, 'sp_nationality', $nationality );

				// Update metrics, preserving existing values for metric columns left blank.
				$metrics = (array) get_post_meta( $id, 'sp_metrics', true );
				foreach ( $this->metric_slugs as $column_key => $slug ) {
					$value = sp_array_value( $meta, $column_key, '' );
					if ( '' !== trim( (string) $value ) ) {
						$metrics[ $slug ] = $value;
					}
				}
				update_post_meta( $id, 'sp_metrics', $metrics );

				$this->imported++;

			endforeach;

			// Show Result.
			echo '<div class="updated settings-error below-h2"><p>';
			$import_summary_message = sprintf(
				/* translators: 1: Number of imported players. 2: Number of skipped players. */
				__( 'Import complete - imported <strong>%1$s</strong> players and skipped <strong>%2$s</strong>.', 'enhanced-import-for-sportspress' ),
				esc_html( $this->imported ),
				esc_html( $this->skipped )
			);
			printf( '%s', wp_kses_post( $import_summary_message ) );
			echo '</p></div>';

			$this->import_end();
		}

		/**
		 * Performs post-import cleanup of files and the cache.
		 *
		 * @access public
		 * @return void
		 */
		public function import_end(): void {
			/* translators: 1: Notice text, 2: URL to players list, 3: Link text */
			printf( '<p>%1$s <a href="%2$s">%3$s</a></p>', esc_html__( 'All done!', 'enhanced-import-for-sportspress' ), esc_url( admin_url( 'edit.php?post_type=sp_player' ) ), esc_html__( 'View Players', 'enhanced-import-for-sportspress' ) );

			do_action( 'eifs_import_end' );
		}

		/**
		 * Header function.
		 *
		 * @access public
		 * @return void
		 */
		public function header(): void {
			echo '<div class="wrap"><h2>' . esc_html__( 'Import Players (Enhanced)', 'enhanced-import-for-sportspress' ) . '</h2>';
		}

		/**
		 * Greet function.
		 *
		 * @access public
		 * @return void
		 */
		public function greet(): void {
			echo '<div class="narrow">';
			echo '<p>' . esc_html__( 'Hi there! Choose a .csv file to upload, then click "Upload file and import".', 'enhanced-import-for-sportspress' ) . '</p>';
			// translators: 1: URL to the sample players CSV file.
			echo '<p>' . wp_kses_post( sprintf( __( 'Players can be defined with a leading optional Player ID column followed by the standard columns and any metric columns. <a href="%s">Click here to download a sample</a>.', 'enhanced-import-for-sportspress' ), esc_url( EIFS_PLUGIN_URL . 'dummy-data/players-sample.csv' ) ) ) . '</p>';
			wp_import_upload_form( 'admin.php?import=sp_player_csv&step=1' );
			echo '</div>';
		}

		/**
		 * Options screen.
		 *
		 * @access public
		 * @return void
		 */
		public function options(): void {
			?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row" class="titledesc">
							<?php esc_html_e( 'Date of Birth Format', 'enhanced-import-for-sportspress' ); ?>
						</th>
						<td class="forminp forminp-radio">
							<fieldset>
								<ul>
									<li>
										<label><input name="sp_date_format" value="yyyy/mm/dd" type="radio" checked> yyyy/mm/dd</label>
									</li>
									<li>
										<label><input name="sp_date_format" value="dd/mm/yyyy" type="radio"> dd/mm/yyyy</label>
									</li>
									<li>
										<label><input name="sp_date_format" value="mm/dd/yyyy" type="radio"> mm/dd/yyyy</label>
									</li>
								</ul>
							</fieldset>
						</td>
					</tr>
					<tr>
						<td>
							<label>
								<input type="hidden" name="merge" value="0">
								<input type="checkbox" name="merge" value="1" checked="checked">
								<?php esc_html_e( 'Merge duplicates', 'enhanced-import-for-sportspress' ); ?>
							</label>
							<?php wp_nonce_field( 'eifs_player_import', 'eifs_nonce' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
		}
	}
} else {
	// Display error if required classes are not available.
	if ( ! class_exists( 'WP_Importer' ) ) {
		add_action(
			'admin_notices',
			function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Enhanced Import for SportsPress requires WordPress Importer to be available.', 'enhanced-import-for-sportspress' ) . '</p></div>';
			}
		);
	}
	if ( ! class_exists( 'SP_Importer' ) ) {
		add_action(
			'admin_notices',
			function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Enhanced Import for SportsPress requires SportsPress plugin to be installed and activated.', 'enhanced-import-for-sportspress' ) . '</p></div>';
			}
		);
	}
}

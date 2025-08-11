<?php
/**
 * Enhanced Import for SportsPress - Fixture importer.
 * Based on the original Fixture importer from SportsPress.
 *
 * @author      Savvas
 * @category    Admin
 * @package     EnhancedImportForSportsPress
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'EIFS_Fixture_Importer' ) ) {
	/**
	 * Class EIFS_Fixture_Importer
	 *
	 * Fixture importer for Enhanced Import for SportsPress. Extends the core
	 * SportsPress importer to support additional columns and automatic creation
	 * of calendars and league tables after import.
	 *
	 * @since 1.0
	 * @package EnhancedImportForSportsPress
	 * @subpackage Importers
	 * @extends SP_Importer
	 */
	class EIFS_Fixture_Importer extends SP_Importer {

		/**
		 * Array to store team IDs during import process.
		 *
		 * This property accumulates team IDs as they are processed during the import,
		 * allowing for tracking and potential post-processing of imported teams.
		 *
		 * @since 1.0
		 * @var array
		 */
		public $teams_ids = array();

		/**
		 * Constructor.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			$this->import_page  = 'sp_fixture_csv';
			$this->import_label = esc_attr__( 'Import Fixtures (Enhanced)', 'sportspress' );
			$this->columns      = array(
				'post_date'     => esc_attr__( 'Date', 'sportspress' ),
				'post_time'     => esc_attr__( 'Time', 'sportspress' ),
				'sp_venue'      => esc_attr__( 'Venue', 'sportspress' ),
				'sp_home'       => esc_attr__( 'Home', 'sportspress' ),
				'sp_away'       => esc_attr__( 'Away', 'sportspress' ),
				'sp_home_score' => esc_attr__( 'Home Score', 'enhanced-import-for-sportspress' ),
				'sp_away_score' => esc_attr__( 'Away Score', 'enhanced-import-for-sportspress' ),
				'sp_day'        => esc_attr__( 'Match Day', 'sportspress' ),
			);
			$this->optionals    = array( 'sp_home_score', 'sp_away_score', 'sp_day' );
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

			// Verify nonce and get event format, league, and season from post vars with proper sanitization.
			if ( ! isset( $_POST['eifs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eifs_nonce'] ) ), 'eifs_import' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'enhanced-import-for-sportspress' ) );
			}

			$event_format = isset( $_POST['sp_format'] ) ? sanitize_key( wp_unslash( $_POST['sp_format'] ) ) : false;
			$league_input = isset( $_POST['sp_league'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_league'] ) ) : '-1';
			$league       = ( '-1' === $league_input ) ? false : $league_input;
			$season_input = isset( $_POST['sp_season'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_season'] ) ) : '-1';
			$season       = ( '-1' === $season_input ) ? false : $season_input;
			$date_format  = isset( $_POST['sp_date_format'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_date_format'] ) ) : 'yyyy/mm/dd';

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

				foreach ( $columns as $index => $key ) :
					$meta[ $key ] = sp_array_value( $row, $index );
				endforeach;

				// Get event details.
				$event = array(
					sp_array_value( $meta, 'post_date' ),
					sp_array_value( $meta, 'post_time' ),
					sp_array_value( $meta, 'sp_venue' ),
					sp_array_value( $meta, 'sp_day' ),
					sp_array_value( $meta, 'sp_home_score' ),
					sp_array_value( $meta, 'sp_away_score' ),
				);

				$teams = array(
					sp_array_value( $meta, 'sp_home' ),
					sp_array_value( $meta, 'sp_away' ),
				);

				// Add new event if date is given.
				if ( count( $event ) > 0 && ! empty( $event[0] ) ) :

					// List event columns.
					list( $date, $time, $venue, $day, $home_score, $away_score ) = $event;

					// Format date.
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

					// Add time to date if given.
					if ( ! empty( $time ) ) :
						$date .= ' ' . trim( $time );
					endif;

					// Define post type args.
					$args = array(
						'post_type'   => 'sp_event',
						'post_status' => 'publish',
						'post_date'   => $date,
						'post_title'  => esc_attr__( 'Event', 'sportspress' ),
					);

					// Insert event.
					$id = wp_insert_post( $args );

					// Flag as import.
					update_post_meta( $id, '_sp_import', 1 );

					// Update event format.
					if ( $event_format ) :
						update_post_meta( $id, 'sp_format', $event_format );
					endif;

					// Update league.
					if ( $league ) :
						wp_set_object_terms( $id, $league, 'sp_league', false );
					endif;

					// Update season.
					if ( $season ) :
						wp_set_object_terms( $id, $season, 'sp_season', false );
					endif;

					// Update venue.
					if ( '' === $venue ) {
						$team        = reset( $teams );
						$team_object = eifs_get_post_by_title( stripslashes( $team ), 'sp_team' );
						$venue       = sp_get_the_term_id( $team_object->ID, 'sp_venue' );
					}
					wp_set_object_terms( $id, $venue, 'sp_venue', false );

					// Update match day.
					if ( '' !== $day ) {
						update_post_meta( $id, 'sp_day', $day );
					}

					// Increment.
					$this->imported ++;

				endif;

				// Add teams to event.
				if ( count( $teams ) > 0 ) :

					foreach ( $teams as $team_name ) :

						if ( '' !== $team_name ) :

							// Find out if team exists.
							$team_object = eifs_get_post_by_title( stripslashes( $team_name ), 'sp_team' );

							// Get or insert team.
							if ( $team_object ) :

								// Make sure team is published.
								if ( 'publish' !== $team_object->post_status ) :
									wp_update_post(
										array(
											'ID'          => $team_object->ID,
											'post_status' => 'publish',
										)
									);
								endif;

								// Get team ID.
								$team_id = $team_object->ID;

							else :

								// Insert team.
								$team_id = wp_insert_post(
									array(
										'post_type'   => 'sp_team',
										'post_status' => 'publish',
										'post_title'  => wp_strip_all_tags( $team_name ),
									)
								);

								// Flag as import.
								update_post_meta( $team_id, '_sp_import', 1 );

							endif;

							// Update league.
							if ( $league ) :
								wp_set_object_terms( $team_id, $league, 'sp_league', true );
							endif;

							// Update season.
							if ( $season ) :
								wp_set_object_terms( $team_id, $season, 'sp_season', true );
							endif;

							// Add to event if exists.
							if ( isset( $id ) ) :

								// Add team to event.
								add_post_meta( $id, 'sp_team', $team_id );

								// Get event name.
								$title = get_the_title( $id );

								// Initialize event name.
								if ( esc_attr__( 'Event', 'sportspress' ) === $title ) {
									$title = '';
								} else {
									$title .= ' ' . get_option( 'sportspress_event_teams_delimiter', 'vs' ) . ' ';
								}

								// Append team name to event name.
								$title .= $team_name;

								// Update event with new name.
								$post = array(
									'ID'         => $id,
									'post_title' => $title,
									'post_name'  => $id,
								);
								wp_update_post( $post );

							endif;
							// Create teams array.
							$this->teams_ids[] = $team_id;

						else :

							// Add empty team to event.
							add_post_meta( $id, 'sp_team', -1 );

						endif;

					endforeach;

				endif;
				// If both sp_home_score and sp_away_score are defined, update main results for the event.
				if ( isset( $home_score ) && isset( $away_score ) && isset( $id ) ) {
					$teams = get_post_meta( $id, 'sp_team', false );
					if ( is_array( $teams ) && count( $teams ) >= 2 ) {
						$results              = array();
						$results[ $teams[0] ] = $home_score;
						$results[ $teams[1] ] = $away_score;
						sp_update_main_results( $id, $results );
					}
				}
			endforeach;

			/* Start League Table and Calendar logic. */

			// Remove duplicates from teams_ids array.
			$teams_clean = array_unique( $this->teams_ids );

			// Get league and season objects by slug.
			$league_object = get_term_by( 'slug', $league, 'sp_league' );
			$season_object = get_term_by( 'slug', $season, 'sp_season' );

			// Get league and season IDs.
			$league_id = $league_object->term_id;
			$season_id = $season_object->term_id;

			if ( isset( $_POST['eifs_auto_create_calendar'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['eifs_auto_create_calendar'] ) ) ) {
				// Check if a calendar exists for the league and season.
				$args      = array(
					'post_type'      => array( 'sp_calendar' ),
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'orderby'        => 'post_date ID',
					'order'          => 'ASC',
					'tax_query'      => array(
						'relation' => 'AND',
						array(
							'taxonomy' => 'sp_league',
							'field'    => 'term_id',
							'terms'    => $league_id,
						),
						array(
							'taxonomy' => 'sp_season',
							'field'    => 'term_id',
							'terms'    => $season_id,
						),
					),
				);
				$calendars = new WP_Query( $args );
				if ( ! empty( $calendars->post ) ) {
					$calendar_id = $calendars->ID;
				} else {
					// Create new calendar.
					$calendar_id = wp_insert_post(
						array(
							'post_type'   => 'sp_calendar',
							'post_status' => 'publish',
							'post_title'  => wp_strip_all_tags( $league_object->name . ' ' . $season_object->name ), // Add league and season name to calendar title.
						)
					);
					// Set league and season terms to the new calendar.
					wp_set_object_terms( $calendar_id, $league, 'sp_league', true );
					wp_set_object_terms( $calendar_id, $season, 'sp_season', true );

					// Set calendar format to list.
					update_post_meta( $calendar_id, 'sp_format', 'list' );
				}
			}
			if ( isset( $_POST['eifs_auto_create_league_table'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['eifs_auto_create_league_table'] ) ) ) {
				// Check if a table exists for the league and season.
				$args   = array(
					'post_type'      => array( 'sp_table' ),
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'orderby'        => 'post_date ID',
					'order'          => 'ASC',
					'tax_query'      => array(
						'relation' => 'AND',
						array(
							'taxonomy' => 'sp_league',
							'field'    => 'term_id',
							'terms'    => $league_id,
						),
						array(
							'taxonomy' => 'sp_season',
							'field'    => 'term_id',
							'terms'    => $season_id,
						),
					),
				);
				$tables = new WP_Query( $args );
				if ( ! empty( $tables->post ) ) {
					$table_id = $tables->ID;
				} else {
					$table_id = wp_insert_post(
						array(
							'post_type'   => 'sp_table',
							'post_status' => 'publish',
							'post_title'  => wp_strip_all_tags( $league_object->name . ' ' . $season_object->name ), // Add league and season name to calendar title.
						)
					);
					// Set league and season terms to the new table.
					wp_set_object_terms( $table_id, $league, 'sp_league', true );
					wp_set_object_terms( $table_id, $season, 'sp_season', true );
				}
				// Add teams to table.
				foreach ( $teams_clean as $team_clean_id ) {
					add_post_meta( $table_id, 'sp_team', $team_clean_id );
				}
				// Set table mode to manual.
				update_post_meta( $table_id, 'sp_select', 'manual' );
				// Add by default all columns to the table.
				$columns_args      = array(
					'post_type'      => 'sp_column',
					'numberposts'    => -1,
					'posts_per_page' => -1,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
					'status'         => 'publish',
				);
				$columns           = new WP_Query( $columns_args );
				$sp_import_columns = array();
				if ( $columns->have_posts() ) {
					while ( $columns->have_posts() ) {
						$columns->the_post();
						$sp_import_columns[] = get_post()->post_name;
					}
					wp_reset_postdata();
				}
				update_post_meta( $table_id, 'sp_columns', $sp_import_columns );
			}
			/* End League Table and Calendar logic */

			// Show Result.
			echo '<div class="updated settings-error below-h2"><p>';
			printf(
				/* translators: 1: Number of imported events, 2: Number of skipped events */
				'%s',
				wp_kses_post( sprintf( __( 'Import complete - imported <strong>%1$s</strong> events and skipped <strong>%2$s</strong>.', 'sportspress' ), esc_html( $this->imported ), esc_html( $this->skipped ) ) )
			);
			echo '</p></div>';

			// Show if a table and/or a calendar were created, with links, and how many teams assigned to the new table.
			// Table message.
			if ( isset( $table_id ) && ! empty( $table_id ) ) {
				$table_edit_link = get_edit_post_link( $table_id );
				$table_title     = get_the_title( $table_id );
				$teams_count     = isset( $teams_clean ) && is_array( $teams_clean ) ? count( $teams_clean ) : 0;

				echo '<div class="notice notice-success is-dismissible"><p>';
				printf(
					/* translators: 1: Table title, 2: Edit link, 3: Number of teams */
					wp_kses_post( __( 'Table <strong>%1$s</strong> was created. <a href="%2$s">Edit Table</a>. <br />Assigned <strong>%3$d</strong> teams to the table.', 'sportspress' ) ),
					esc_html( $table_title ),
					esc_url( $table_edit_link ),
					intval( $teams_count )
				);
				echo '</p></div>';
			}

			// Calendar message.
			if ( isset( $calendar_id ) && ! empty( $calendar_id ) ) {
				$calendar_edit_link = get_edit_post_link( $calendar_id );
				$calendar_title     = get_the_title( $calendar_id );

				echo '<div class="notice notice-success is-dismissible"><p>';
				printf(
					/* translators: 1: Calendar title, 2: Edit link */
					wp_kses_post( __( 'Calendar <strong>%1$s</strong> was created. <a href="%2$s">Edit Calendar</a>.', 'sportspress' ) ),
					esc_html( $calendar_title ),
					esc_url( $calendar_edit_link )
				);
				echo '</p></div>';
			}

			$this->import_end();
		}

		/**
		 * Performs post-import cleanup of files and the cache
		 */
		public function import_end(): void {
			/* translators: 1: Notice text, 2: URL to fixtures list, 3: Link text */
			printf( '<p>%1$s <a href="%2$s">%3$s</a></p>', esc_html__( 'All done!', 'sportspress' ), esc_url( admin_url( 'edit.php?post_type=sp_event' ) ), esc_html__( 'View Fixtures', 'sportspress' ) );

			do_action( 'import_end' );
		}

		/**
		 * Greet function.
		 *
		 * @access public
		 * @return void
		 */
		public function greet(): void {
			echo '<div class="narrow">';
			echo '<p>' . esc_html__( 'Hi there! Choose a .csv file to upload, then click "Upload file and import".', 'sportspress' ) . '</p>';
			echo '<p>' . wp_kses_post( sprintf( __( 'Fixtures need to be defined with columns in a specific order (4+ columns). <a href="%s">Click here to download a sample</a>.', 'sportspress' ), esc_url( EIFS_PLUGIN_URL . 'dummy-data/fixtures-sample.csv' ) ) ) . '</p>';
			echo '<p>' . wp_kses_post( sprintf( __( 'Supports CSV files generated by <a href="%s">LeagueLobster</a>.', 'sportspress' ), 'https://tboy.co/leaguelobster' ) ) . '</p>';
			wp_import_upload_form( 'admin.php?import=sp_fixture_csv&step=1' );
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
						<th scope="row"><label><?php esc_html_e( 'Format', 'sportspress' ); ?></label><br/></th>
						<td class="forminp forminp-radio" id="sp_formatdiv">
							<fieldset id="post-formats-select">
								<ul>
									<li><input type="radio" name="sp_format" class="post-format" id="post-format-league" value="league" checked="checked"> <label for="post-format-league" class="post-format-icon post-format-league"><?php esc_html_e( 'Competitive', 'sportspress' ); ?></label></li>
									<li><input type="radio" name="sp_format" class="post-format" id="post-format-friendly" value="friendly"> <label for="post-format-friendly" class="post-format-icon post-format-friendly"><?php esc_html_e( 'Friendly', 'sportspress' ); ?></label></li>
								<br>
						</fieldset>
					</td>
					</tr>
					<tr>
						<th scope="row"><label><?php esc_html_e( 'League', 'sportspress' ); ?></label><br/></th>
						<td>
						<?php
						$args = array(
							'taxonomy'         => 'sp_league',
							'name'             => 'sp_league',
							'values'           => 'slug',
							'show_option_none' => esc_attr__( '&mdash; Not set &mdash;', 'sportspress' ),
						);
						if ( ! sp_dropdown_taxonomies( $args ) ) :
							echo '<p>' . esc_html__( 'None', 'sportspress' ) . '</p>';
							sp_taxonomy_adder( 'sp_league', 'sp_team', esc_attr__( 'Add New', 'sportspress' ) );
						endif;
						?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label><?php esc_html_e( 'Season', 'sportspress' ); ?></label><br/></th>
						<td>
						<?php
						$args = array(
							'taxonomy'         => 'sp_season',
							'name'             => 'sp_season',
							'values'           => 'slug',
							'show_option_none' => esc_attr__( '&mdash; Not set &mdash;', 'sportspress' ),
						);
						if ( ! sp_dropdown_taxonomies( $args ) ) :
							echo '<p>' . esc_html__( 'None', 'sportspress' ) . '</p>';
							sp_taxonomy_adder( 'sp_season', 'sp_team', esc_attr__( 'Add New', 'sportspress' ) );
						endif;
						?>
						</td>
					</tr>
					<tr>
						<th scope="row" class="titledesc">
							<?php esc_html_e( 'Date Format', 'sportspress' ); ?>
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
						<th scope="row" class="titledesc">
							<?php esc_html_e( 'Auto create League Table', 'enhanced-import-for-sportspress' ); ?>
						</th>
						<td class="forminp forminp-radio">
							<fieldset>
								<ul>
									<li>
										<label><input name="eifs_auto_create_league_table" value="yes" type="radio"> Yes</label>
									</li>
									<li>
										<label><input name="eifs_auto_create_league_table" value="no" type="radio" checked> No</label>
									</li>
								</ul>
						</fieldset>
					</td>
					</tr>
					<tr>
						<th scope="row" class="titledesc">
							<?php esc_html_e( 'Auto create Calendar', 'enhanced-import-for-sportspress' ); ?>
						</th>
						<td class="forminp forminp-radio">
							<fieldset>
								<ul>
									<li>
										<label><input name="eifs_auto_create_calendar" value="yes" type="radio"> Yes</label>
									</li>
									<li>
										<label><input name="eifs_auto_create_calendar" value="no" type="radio" checked> No</label>
									</li>
								</ul>
						</fieldset>
						<?php wp_nonce_field( 'eifs_import', 'eifs_nonce' ); ?>
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

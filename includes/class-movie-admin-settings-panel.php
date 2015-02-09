<?php
if ( !class_exists( 'Movies_Admin_Settings_Panel' ) ) {
	class Movies_Admin_Settings_Panel {
		
		function __construct() {
		}
	
		/**
		 * This function is called by WordPress when our Settings menu within the custom post type is 
		 * loaded. Here we register a custom stylesheet to effect some screen elements.
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function on_load_movies_admin_settings_panel() {
			global $movies;
		
			wp_register_style( 'movies-xml-admin-css', $movies->plugin_url .'css/movies-xml-admin.css' , array(), $movies->version );
			wp_enqueue_style( 'movies-xml-admin-css' ); 
					
			// We load the 'theatres' feed object to drive the Theatre select shown on the admin setttings panel.
			$movies->movies_xml_processor->load_feed_objects( false, 'theatres' );
		}
	
		/**
		 * This function is called to show the Setting page we registered in the admin_menu() 
		 * function. This function handles all the HTML settings field output.
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function admin_menu_settings() {
			global $movies;
		
			?>
			<div class="wrap wrap-movies">
				<h2><?php _ex('Movies XML Settings', 'Page Title', 'movies'); ?></h2>

				<?php $this->process_settings_actions(); ?>

				<div class="wrap-movies-settings-content">
					<form id="movies-settings-update" method="post" action="<?php echo admin_url('edit.php?post_type=movie&page=movies.php') ?>">
						<input type="hidden" id="movies-settings-action" name="movies-settings-action" value="settings-update" />
						<?php wp_nonce_field( 'movies-settings-update', 'movies-settings-update-wpnonce' ); ?>
						<p><?php _ex('Please select the Theatre to be used for this website. The Theatre selection controls the data processed.', 'movies'); ?></p>					
						<table class="form-table form-table-movies-settings">					
						<tr class="form-field">
							<th scope="row">
								<label for="movies-settings-theatre-id"><?php _e('Select Theatre', 'movies'); ?></label>
							</th>
							<td>
								<select id="movies-settings-theatre-id" name="movies_settings[theatre-id]">
									<option value=""><?php _e('Select Theatre', 'movies')?></option>
									<?php 
										$theatres_object = $movies->movies_xml_processor->get_feeds_object('theatres');
										if ( is_object( $theatres_object ) ) {
											echo $theatres_object->get_theatres_select_options( $movies->settings['theatre-id'] ); 
										}
									?>
								</select>
							</td>
						</tr>
						</table>

						<table class="form-table form-table-movies-settings">					
						<tr class="form-field">
							<th scope="row">
								<label for="movies-settings-cache-timeout"><?php _e('Feed cache limit', 'movies'); ?></label>
							</th>
							<td>
								<input id="movies-settings-cache-timeout" name="movies_settings[cache-timeout]" value="<?php echo intval($movies->settings['cache-timeout']) / 3600 ?>" /><br /><span class="description"><?php _e('Default is 2. Minimum. 1, Maximum 24.', 'movies') ?>
							</td>
						</tr>
						</table>

						<input type="submit" class="button-primary" value="<?php _e('Save', 'movies'); ?>" />
					</form>
			
				</div><br />
				
				
				<div class="wrap-movies-feed_urls-content">
		
					<h2><?php _ex('Movies XML Feed Processing', 'Page Title', 'movies'); ?></h2>
					<p><?php _e('The following listing shows the XML Feed URLs and the last time it was updated. You can force a manual reprocess of the feeds by clicking the button below. This reprocessing will perform both the retreival of the external XML data as well as loading it into the custom post types.', 'movies') ?></p>
					
					<?php
						$current_timestamp = time();
						$next_timestamp = wp_next_scheduled( 'movies_cron_hook' );
						$diff_timestamp = $next_timestamp - $current_timestamp;
						//echo "next_timestamp[". $next_timestamp ."] current_timestamp[". $current_timestamp ."] diff[". $diff_timestamp ."]<br />";
						if (!$next_timestamp) {
							echo "No Cron scheduled<br />";
						} else {
							$next_timestamp_display = date_i18n( 'Y-m-d H:i:s', $next_timestamp + get_option( 'gmt_offset' ) * 3600, false );
							$current_timestamp_display = date_i18n( 'Y-m-d H:i:s', $current_timestamp + get_option( 'gmt_offset' ) * 3600, false );
							$diff_timestamp_display = human_time_diff($next_timestamp, $current_timestamp);
							//echo "Current Time: ". $current_timestamp_display ."  Next Cron scheduled: ". $next_timestamp_display ." roughly ". $diff_timestamp_display ."<br />";
							echo "Next Cron scheduled: ". $next_timestamp_display ." (<strong>roughly ". $diff_timestamp_display ."</strong>)<br />";
						}
					?>
					
					
					
					<?php $this->process_feed_urls_actions(); ?>
			
					<form id="movies-settings-update" method="post" action="<?php echo admin_url('edit.php?post_type=movie&page=movies.php') ?>">
						<input type="hidden" id="movies-feed-urls-action" name="movies-feed-urls-action" value="feed-urls-update" />
						<?php wp_nonce_field( 'movies-feed-urls-update', 'movies-feed-urls-update-wpnonce' ); ?>
						<table class="form-table form-table-movies-feed-urls">
						<tr>
							<?php /* ?><th class="column-action"><?php _e('Action', 'movies'); ?></th><?php */ ?>
							<th class="column-url"><?php _e('URL', 'movies'); ?></th>
							<th class="column-date"><?php _e('Last update', 'movies'); ?></th>
						<tr>
						<?php
							//foreach( $movies->movies_xml_processor->feeds as $feed_key => $feed ) {
							foreach( $movies->movies_xml_processor->get_feeds_keys() as $feed_key ) {
								$feed = $movies->movies_xml_processor->get_feeds_object( $feed_key );
								if (is_object( $feed ) ) {
									?>
									<tr class="form-field">
<?php /* ?>
										<th scope="row"  class="column-action">
											<input  type="checkbox" name="movies_feed_urls[]" title="<?php _e('check to manually process feed', 'movies'); ?>" value="<?php echo $feed_key ?>" />
										</th>
<?php */ ?>
										<td  class="column-url">
											<?php echo $feed->feed_url ?>
										</td>
										<td  class="column-date">
											<?php
											$feed_object = 
												$error_file_time = $feed->get_feed_xml_error_file_time();
												if ( empty( $error_file_time ) ) {
													echo $feed->get_feed_xml_cache_file_time();	
												} else {
													$error_file_message = $feed->get_last_feed_data_error_message();
													echo '<br /><span class="error">'. __('Last Error', 'movies') .': '. $error_file_time .'<br />'. $error_file_message .'</span>';									
												}
											?>
										</td>
									</tr>
									<?php
								}
							}
						?>
					</table>
					<p><label for="movies-feeds-purge" style="color: red; font-weight: bold"><?php _e('Remove existing Movies before processing Feeds? Warning this will remove ALL Movie data including any edits or custom data entered manually.', 'movies') ?><br />
						<select name="movies-feeds-purge" id="movies-feeds-purge">
							<option value="no"><?php _e('No - Update existing Movies', 'movies') ?></option>
							<option value="yes"><?php _e('Yes - Remove existing Movies', 'movies') ?></option>
						</select><br />
					</p>
					<input type="submit" class="button-primary" value="<?php _e('Process All Feeds', 'movies'); ?>" />
				</div>
			</div>
			<?php
		}

		/**
		 * This function is called from admin_menu_settings() and handles processing the settings form updates
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function process_settings_actions() {
			global $movies;
			
			if ( ( isset( $_POST['movies-settings-action'] ) ) 
			  && ( $_POST['movies-settings-action'] == 'settings-update' ) ) {
		
				if ( !wp_verify_nonce( $_POST['movies-settings-update-wpnonce'], 'movies-settings-update' ) )  {
					return;
				}
		
				$current_theatre_id = $movies->settings['theatre-id'];
		
				if ( ( isset( $_POST['movies_settings'] ) ) && ( !empty( $_POST['movies_settings'] ) ) ) {

					foreach( $_POST['movies_settings'] as $settings_key => $settings_value ) {
						switch( $settings_key ) {
				
							case 'theatre-id':
								$theatre_id = intval( $settings_value );
								if ( !empty( $theatre_id ) ) {
									$movies->settings['theatre-id'] = $theatre_id;
								}
								break;
					
							case 'cache-timeout':
								if ( intval( $_POST['movies_settings']['cache-timeout'] ) < 1 ) {
									$movies->settings['cache-timeout'] = 1 * 3600;
								} else if ( intval( $_POST['movies_settings']['cache-timeout']) > 24 ) {
									$movies->settings['cache-timeout'] = 24 * 3600;
								} else {
									$movies->settings['cache-timeout'] = intval( $_POST['movies_settings']['cache-timeout'] ) * 3600;
								}
								break;
				
						}
					}
					
					$movies->save_plugin_options();
		
					?><div id="movies-settings-saved" class="updated below-h2"><p><?php 
								_e('Settings Saved', 'movies'); ?></p></div><?php

					if ( $current_theatre_id !== $movies->settings['theatre-id'] ) {

						$movies->movies_xml_processor->process_all_feeds( true, true );
						
						?><div id="movies-feed-urls-processed" class="updated below-h2"><p><?php _e('XML Feeds Processed - Due to Theatre ID change.', 'movies'); ?></p></div><?php
						
					}

				}
			}
		}

		/**
		 * This function is called from admin_menu_settings() and handles processing the Feed URL form updates
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function process_feed_urls_actions() {
			global $movies;
			
			if ( ( isset( $_POST['movies-feed-urls-action'] ) ) && ( $_POST['movies-feed-urls-action'] == 'feed-urls-update' ) ) {
					
				if ( wp_verify_nonce( $_POST['movies-feed-urls-update-wpnonce'], 'movies-feed-urls-update' ) )  {
					
					if ( (isset( $_POST['movies-feeds-purge'] ) ) && ( $_POST['movies-feeds-purge'] == 'yes' ) ) {
						$movies_feeds_purge = true;
					} else {
						$movies_feeds_purge = false;
					}
					
					$movies->movies_xml_processor->process_all_feeds( true, $movies_feeds_purge );
						
					?><div id="movies-feed-urls-processed" class="updated below-h2"><p><?php _e('XML Feeds Processed', 'movies'); ?></p></div><?php
				}
			}
		}
	}	
}

<?php
/*
Plugin Name: Movies XML Processing
Plugin URI: 
Description: This plugin retreives movies information from various external URLs. The data is processed and assigned to a custom post type 'movie'. This plugin is written custom for Envision.
Author: Paul Menard
Version: 1.0M
Author URI: http://www.codehooligans.com
License: 
Text Domain: movies
Domain Path: /languages

*/

include ( dirname( __FILE__ ) . "/includes/class-movie-xml-parser-processor.php");
include ( dirname( __FILE__ ) . "/includes/movies-template-functions.php");

if ( !class_exists( 'Movies' ) ) {
	class Movies {
		
		var $version	= '1.0';

		// Contains the reference path to the plugin root directory. Used when other included plugin files 
		// need to include files relative to the plugin root.
		var $plugin_dir;
		
		// Contains the reference url to the plugin root directory. Used when other included plugin files 
		// need to refernece CSS/JS via URL
		var $plugin_url; 
		
		var $pagehooks	= array();
		var $settings 	= array();
		
		// This will be our reference to the XML feed and processed data. 
		var $movies_xml_processor; 
		
		// This var will be used to reference the admin panel class where the settings form is shown and processed.
		var $movies_admin_settings_panel;
		
		function __construct() {
			
			$this->plugin_dir = trailingslashit( plugin_dir_path( __FILE__ ) );
			$this->plugin_url = trailingslashit( plugin_dir_url( __FILE__ ) );

			add_action( 'init', 				array( $this, 'init') );
			add_action( 'admin_menu', 			array( $this, 'admin_menu') );
			
			add_filter( 'cron_schedules', 		array( $this, 'movies_add_cron_schdules_proc' ), 99);
			add_action( 'movies_cron_hook', 	array( $this, 'movies_cron_proc' ) );
			
		}
	
		/**
		 * init our plugin settings and functioal data in order to get processing started. Register our 'movie' custom post type
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function init() {			
			$this->load_plugin_settings();
			$this->register_post_type();
			$this->seed_cron_proc();
		}
	
	
 		/**
 		 * This function call via the 'init' action hook. Here we load the plugin settings from the options table.
 		 *
 		 * @since 1.0.0
 		 * @param none
 		 * @return none
 		 */				
		function load_plugin_settings() {

			$this->load_plugin_options();
		
			// Regardless of the settings value we calculate this every time!
			// When the rmeote XML data is retreived we store a local cached version. This helps prevent polling 
			// the remote server too often.
			$wp_upload_dir = wp_upload_dir();
			$wp_upload_dir['basedir'] = str_replace( '\\', '/', $wp_upload_dir['basedir'] );
			$this->settings['cache-dir'] = trailingslashit( $wp_upload_dir['basedir'] ) . 'movies-xml-data'; 
			wp_mkdir_p( $this->settings['cache-dir'] );
		
			// Check our cache-timeout value. Ensure the value is not less than 1 hour or more than 24 hours. Assign a default if needed.
			if ( ( !isset( $this->settings['cache-timeout'] ) ) 
			  || ( empty( $this->settings['cache-timeout'] ) )
			  || ( intval( $this->settings['cache-timeout'] ) < 1 )
			  || ( intval( $this->settings['cache-timeout'] ) > 24 ) ) {
		 		$this->settings['cache-timeout'] = 60*60*2; // 2 hourse 7200 seconds
			 }
		 }

		function load_plugin_options() {
			// Load our Settings from WordPress options table
			$this->settings = get_option( 'movies-xml-settings' );
		}
		
		function save_plugin_options() {
			update_option( 'movies-xml-settings', $this->settings);
		}
		 

 		/**
 		 * This function call via the 'init' action hook. Here we register the 'Movie' custom post type
 		 *
 		 * @since 1.0.0
 		 * @param none
 		 * @return none
 		 */				
		function register_post_type() {
			
			// Now register the Moview custom post type.
			$labels = array(
				'name'               	=> 	_x( 'Movies', 'post type general name', 'movies' ),
				'singular_name'      	=> 	_x( 'Movie', 'post type singular name', 'movies' ),
				'menu_name'          	=> 	_x( 'Movies', 'admin menu', 'movies' ),
				'name_admin_bar'     	=> 	_x( 'Movie', 'add new on admin bar', 'movies' ),
				'add_new'            	=> 	_x( 'Add New', 'movie', 'movies' ),
				'add_new_item'       	=> 	__( 'Add New Movie', 'movies' ),
				'new_item'           	=> 	__( 'New Movie', 'movies' ),
				'edit_item'          	=> 	__( 'Edit Movie', 'movies' ),
				'view_item'          	=> 	__( 'View Movie', 'movies' ),
				'all_items'          	=> 	__( 'All Movies', 'movies' ),
				'search_items'       	=> 	__( 'Search Movies', 'movies' ),
				'parent_item_colon'  	=> 	__( 'Parent Movies:', 'movies' ),
				'not_found'          	=> 	__( 'No Movies found.', 'movies' ),
				'not_found_in_trash' 	=> 	__( 'No Movies found in Trash.', 'movies' )
			);

			$args = array(
				'labels'             	=> 	$labels,
				'public'             	=> 	true,
				'publicly_queryable' 	=> 	true,
				'show_ui'            	=> 	true,
				'show_in_menu'			=> 	true,
				'show_in_nav_menus'		=>	false,
				'query_var'          	=> 	true,
				'rewrite'            	=> 	array( 'slug' => 'movie' ),
				'capability_type'    	=> 	'page',
				'has_archive'        	=> 	true,
				'hierarchical'       	=> 	false,
				'menu_position'      	=> 	null,
				'supports'           	=> 	array( 'title', 'editor', 'thumbnail', 'custom-fields' )
			);
			// The custom post type is now registered via the theme
			//register_post_type( 'movie', $args );
			
			if ( !is_object($this->movies_xml_processor ) ) {
				$this->movies_xml_processor = new Movies_XML_Parser_Processor;
			}
			
		}
	
		/**
		 * This function call via the 'admin_menu' action hook. Here we setup out custom Settings page and 
		 * attach it to the menu from the custom post type we registered in the init function.
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function admin_menu() {
			
			include ( dirname( __FILE__ ) . "/includes/class-movie-admin-settings-panel.php");
			$this->movies_admin_settings_panel = new Movies_Admin_Settings_Panel;
						
			$this->pagehooks['movies_admin_settings'] = add_submenu_page (
				'edit.php?post_type=movie', 
				'Settings', 
				'Settings', 
				'edit_posts', 
				basename( __FILE__ ), 
				array( $this->movies_admin_settings_panel, 'admin_menu_settings' ) );
			
			add_action( 'load-'. $this->pagehooks['movies_admin_settings'], 
				array( $this->movies_admin_settings_panel, 'on_load_movies_admin_settings_panel') );
		}


		function movies_add_cron_schdules_proc( $cron_schedules ) {
			if ( !isset( $cron_schedules['movies-custom'] ) ) {
			    $cron_schedules['movies-custom'] = array(
			        'interval' => intval($this->settings['cache-timeout']), //60*5, 
			        'display'  => __( 'Movies Custom', 'movies' ),
			    );		
			}
			return $cron_schedules;			
		}
		
		function seed_cron_proc() {
			$next_timestamp = wp_next_scheduled( 'movies_cron_hook' );
			//echo "next_timestamp[". $next_timestamp ."] current_timestamp[". time() ."]<br />";
			if (!$next_timestamp) {
				//echo "No Cron scheduled<br />";				
				wp_schedule_event(time() + 60, 'movies-custom', 'movies_cron_hook' );
			} else {
				//echo "Next Cron scheduled: ". date_i18n( 'Y-m-d H:i:s', $next_timestamp + get_option( 'gmt_offset' ) * 3600, false );
				//wp_unschedule_event( $next_timestamp, 'movies_cron_hook' );
			}
		}
		
		function movies_cron_proc() {
			
			$cron_log_filename = $this->settings['cache-dir'] .'/cron-process-log.txt';
			$this->cron_log_fp = fopen( $cron_log_filename, 'w+');

			if ($this->cron_log_fp != null) {
				fwrite( $this->cron_log_fp, date('Y-m-d H:i:s :'). "Movie Cron Started --------------------------------------------------\r\n");
			}

			$this->movies_xml_processor->process_all_feeds(true, false);

			if ($this->cron_log_fp != null) {
				fwrite( $this->cron_log_fp, date('Y-m-d H:i:s :'). "Movie Cron Ended --------------------------------------------------\r\n");
			}

			if ($this->cron_log_fp != null) {
				fclose( $this->cron_log_fp );
			}
		}
	}
	$movies = new Movies;
}


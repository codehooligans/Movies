<?php
if ( !class_exists( 'movies_xml_parser_base' ) ) {
	class movies_xml_parser_base {

		public $feed_key = '';
		public $feed_url = '';
		public $feed_xml_data = '';

		public $feed_xml_cache_file = '';
		public $feed_xml_error_file = '';

		private $feed_xml_data_loaded = false;

		function __construct( $feed_key, $feed_url ) {

			$this->feed_key = $feed_key;
			$this->feed_url = $feed_url;

			$this->feed_xml_cache_file = $this->get_feed_xml_cache_file_name( $this->feed_key ); 
			$this->feed_xml_error_file = $this->get_feed_xml_error_file_name( $this->feed_key ); 
			
		}
				
		function load_feed_xml_data( $bypass_cache = false ) {
			global $movies;
			
			$this->feed_xml_data = '';
					
			if ( ( file_exists( $this->feed_xml_cache_file ) ) && ( $bypass_cache == false ) ) {
				$feed_xml_cache_file_mod_time = filemtime( $this->feed_xml_cache_file );
				$current_time = time();
				$diff_time = $current_time - $feed_xml_cache_file_mod_time;

				// Has our local cache expired?
				if ($diff_time < $movies->settings['cache-timeout'] ) {
					$this->feed_xml_data = file_get_contents( $this->feed_xml_cache_file );  
				} 
			}
		
			if ( empty( $this->feed_xml_data ) ) {
				$response = wp_remote_get( $this->feed_url, array( 'timeout' => 120, 'httpversion' => '1.1' ) );
				if ( is_wp_error( $response ) ) {
					$this->set_last_feed_xml_data_error( $this->feed_key, $response->get_error_message() );
					return;
				} 
			
				if ( isset($response['body'] ) ) {
					$this->feed_xml_data = $response['body'];

					// If the remote URL provided some content then update the local cache file for later. 
					if ( !empty( $this->feed_xml_data ) ) {
						file_put_contents( $this->feed_xml_cache_file, $this->feed_xml_data );
					}
				}

				// Remove the previous error 
				if ( file_exists( $this->get_feed_xml_error_file_name( $this->feed_key ) ) ) {
					unlink( $this->get_feed_xml_error_file_name( $this->feed_key ) );
				}
			}
		
			$this->feed_xml_data = simplexml_load_string( $this->feed_xml_data, null, LIBXML_NOCDATA );
			
			$this->filter_feed_xml_data();
		}

		/**
		 * Utility function called from load_feed_xml_data() to start filtering the feed_xml_data elements. 
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function filter_feed_xml_data() {
		}
		
		/**
		 * Utility function to return the cache filename on disk
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function get_feed_xml_cache_file_name( ) {
			global $movies;
			return $movies->settings['cache-dir'] .'/'. $this->feed_key .'-'. $movies->settings['theatre-id'] .'-xml-cache.txt';
		}

		/**
		 * Utility function to return the modified time of the cache filename on disk
		 *
		 * @since 1.0.0
		 * @param 
		 * 		$date_time_format - string containing the date/time format paramters. See PHP date() function for usage.
		 * @return none
		 */				
		function get_feed_xml_cache_file_time( $date_time_format = '' ) {
			$feed_xml_cache_file_time = '';
			$feed_xml_cache_file = $this->get_feed_xml_cache_file_name( );
			if ( file_exists( $feed_xml_cache_file ) ) {
				$feed_xml_cache_file_time = $this->format_datetime( filemtime( $feed_xml_cache_file ), $date_time_format );
			} 
			
			return $feed_xml_cache_file_time;
		}

		/**
		 * Utility function to return the error filename on disk
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function get_feed_xml_error_file_name( ) {
			global $movies;
			return $movies->settings['cache-dir'] .'/'. $this->feed_key .'-'. $movies->settings['theatre-id'] .'-xml-error.txt';
		}

		/**
		 * Utility function to return the modified time of the error filename on disk
		 *
		 * @since 1.0.0
		 * @param 
		 * 		$date_time_format - string containing the date/time format paramters. See PHP date() function for usage.
		 * @return none
		 */				
		function get_feed_xml_error_file_time( $date_time_format = '' ) {
			$feed_xml_error_file_time = '';
			$feed_xml_error_file = $this->get_feed_xml_error_file_name( );
			if ( file_exists( $feed_xml_error_file ) ) {
				$feed_xml_error_file_time = $this->format_datetime( filemtime( $feed_xml_error_file ), $date_time_format );
			} 
			
			return $feed_cache_file_time;
		}


		/**
		 * Utility function to set the contents of the error file on disk
		 *
		 * @since 1.0.0
		 * @param (string) error message string to be written to the error file. 
		 * @return none
		 */				
		function set_last_feed_xml_data_error( $error_message ) {

			file_put_contents( $this->feed_xml_error_file, maybe_serialize( $error_message ) );
		}

		/**
		 * Utility function to return the contents of the error file on disk
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function get_last_feed_xml_data_error_message( ) {

			$this->feed_xml_error_file = $this->get_feed_xml_error_file_name( $this->feed_key ); 
		
			if ( file_exists( $this->feed_xml_error_file ) ) {
				$error_message = file_get_contents( $this->feed_xml_error_file );
				if ( !empty( $error_message ) ) {
					$error_message = maybe_unserialize( $error_message );
				}
				return $error_message;
			}
		}
		
		/**
		 * Utility function to format the date/time output
		 *
		 * @since 1.0.0
		 * @param 
		 *		$datetime = (long) The time in second to be formatted.
		 * 		$date_time_format - string containing the date/time format paramters. See PHP date() function for usage. 
		 * 		If empty the system values will be used from WordPress Settings panel
		 * @return (string) formatted time string.
		 */				
		function format_datetime( $datetime = 0, $date_time_format = '' ) {
			if ( empty( $date_time_format ) ) {
				$date_time_format = get_option( 'date_format' ) .' '. get_option( 'time_format' );
			}
			return date_i18n( $date_time_format, $datetime + get_option( 'gmt_offset' ) * 3600, false );
		}
	}
}

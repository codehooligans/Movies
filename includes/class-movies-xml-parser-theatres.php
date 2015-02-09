<?php
if ( !class_exists( 'movies_xml_parser_theatres' ) ) {
	class movies_xml_parser_theatres extends movies_xml_parser_base {

		function __construct( $feed_key, $feed_url ) {
			parent::__construct( $feed_key, $feed_url );
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
			
			return $movies->settings['cache-dir'] .'/'. $this->feed_key .'-xml-cache.txt';
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
			return $movies->settings['cache-dir'] .'/'. $this->feed_key .'-xml-error.txt';
		}


		/**
		 * Utility function called from load_feed_xml_data() to start filtering the feed_xml_data elements. 
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function filter_feed_xml_data() {
			$feed_xml_data = array();

			if ( !empty( $this->feed_xml_data->TheatresRow ) ) {
				
				foreach( $this->feed_xml_data->TheatresRow as $feed_idx => $feed ) {
					
					$feed = (array)$feed;
					
					foreach($feed as $key => $val) {
						//	echo "key[". $key ."] val[". $val ."][". var_dump($val) ."]<br />";
						if (!is_string($val)) {
							$feed[$key] = (string)$val;
						}
					}

					$TheatreID = intval( $feed['TheatreID'] );
					$feed_xml_data[$TheatreID] = $feed;
				}
			
				$this->feed_xml_data = $feed_xml_data;
			}
			
			$this->theatre_to_options();
		}

		function theatre_to_options() {
			global $movies;
			
			if ( isset( $this->feed_xml_data[$movies->settings['theatre-id']] ) ) {
				update_option( 'Movies_theatre', $this->feed_xml_data[$movies->settings['theatre-id']] );
			} else {
				delete_option( 'Movies_theatre' );
			}
		}

		/**
		 * Utility function to get a list of Theatres
		 *
		 * @since 1.0.0
		 * @param (string) Optinal sortby value. Default is 'TheatreName'. Can be any element if the feed_xml_data. 
		 * Note: Other values are untested.
		 * @return string containing <option></options> elements
		 */				
		function get_theatres( $sortby = 'TheatreName' ) {
			$feed_xml_data_sorted = array();

			if ( !empty( $this->feed_xml_data ) ) {

				foreach( $this->feed_xml_data as $feed ) {
					
					$sort_value = (string)$feed[$sortby];
					
					$feed_xml_data_sorted[$sort_value] = $feed;
				}
								
				if ( !empty( $feed_xml_data_sorted ) ) {
					ksort( $feed_xml_data_sorted );
				}
			}
			return $feed_xml_data_sorted;
		}


		/**
		 * Build a list of the Theaters to be used within a <select> HTML element
		 *
		 * @since 1.0.0
		 * @param none
		 * @return string containing <option></options> elements
		 */				
		function get_theatres_select_options( $selected_id = '', $sortby = 'TheatreName' ) {
			$options = '';

			$theatres = $this->get_theatres( $sortby );

			// IF we have a none-empty value then build out the <option></option> values
			if ( !empty( $theatres ) ) {
			
				foreach( $theatres as $theatre ) {
					$options .= '<option '. selected( intval($theatre['TheatreID'] ), intval( $selected_id ) ) .' value="'. $theatre['TheatreID'] .'">'. '('. intval( $theatre['TheatreID'] ) .') '. esc_attr( $theatre['TheatreName'] ) .' - '. esc_attr( $theatre['TheatreAddress'] ) .'</option>';
				}
			}
		
			return $options;
		}
	}
}
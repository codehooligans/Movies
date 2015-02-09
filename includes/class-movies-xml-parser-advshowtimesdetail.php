<?php
if ( !class_exists( 'movies_xml_parser_advshowtimesdetail' ) ) {
	class movies_xml_parser_advshowtimesdetail extends movies_xml_parser_base {

		function __construct( $feed_key, $feed_url ) {
			global $movies;

			// If the Theatre ID is set within wp-admin then we can filter the returned XML for that location.
			if ( intval( $movies->settings['theatre-id'] ) > 0 ) {
				$feed_url = add_query_arg( 'TheatreID', intval( $movies->settings['theatre-id'] ), $feed_url );
			}
			parent::__construct( $feed_key, $feed_url );
		}

		/**
		 * Utility function called from load_feed_xml_data() to start filtering the feed_xml_data elements. 
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function filter_feed_xml_data() {
			global $movies;
			
			$feed_xml_data = array();
			
			if ( !empty( $this->feed_xml_data->AdvShowtimesDetailRow ) ) {
				//echo "feed_xml_data<pre>"; print_r( $this->feed_xml_data ); echo "</pre>";
				//die();
				
				foreach( $this->feed_xml_data->AdvShowtimesDetailRow as $feed_idx => $feed ) {
					
					$feed = (array)$feed;
					
					foreach($feed as $key => $val) {
						//	echo "key[". $key ."] val[". $val ."][". var_dump($val) ."]<br />";
						if (!is_string($val)) {
							$feed[$key] = (string)$val;
						}
					}

					$TheatreID = intval( $feed['TheatreID'] );
					
					if ( ( intval( $movies->settings['theatre-id'] ) > 0 ) 
					  && ( $TheatreID == intval( $movies->settings['theatre-id'] ) ) ) {

						$feed = $this->process_feed_showtimes( $feed );

						$MovieID = intval( $feed['MovieID'] );
						$BusinessDate_YYYYMMDD = $feed['BusinessDate_YYYYMMDD'];
						
						if ( !isset( $feed_xml_data[$MovieID] ) ) $feed_xml_data[$MovieID] = array();
						
						if ( !isset( $feed_xml_data[$MovieID][$BusinessDate_YYYYMMDD] ) ) 
							$feed_xml_data[$MovieID][$BusinessDate_YYYYMMDD] = $feed;

					}
				}
				//echo "feed_xml_data<pre>"; print_r($feed_xml_data); echo "</pre>";
				//die();

				$this->feed_xml_data = $feed_xml_data;
			}
		}
				
		/**
		 * This function will accept a feed object and do the needed processing for the showtimes. The showtimes are determined 
		 * from a number of fields (see $keys array internal to the function). These fields are provided with one or more 
		 * values pipe '|' delimited. This function will parse out the multiple values and create sub-arrays assigned
		 * back to the feed. 
		 *
		 * @since 1.0.0
		 * @param (object) feed data item
		 * @return (object) feed data item
		 */				
		function process_feed_showtimes( $feed ) {
			
			//$feed['BusinessDate_YYYYMMDD'] = date_i18n( 'Ymd', strtotime($feed['BusinessDate'] ) + get_option( 'gmt_offset' ) * 3600, false );
			$feed['BusinessDate_YYYYMMDD'] = date_i18n( 'Ymd', strtotime($feed['BusinessDate'] ), false );
			
			$keys = array(
				'Showtimes', 
				'HouseIDs', 
				'ScheduleIDs', 
				'Over21Flags', 
				'VipFlags', 
				'DiscountFlags', 
				'SneakFlags', 
				'DigitalFlags', 
				'IsPast', 
				'SeatsRemaining', 
				'NoPassFlags', 
				'ReservedSeats', 
				'ReadOnlyFlags', 
				'PrivateFlags', 
				'SubTitledFlags', 
				'ClosedCaptionedFlags', 
				'AllowWebFlags', 
				'OpenCaptionedFlags'
			);
			
			foreach( $keys as $key ) {
				if ( ( isset( $feed[$key] ) ) && ( !empty( $feed[$key] ) ) ) {
					$data[$key] = explode( '|', $feed[$key] );
					unset( $feed[$key] );
					
					foreach( $data[$key] as $idx => $value ) {
						$data[$key][$idx] = trim( $value );
					}
					
					if ( $key == 'Showtimes' ) {
						foreach( $data[$key] as $data_idx => $data_item ) {
							//$data_time = strtotime( $data_item ) + get_option( 'gmt_offset' ) * 3600;
							$data_time = strtotime( $data_item ) ;

							$data_hhmm = date_i18n( 'Hi', $data_time, false ); 
							$data[$key][$data_idx] = $data_hhmm;
							
							if ( !isset( $data[$key.'_time'] ) ) $data[$key.'_time'] = array();
							$data[$key.'_time'][] = $data_time;
						}
					}
				}
			}
			
			if ( !empty( $data ) ) {
				$showtimes_meta_data = array();
				if ( ( isset( $data['Showtimes'] ) ) && ( !empty( $data['Showtimes'] ) ) ) {
					foreach( $data['Showtimes'] as $idx => $showtime_hhmm ) {
					
						foreach( $keys as $key ) {
							if ( !isset( $showtimes_meta_data[$showtime_hhmm] ) ) 
								$showtimes_meta_data[$showtime_hhmm] = array();
							if ( !isset( $showtimes_meta_data[$showtime_hhmm][$key] ) ) 
								$showtimes_meta_data[$showtime_hhmm][$key] = array();
						
							$showtimes_meta_data[$showtime_hhmm][$key] = $data[$key][$idx];
						}
						$showtimes_meta_data[$showtime_hhmm]['url'] = add_query_arg( 
							array( 
								'MovieID' 		=> $feed['MovieID'], 
								'ScheduleID' 	=> $data['ScheduleIDs'][$idx]
							), $feed['link'] );
						$showtimes_meta_data[$showtime_hhmm]['TheatreID'] = $feed['TheatreID'];
						$showtimes_meta_data[$showtime_hhmm]['BusinessDate_YYYYMMDD'] = $feed['BusinessDate_YYYYMMDD'];
					}

					$feed['Showtimes'] = $showtimes_meta_data;
				}
			}


			// The movie title for the showtimes detail is DIFFERENT than the title for the Movies.xml. The problem is the movie
			// title from the showtimes details contains 'extra' elements separated by ' - '. The general format is 
			// title - rating - runtime. But we have to consider IF the movie title itself contains '-'. To handle this we 
			// need to remove the last two items for the rating and runtime and leave the rest. 
			//$title_parts = explode(' - ', $feed['title']);
			//if ( !empty( $title_parts ) ) {
			//	$feed['title'] = implode(' - ', array_slice( $title_parts, 0, count( $title_parts )-2));
			//}

			// Remove some of the attributes we no longer need.
			unset($feed['title']);
			unset($feed['link']);
			//unset($feed['TheatreID']);
			unset($feed['pubDate']);
			unset($feed['BusinessDate']);
			unset($feed['LargeFormat']);
			unset($feed['Enclosure']);
			unset($feed['TSMovieID']);
			
			return $feed;
		}
	}
}
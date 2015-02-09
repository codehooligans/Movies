<?php
if ( !class_exists( 'movies_xml_parser_bookings' ) ) {
	class movies_xml_parser_bookings extends movies_xml_parser_base {
		
		function __construct( $feed_key, $feed_url ) {
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

			//echo "feed_xml_data<pre>"; print_r($this->feed_xml_data); echo "</pre>";

			$movies_object = $movies->movies_xml_processor->get_feeds_object('movies');

			if (!empty($this->feed_xml_data->BookingsRow)) {
				
				foreach($this->feed_xml_data->BookingsRow as $feed_idx => $feed) {
					
					$feed = (array)$feed;

					//echo "feed<pre>"; print_r($feed); echo "</pre>";
					
					$TheatreID = intval( $feed['TheatreID'] );
					if ( $TheatreID == intval( $movies->settings['theatre-id'] ) ) {

						$MovieID = intval( $feed['MovieID'] );
					
						list($from_date_mm, $from_date_dd, $from_date_yyyy) = explode('/', $feed['FromDate']);
						$feed['FromDate'] = $from_date_yyyy.$from_date_mm.$from_date_dd;
													
						list($to_date_mm, $to_date_dd, $to_date_yyyy) = explode('/', $feed['ToDate']);
						$feed['ToDate'] = $to_date_yyyy.$to_date_mm.$to_date_dd;
						
						if (!isset($feed_xml_data[$MovieID])) {
							$feed_xml_data[$MovieID] = $feed;
						} else {
							if (!isset($feed_xml_data[$MovieID]['FromDate'])) {
								$feed_xml_data[$MovieID]['FromDate'] = $feed['FromDate'];
							} else if ( $feed['FromDate'] < $feed_xml_data[$MovieID]['FromDate']) {
								$feed_xml_data[$MovieID]['FromDate'] = $feed['FromDate'];
							}
						
							if (!isset($feed_xml_data[$MovieID]['ToDate'])) {
								$feed_xml_data[$MovieID]['ToDate'] = $feed['ToDate'];
							} else if ( $feed['ToDate'] > $feed_xml_data[$MovieID]['ToDate']) {
								$feed_xml_data[$MovieID]['ToDate'] = $feed['ToDate'];
							}
						}
					}
				}

				//echo "feed_xml_data<pre>"; print_r($feed_xml_data); echo "</pre>";
				//die();
				
				$this->feed_xml_data = $feed_xml_data;
			}
		}
		
		/**
		 * Utility function called from filter_feed_xml_data() to filter the date data. 
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function filter_dates( $feed ) {
			//echo "feed<pre>"; print_r($feed); echo "</pre>";
			
			$new_feed = array();
			
			$new_feed['MovieID'] = $feed['MovieID'];
			if ( !isset( $new_feed['dates'] ) ) $new_feed['dates'] = array();
			
			$new_feed['dates'][$feed['FromDate']] = $feed['ToDate'];
			//echo "new_feed<pre>"; print_r($new_feed); echo "</pre>";
			//die();
			return $new_feed;
		}
	}
}
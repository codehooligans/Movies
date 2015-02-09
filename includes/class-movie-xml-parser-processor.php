<?php
/* The Movies XML Processor logic contained in this file is the first half to the Movies processing. This first half 
will drive the conntection to the remote source to retreive the XML feed. This XML data will be cached on disk. Then
a secondary process will filter the feed data elements. 

After this first half the second half will kick in and load the XML filtered data into the Movies custom post type. 
*/

include ( dirname( __FILE__ ) . '/class-movies-xml-parser-base.php');
//include ( dirname( __FILE__ ) . '/class-movies-file-locker.php');

if ( !class_exists( 'Movies_XML_Parser_Processor' ) ) {
	class Movies_XML_Parser_Processor {
		
		// Defines all the Feed XML instances we are to process. 		
		var $feeds 					= array();
		var $movie_meta_keys 		= array();
		var $showtimes_meta_keys 	= array();
		
		var $process_error_log_fp;
		var $process_data			= array();
		//var $process_file_locker;
		
		function __construct() {

			$this->feeds 		= array(
				'theatres'				=>	array(
							'url'		=>	'http://txc.tstickets.com/rss/Theatres.xml',
							'key'		=>	'theatres',
							'classfile'	=>	dirname( __FILE__ ) . '/class-movies-xml-parser-theatres.php',
							'class'		=>	'movies_xml_parser_theatres',
							'object'	=>	false
				),
				'bookings'				=>	array(
							'url'		=>	'http://txc.tstickets.com/rss/Bookings.xml',
							'key'		=>	'bookings',
							'classfile'	=>	dirname( __FILE__ ) . '/class-movies-xml-parser-bookings.php',
							'class'		=>	'movies_xml_parser_bookings',
							'object'	=>	false
				),
				'advshowtimesdetail'	=>	array(
							'url'		=>	'http://txc.tstickets.com/rss/AdvShowtimesDetail.xml',
							'key'		=>	'advshowtimesdetail',
							'classfile'	=>	dirname( __FILE__ ) . '/class-movies-xml-parser-advshowtimesdetail.php',
							'class'		=>	'movies_xml_parser_advshowtimesdetail',
							'object'	=>	false
				),
				'movies'				=>	array(
							'url'		=>	'http://txc.tstickets.com/rss/Movies.xml',
							'key'		=>	'movies',
							'classfile'	=>	dirname( __FILE__ ) . '/class-movies-xml-parser-movies.php',
							'class'		=>	'movies_xml_parser_movies',
							'object'	=>	false
				),
			);
			
			// Defines all the elements from the Movie XML data we want to tranfer to the post_meta key-value pairs. 
			$this->movie_meta_keys = array(
				'MovieID',
				//'TheatreID',
				'StartDate',
				'MovieTitle',
				'MovieRunTime',
				'MovieRating',
				'MovieRatingDesc',
				'MovieDescription',
				'TSMovieID',
				'MovieSiteLink',
				'MoviePosterLink',
				'TheatreCount',
				'ActorList',
				'Director',
				'is_3D',
				'imdbID',
				'Year',
				'Genre',
				'Writer',
				'Language',
				'Country',
				'Awards',
				'Metascore',
				'imdbRating',
				'imdbVotes',
				'Type',
				'tomatoMeter',
				'tomatoImage',
				'tomatoRating',
				'tomatoReviews',
				'tomatoFresh',
				'tomatoRotten',
				'tomatoConsensus',
				'tomatoUserMeter',
				'tomatoUserRating',
				'tomatoUserReviews',
				'DVD',
				'BoxOffice',
				'Production',
				'Website',
				'MovieTrailerURL',
				'Poster'
			);

			$this->showtimes_meta_keys = array(
				//'TheatreID',
				//'link',
				//'LargeFormat',
				//'HouseIDs',
				//'ScheduleIDs',
				//'Over21Flags',
				//'VipFlags',
				//'DiscountFlags',
				//'SneakFlags',
				//'DigitalFlags',
				//'IsPast',
				//'SeatsRemaining',
				//'NoPassFlags',
				//'ReservedSeats',
				//'ReadOnlyFlags',
				//'PrivateFlags',
				//'SubTitledFlags',
				//'ClosedCaptionedFlags',
				//'AllowWebFlags',
				//'OpenCaptionedFlags',
				//'pubDate',
				//'BusinessDate',
				//'Enclosure',
				//'TSMovieID',
				//'BusinessDate_YYYYMMDD'
			);
			
			//$this->process_file_locker 	= new MoviesFileLocker( $this->get_feed_process_locker_file_name() );
		}
		
		function __destruct() {
			$this->end_feed_process();
			
		}
		
		
		/**
		 * This function is used to handle the loading of the XML data from cache file or remove and to process the XML data to the Post Type.
		 *
		 * @since 1.0.0
		 * @param 
		 * 		bypass_cache - true/false Default is false. Allow force to reload feeds from external if true. If false then local cache file is used.
		 * 		delete_existing_movies - Optional true/false flag to remove existing movies before processing. This is needed for example when changing the TheatreID.
		 * @return none
		 */				
		function process_all_feeds( $bypass_cache = false, $delete_existing_movies = false ) {
			global $movies; 
			
			$movies->movies_xml_processor->load_feed_objects( $bypass_cache );

			//echo "feeds<pre>"; print_r($movies->movies_xml_processor->feeds); echo "</pre>";
			//die();
			
			// Once all the feeds have been loaded we call the second step to load the XML data to the custom post type
			$movies->movies_xml_processor->process_feeds_to_post_type( $delete_existing_movies );

			//echo "process_data<pre>"; print_r($movies->movies_xml_processor->process_data); echo "</pre>";
			
		}
		
		/**
		 * This is the main entery point to start loading the defined $feeds. 
		 *
		 * @since 1.0.0
		 * @param 
		 * 		bypass_cache - true/false Default is false. Allow force to reload feeds from external if true. If false then local cache file is used.
		 * 		feed_to_load - (string) Optioanl feed key to load. If not provided all feeds will be loaded. If 
		 * a feed key is provided only that feed is loaded. 
		 * @return none
		 */				
		function load_feed_objects( $bypass_cache = false, $feed_to_load = '' ) {
			
			foreach( $this->feeds as $feed_key => $feed ) {
				if ( is_object( $this->feeds[$feed_key]['object'] ) ) {
					unset( $this->feeds[$feed_key]['object'] );
				}
				include( $this->feeds[$feed_key]['classfile'] );
				$this->feeds[$feed_key]['object'] = new $feed['class'] ( 
					$this->feeds[$feed_key]['key'], 
					$this->feeds[$feed_key]['url'] 
				);

				if ( ( empty( $feed_to_load ) ) || ( $feed_key == $feed_to_load ) ) {
					$this->get_feeds_object($feed_key)->load_feed_xml_data( $bypass_cache );
				} 
			}
		}
		
		/**
		 * A utility function to return an array of the feed keys. This is handy to allow looping the 
		 * feeds in external function without exposing the feeds array directly. 
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function get_feeds_keys() {
			return array_keys( $this->feeds );
		}
		
		/**
		 * Utility function to return a specific feed object based on a feed key. 
		 *
		 * @since 1.0.0
		 * @param (string) the feed key of the object to return
		 * @return (object) IF the feed key is value the object instance of the feed is returned. 
		 */				
		function get_feeds_object( $feed_key = '' ) {
			if ( ( isset( $this->feeds[$feed_key] ) ) && (is_object( $this->feeds[$feed_key]['object'] ) ) ) 
				return $this->feeds[$feed_key]['object'];
		}
		
		/**
		 * Utility function to return the error filename on disk
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */				
		function get_feed_process_error_file_name( ) {
			global $movies;
			return $movies->settings['cache-dir'] .'/process-'. $movies->settings['theatre-id'] .'-error.txt';
		}

		function get_feed_process_locker_file_name( ) {
			global $movies;
			return $movies->settings['cache-dir'] .'/process-'. $movies->settings['theatre-id'] .'-locker.lck';
		}
		
		function init_feed_process() {

			$this->process_error_log_fp = fopen( $this->get_feed_process_error_file_name(), 'w');
			
			$this->process_data['movies_new'] = 0;
			$this->process_data['movies_updated'] = 0;

		}
		
		function end_feed_process() {
			//if ( $this->process_error_log_fp != null) {
			//	fclose( $this->process_error_log_fp );
			//}
		}
		
		function update_feed_process_error_file( $error_message = '' ) {
			if ( ( $this->process_error_log_fp ) && ( !empty( $error_message ) ) ) {
				fwrite( $this->process_error_log_fp, $error_message."<br />\r\n" );
			}
		}
		
		/**
		 * This function migrates the feed_xml_data from load_feed_objects into WordPress new custom post type ('Movie'). 
		 *
		 * @since 1.0.0
		 * @param 
		 * 		delete_existing_movies - Optional true/false flag to remove existing movies before processing. This is needed for example when changing the TheatreID.
		 * @return none
		 */
		function process_feeds_to_post_type( $delete_existing_movies = false ) {
			global $movies;
			
			$this->init_feed_process();
			
			//if ( !$this->process_file_locker->is_locked() ) {
			//	$this->update_feed_process_error_file( 'Error: Process lock is present.');
			//	$locker_info = $this->process_file_locker->get_locker_info();
			//	echo "locker_info<pre>"; print_r($locker_info); echo "</pre>";
			//	return;
			//} else {
			//	$locker_info = array(
			//		'doing'				=>	__('Processing Feeds', 'movies'),
			//		'time_start' 		=>	time()
			//	);
			//	$this->process_file_locker->set_locker_info( $locker_info );
			//}			
			
			if ( $delete_existing_movies == true ) {
				$this->delete_all_movies();
			}
			
			// Used to contain a global date listing of all dates. Used to drive a dropdown select date picker.			
			$movies_date_sets = array();
			
			$movies_object = $this->get_feeds_object('movies');
			if ( !is_object( $movies_object ) ) {
				return;
			}
			//echo "movies_object<pre>"; print_r($movies_object); echo "</pre>";
			//die();			
			
			$advshowtimesdetail_object = $this->get_feeds_object('advshowtimesdetail');
			if ( !is_object( $advshowtimesdetail_object ) ) {
				return;
			}
			//echo "advshowtimesdetail_object<pre>"; print_r($advshowtimesdetail_object); echo "</pre>";
			//die();
			
			$bookings_object = $this->get_feeds_object('bookings');
			if ( !is_object( $bookings_object ) ) {
				return;
			}
			//echo "bookings_object<pre>"; print_r($bookings_object); echo "</pre>";
			//die();

			$theatre_id 			= intval( $movies->settings['theatre-id'] );
			$movies_updated_date 	= $advshowtimesdetail_object->get_feed_xml_cache_file_time('Ymd');
			
			// We loop through the showtime details since that is by theatre. Where as the movie object
			// XML data is ALL movie across all theatres. 				
			foreach( $movies_object->feed_xml_data as $movie_id => $movie ) {
				
				//if ($movie['MovieID'] != 28686) continue;
				
				//echo "movie<pre>"; print_r($movie); echo "</pre>";
				//echo "[". $movie['MovieID'] ."] MovieTitle[". $movie['MovieTitle'] ."]<br />";
				//continue;
				
				unset( $showtimes );
				if ( ( isset( $advshowtimesdetail_object->feed_xml_data[$movie_id] ) ) 
				  && ( !empty( $advshowtimesdetail_object->feed_xml_data[$movie_id] ) ) ) {
  					$showtimes = $advshowtimesdetail_object->feed_xml_data[$movie_id];
				}
				
				unset( $bookings );
				if ( ( isset( $bookings_object->feed_xml_data[$movie_id] ) ) 
				  && ( !empty( $bookings_object->feed_xml_data[$movie_id] ) ) ) {
					$bookings = $bookings_object->feed_xml_data[$movie_id];
				}

				if ( ( !isset( $showtimes ) ) && ( !isset( $bookings ) ) ) {
					//echo "Missing Booking/Showtime information for MovieID[". $movie_id ."]<br />";
					//echo "movie<pre>"; print_r($movie); echo "</pre>";
					//die();
					continue;
				}
				
				// Once we have the movie and we are sure it belongs to this theatre we do the remote lookup 
				// to pull in the 'extra' fields from IMBD as well as the trailer videos
				$movie = $movies_object->remote_data_lookup( $movie );
				$movie = $movies_object->get_movie_trailer( $movie );
								
				$post_id = get_post_id_from_movie_id( $movie_id );
				if ( empty( $post_id ) ) {

					$movie_post = array(
					  'post_title'    	=> 	$movie['MovieTitle'],
					  'post_content'  	=> 	$movie['MovieDescription'],
					  'post_status'   	=> 	'publish',
					  'post_type'		=>	'movie'
					);
					
					// Insert the post into the database
					$post_id = wp_insert_post( $movie_post );
					if (!empty( $post_id ) ) {
						update_post_meta( $post_id, 'MovieID', $movie_id );
						$this->process_data['movies_new'] += 1;
					}
					update_post_meta( $post_id, 'TheatreID', $theatre_id );
					
				} else {
					
					$movie_post = array(
						'ID'				=>	$post_id,
						'post_title'    	=> 	$movie['MovieTitle'],
						'post_content'  	=> 	$movie['MovieDescription'],
						'post_status'   	=> 	'publish',
						'post_type'			=>	'movie'
					);
					//echo "movie_post<pre>"; print_r($movie_post); echo "</pre>";
					// Update the post into the database
					$post_id_new = wp_insert_post( $movie_post );
					//echo "post_id[". $post_id ."] post_id_new[". $post_id_new ."]<br />";

					$this->process_data['movies_updated'] += 1;
					
					// here we have an existing movie entry. We want to purge the showtime post meta data associated with the movie
					// Because in the code further below it will be added back into the system. This is to prevent stale date from
					// being stuck in the post_meta table. 
					
					$movie_dates_key = 'Showtimes_'. $theatre_id .'_dates';
					$movie_dates_val = get_post_meta( $post_id, $movie_dates_key, true);
					if ( !empty( $movie_dates_val ) ) {
						
						foreach( $movie_dates_val as $movie_date_key => $movie_date_display ) {
							$movie_times_key = 'Showtimes_'. $theatre_id .'_'. $movie_date_key .'_times';
							delete_post_meta( $post_id, $movie_times_key);
						}
					}
					delete_post_meta( $post_id, $movie_dates_key );
				}
				
				$movies_object->download_movie_image( $post_id, $movie );
				
				if (!empty( $post_id ) ) {
					
					// We update this to know when each post has been updated. If after processing a post has not been 
					// updated we know we can delete it.
					//update_post_meta( $post_id, 'MovieUpdatedDate', $movies_updated_date );
					
					// Update/Add the movie post_meta fields
					//foreach( $this->movie_meta_keys as $meta_key ) {
					//	update_post_meta( $post_id, $meta_key, esc_attr($movie[$meta_key] ) );
					//}
					
					foreach( $movie as $movie_meta_key => $movie_meta_val ) {
						update_post_meta( $post_id, $movie_meta_key, $movie_meta_val );
					}
				} 
				
				//if ( ( isset( $bookings_object->feed_xml_data[$movie_id] ) ) 
				//  && ( !empty( $bookings_object->feed_xml_data[$movie_id] ) ) ) {
				//	  $bookings = $bookings_object->feed_xml_data[$movie_id];
					  //echo "bookings<pre>"; print_r($bookings); echo "</pre>";
				
	  			if ( ( isset( $bookings ) ) && ( !empty( $bookings ) ) ) {
					//echo "bookings<pre>"; print_r($bookings); echo "</pre>";
					
					if (isset($bookings['FromDate'])) {
						update_post_meta( $post_id, 'FromDate', $bookings['FromDate'] );
					} else {
						delete_post_meta( $post_id, 'FromDate' );
					}
					
					if (isset($bookings['ToDate'])) {
						update_post_meta( $post_id, 'ToDate', $bookings['ToDate'] );
					} else {
						delete_post_meta( $post_id, 'ToDate' );
					}
				}
				
				// If the movie_id is not present this means simply this is a future movie. So we move on. 
				//if ( ( isset( $advshowtimesdetail_object->feed_xml_data[$movie_id] ) ) 
				//  && ( !empty( $advshowtimesdetail_object->feed_xml_data[$movie_id] ) ) ) {
				
				if ( ( isset( $showtimes ) ) && ( !empty( $showtimes ) ) ) {

					//echo "showtimes<pre>"; print_r($showtimes); echo "</pre>";
					
					$showtimes_date_sets = array();

					//$showtimes = $advshowtimesdetail_object->feed_xml_data[$movie_id];
					//echo "showtimes<pre>"; print_r($showtimes); echo "</pre>";
					//die();
				
					foreach( $showtimes as $showtimes_YYYYMMDD => $showtimes_data ) {
				
						$showtimes_date_sets[$showtimes_YYYYMMDD] = $showtimes_YYYYMMDD;

						$movies_date_sets[$showtimes_YYYYMMDD] = $showtimes_YYYYMMDD;

						// Update/Add the showtimes post_meta fields
						foreach( $this->showtimes_meta_keys as $meta_key ) {
							update_post_meta( $post_id, $meta_key, esc_attr( $showtimes_data[$meta_key] ) );
						}

						$post_meta_time_key = 'Showtimes_'. $theatre_id .'_'. $showtimes_YYYYMMDD .'_times';
						if ( ( isset( $showtimes_data['Showtimes'] ) )  && ( !empty( $showtimes_data['Showtimes'] ) ) ) {
							update_post_meta( $post_id, $post_meta_time_key, $showtimes_data['Showtimes'] );
						} else {
							delete_post_meta( $post_id, $post_meta_time_key );
						}
					}				
				
					// The following post_meta value is an array of all dates for a specific post/movie. This help in 
					// searching for movies
					$post_meta_date_key = 'Showtimes_'. $theatre_id .'_dates';
					if ( !empty( $showtimes_date_sets ) ) {
						update_post_meta( $post_id, $post_meta_date_key, $showtimes_date_sets );

						$FromDate 	= array_slice($showtimes_date_sets, 0, 1);
						if ( !empty( $FromDate[0] ) ) {
							update_post_meta( $post_id, 'FromDate', $FromDate[0] );
						}

						$ToDate		= array_slice($showtimes_date_sets, count($showtimes_date_sets)-1, 1);
						if ( !empty( $ToDate[0] ) ) {
							update_post_meta( $post_id, 'ToDate', $ToDate[0] );
						}
					
					} else {
						delete_post_meta( $post_id, $post_meta_date_key );
					}
				} else {
					$post_meta_date_key = 'Showtimes_'. $theatre_id .'_dates';
					delete_post_meta( $post_id, $post_meta_date_key );
				}
				
				if (function_exists('w3tc_pgcache_flush_post')) {
					w3tc_pgcache_flush_post($post_id); 
				}
			}
			
			$this->delete_stale_movies();


			if (function_exists('w3tc_pgcache_flush_post')) {
				w3tc_pgcache_flush_post(9); 
				w3tc_pgcache_flush_post(153); 
				w3tc_pgcache_flush_post(175); 
				w3tc_pgcache_flush_post(177); 
			}
			
			
			// We also store a global options entry for all movie dates. This can help drive screen elements like a generic
			// dropdown showing selectable date for movies.
			$options_date_key = 'Movies_'. $theatre_id .'_dates';
			//echo "movies_date_sets<pre>"; print_r($movies_date_sets); echo "</pre>";
			if ( !empty( $movies_date_sets ) ) {
				ksort( $movies_date_sets );
				update_option( $options_date_key, $movies_date_sets );
			} else {
				delete_option( $options_date_key );
			}
			
			$this->end_feed_process();
		}

		/**
		 * Utility function to delete old movie (post within the 'movie' post_type). This is called AFTER the XML processing to 
		 * the movie custom post type. When the post_type is added/updated a post_meta field 'MovieUpdatedDate' is set to the 
		 * current YYYYMMDD. This function will query all posts with the post_meta field value prior than today's date. 
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */
		function delete_stale_movies() {
			
			// Get the reference to our movies object
			$advshowtimesdetail_object = $this->get_feeds_object('advshowtimesdetail');
			if ( !is_object( $advshowtimesdetail_object ) ) {
				return;
			}
			
			// Ensure we have a valid update date.
			$movies_updated_date = $advshowtimesdetail_object->get_feed_xml_cache_file_time('Ymd');
			if ( empty( $movies_updated_date ) ) {
				return;
			}
			
			// Build our purge threshold date. We purge movie which have not been updated in 5 days or more.
			$last_update_yyymmdd = $advshowtimesdetail_object->get_feed_xml_cache_file_time( 'Ymd' );
			$purge_threshold_yyyymmdd = date('Ymd', strtotime($last_update_yyymmdd)-(24*60*60*5));
			
			$movie_query_args = array();
			$movie_query_args['post_type'] 		= 	'movie';
			$movie_query_args['nopaging']		=	true;
			$movie_query_args['meta_key']		=	'MovieUpdatedDate';
			$movie_query_args['meta_value']		=	$purge_threshold_yyyymmdd;
			$movie_query_args['meta_compare']	=	'<';
			//echo "movie_query_args<pre>"; print_r($movie_query_args); echo "</pre>";
			//die();
			
			$movie_query = new WP_Query( $movie_query_args );
			//echo "movie_query<pre>"; print_r($movie_query); echo "</pre>";
			//die();
			
			if ( ( isset( $movie_query->posts ) ) && ( !empty( $movie_query->posts ) ) ) {
				foreach( $movie_query->posts as $idx => $movie_post ) {
					$ret = wp_delete_post( $movie_post->ID );
				}
			}
			
		}
		
		/**
		 * Utility function to delete all movie (post within the 'movie' post_type). This is done when 
		 * processing the XML data to the post_type. This is to ensure no legacy movie items are still in the system. 
		 *
		 * @since 1.0.0
		 * @param none
		 * @return none
		 */
		function delete_all_movies() {
			
			$movie_query_args = array();
			$movie_query_args['post_type'] 	= 	'movie';
			$movie_query_args['nopaging']	=	true;
			$movie_query = new WP_Query( $movie_query_args );

			if ( ( isset( $movie_query->posts ) ) && ( !empty( $movie_query->posts ) ) ) {
				foreach( $movie_query->posts as $idx => $movie_post ) {
					$ret = wp_delete_post( $movie_post->ID );
				}
			}
		}
	}
}

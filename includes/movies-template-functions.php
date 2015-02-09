<?php
/**
 * This is a utility function to return the information about the select Theatre. This is mostly for any where on the theme side where the Theatre name, location, Address might need to be displayed. 
 *
 * @since 1.0.0
 * @param none
 * @return array for the current Theatre
 */			
if ( !function_exists( 'get_theatre_info' ) ) {
	function get_theatre_info( ) {
		return get_option( 'Movies_theatre' );
	}
}

/**
 * This is a utility function to return the post_id for a given movie_id value. The movie_id 
 * is stored as post_meta data (MovieID) for the post
 *
 * @since 1.0.0
 * @param $movie_id (integer)
 * @return $post_id (integer)
 */			
if ( !function_exists( 'get_post_id_from_movie_id' ) ) {
	function get_post_id_from_movie_id( $movie_id = 0 ) {
		$movie_id = intval( $movie_id );
		if ( !empty( $movie_id ) ) {
			$posts = get_posts( array( 
				'post_type' 	=> 	'movie', 
				'meta_key' 		=> 	'MovieID', 
				'meta_value' 	=> 	$movie_id ) 
			);
		
			if (!empty($posts)) {
				foreach($posts as $post) {
					return $post->ID;
				}
			}
		}
	}
}

/**
 * This is a utility function to return the movie_id for a given post_id value. The movie_id 
 * is stored as post_meta data (MovieID) for the post
 *
 * @since 1.0.0
 * @param $post_id (integer)
 * @return $movie_id (integer)
 */				
if ( !function_exists( 'get_movie_id_from_post_id' ) ) {
	function get_movie_id_from_post_id( $post_id = 0 ) {
		$post_id = intval( $post_id );
		if ( !empty( $post_id ) ) {
			return get_post_meta( $post_id, 'MovieID', true);
		}
	}
}
/**
 * This is a utility function to get a listing of all dates for all Movies.  
 *
 * @since 1.0.0
 * @param args array using the following optional parameters:
 * 		data_format (optional) - This controls the format of the dats returned from the function. Possible values are 
 * 			'array' (default) - Standard PHP array
 *			'select_options' - HTML <option></option> sets
 * 		display_format (optional) - Can be used to control the date format. If not provided the system date_format is used.
 * @return Various. Either Array or string depending on the inbound value of 'data_format'
 */				
if ( !function_exists( 'get_all_dates_all_movies' ) ) {
	function get_all_dates_all_movies( $args = array() ) {
		global $movies;
		
		
		$defaults = array(
			'data_format'			=>	'array',
			'display_format' 		=> 	get_option( 'date_format' ),
			'upcoming_times_only'	=>	true
		);

		$args = wp_parse_args( $args, $defaults );
		$args['theatre_id'] 		= intval( $movies->settings['theatre-id'] );
		
		$movies_dates = get_option('Movies_'. $args['theatre_id'] .'_dates');
		if ( !empty( $movies_dates ) ) {
		
			if ( $args['data_format'] == 'array' ) {
				$return_out = array();
			} else if ( $args['data_format'] == 'select_options' ) {	
				$return_out = '';
			}
			
			$current_date_yyyymmdd = date_i18n( 'Ymd', time() + get_option( 'gmt_offset' ) * 3600, false );
				
			foreach( $movies_dates as $date ) {
				
				// Check of the showtime HHMM is past.
				if ( $args['upcoming_times_only'] == true ) {
					if ( $date < $current_date_yyyymmdd ) continue;
				}
				
				$display_date_time = date_i18n( $args['display_format'], strtotime( $date.' 00:00:00' ), false );

				if ( $args['data_format'] == 'array' ) {
					$return_out[$date] = $display_date_time;

				} else if ( $args['data_format'] == 'select_options' ) {
					$return_out .= '<option value="'. $date .'">'. $display_date_time .'</option>';

				}
			}
			return $return_out;
		}
	}
}

/**
 * This is a utility function to retreive all the dates for a specific movie_id
 *
 * @since 1.0.0
 * @param args array using the following optional parameters:
 *		post_id (required) - Integer post_id of the movie to lookup. Used the function get_post_id_from_movie_id if needed.
 * 		data_format (optional) - This controls the format of the dats returned from the function. Possible values are 
 * 			'array' (default) - Standard PHP array
 *			'select_options' - HTML <option></option> sets
 * 		display_format (optional) - Can be used to control the date format. If not provided the system date_format is used.
 *		upcoming_times_only (optional) - true/false bool. Default is true. Used to limit dates to be current date to higher. Past dates will not be returned. 
 * @return Various. Either Array or string depending on the inbound value of 'data_format'
 */
if ( !function_exists( 'get_movie_all_dates' ) ) {
	function get_movie_all_dates( $args = array() ) {
		global $movies;
		
		$defaults = array(
			'post_id'				=>	0,
			'data_format'			=>	'array',
			'display_format' 		=> 	get_option('date_format'),
			'upcoming_times_only'	=>	true,
		);
		$args = wp_parse_args( $args, $defaults );
	
		if ( empty( $args['post_id'] ) ) {
			$queried_object = get_queried_object();
			//echo "queried_object<pre>"; print_r($queried_object); echo "</pre>";
			if ( ( is_single() ) 
 			  && ( $queried_object->post_type == 'movie' ) 
			  && ( isset( $queried_object->ID ) ) 
			  && ( !empty( $queried_object->ID ) ) ) {
				$args['post_id'] = $queried_object->ID;
			}
		}
	
		$args['post_id'] 			= intval( $args['post_id'] );
		$args['theatre_id'] 		= intval( $movies->settings['theatre-id'] );

		//echo "args<pre>"; print_r($args); echo "</pre>";

		if ( !empty( $args['post_id'] ) )  {
			$post_meta_date_key = 'Showtimes_'. $args['theatre_id'] .'_dates';
			$movie_dates = get_post_meta( $args['post_id'], $post_meta_date_key, true );
			
			if ( !empty( $movie_dates ) ) {
				ksort( $movie_dates );
		
				if ( $args['data_format'] == 'array' ) {
					$return_out = array();
				} else if ( $args['data_format'] == 'select_options' ) {
					$return_out = '';
				}

				$current_date_yyyymmdd = date_i18n( 'Ymd', time() + get_option( 'gmt_offset' ) * 3600, false );
					
				foreach( $movie_dates as $date ) {
					
					// Check of the showtime HHMM is past.
					if ( $args['upcoming_times_only'] == true ) {
						if ( $date < $current_date_yyyymmdd ) continue;
					}
					
					$display_date_time = date_i18n( $args['display_format'], strtotime( $date .' 00:00:00' ), false );

					if ( $args['data_format'] == 'array' ) {
						$return_out[$date] = $display_date_time;

					} else if ( $args['data_format'] == 'select_options' ) {
						$return_out .= '<option value="'. $date .'">'. $display_date_time .'</option>';

					}
				}
				return $return_out;
			}
		}
	}
}

/**
 * This is a utility function to retreive all the showtimes for a specific movie_id + date (YYYYMMDD) 
 * combination. The returned output will contain the time of the movie (example: 11:20 am, 3:30pm) 
 * along with the URL with the ScheduleID needed to purchase tickets for the specific 
 * TheatreID + MovieID + Showtime combination
 *
 * @since 1.0.0
 * @param args array using the following optional parameters:
 *		post_id (required) - Integer post_id of the movie to lookup. Used the function get_post_id_from_movie_id if needed.
 * 		date (required) - date in YYYYMMDD format.
 * 		data_format (optional) - This controls the format of the dats returned from the function. Possible values are 
 * 			'array' (default) - Standard PHP array
 *			'select_options' - HTML <option></option> formatted sets
 *			'list_items - HTML <li></li> formatted sets
 * 		display_format (optional) - Can be used to control the date format. If not provided the system date_format is used.
 *		upcoming_times_only (optional) - true/false Will filter showtimes to only yield values for times greate than current HHMM system time. Default is true
 * @return Various. Either Array or string depending on the inbound value of 'data_format'.
 * 		array - (default) If the array is select the returned items will have a key using the YYYYMMDDHHMM of the showtime. For the value another array is provided with keys for 'date_display', 'url' and 'time_is_past'. The 'time_is_past' is a true/false bool to indicate if the showtime is past the current HHMM time. 
 */
if ( !function_exists( 'get_movie_date_showtimes' ) ) {
	function get_movie_date_showtimes( $args = array() ) {
		global $movies;
		
		$defaults = array(
			'post_id'				=>	0,
			'date'					=> 	date_i18n( 'Ymd', time() + get_option( 'gmt_offset' ) * 3600, false ),
			'data_format'			=>	'array',
			'display_format' 		=> 	get_option('time_format'),
			'upcoming_times_only'	=>	true
		);
		$args = wp_parse_args( $args, $defaults );
		
		$args['post_id'] 			= intval( $args['post_id'] );
		$args['theatre_id'] 		= intval( $movies->settings['theatre-id'] );

		if ( empty( $args['post_id'] ) ) {
			$queried_object = get_queried_object();
			//echo "queried_object<pre>"; print_r($queried_object); echo "</pre>";
			if ( ( is_single() ) 
 			  && ( $queried_object->post_type == 'movie' ) 
			  && ( isset( $queried_object->ID ) ) 
			  && ( !empty( $queried_object->ID ) ) ) {
				$args['post_id'] = $queried_object->ID;
			}
		}
		//echo "args<pre>"; print_r($args); echo "</pre>";
		
		if ( !empty( $args['post_id'] ) ) {
			$post_meta_date_key = 'Showtimes_'. $args['theatre_id'] .'_'. $args['date'] .'_times';
			//echo "post_meta_date_key[". $post_meta_date_key ."]<br />";
			$movie_showtimes = get_post_meta( $args['post_id'], $post_meta_date_key, true );
			if (!empty($movie_showtimes)) {
				//echo "movie_showtimes<pre>"; print_r($movie_showtimes); echo "</pre>";
				//die();
				
				//$movie_showtimes = array_keys( $movie_showtimes );
				if ($args['data_format'] == 'array') {
					$return_out = array();
				} else if ( $args['data_format'] == 'select_options' ) {
					$return_out = '';
				} else if ( $args['data_format'] == 'list_items' ) {
					$return_out = '';
				}
				
				$current_time_hhmm 		= date_i18n( 'Hi', time() + get_option( 'gmt_offset' ) * 3600, false );
				$current_date_yyyymmdd 	= date_i18n( 'Ymd', time() + get_option( 'gmt_offset' ) * 3600, false );
				
				foreach( $movie_showtimes as $time => $movie_showtimes_meta ) {
					$time_is_past = false;

					// Check of the date YYYYMMDD is past.
					if (( $args['date'] == $current_date_yyyymmdd) && ( $time < $current_time_hhmm )) {
						$time_is_past = true;
					}

					if (( $args['upcoming_times_only'] == true ) && ( $time_is_past == true )) continue;
					
						
					$display_date_time = date_i18n( $args['display_format'], strtotime( $args['date'] .' '. $time ), false );
					if ($args['data_format'] == 'array') {
						
						$return_out[$args['date'] . $time] = array_merge($movie_showtimes_meta, array(
							'date_display'	=>	$display_date_time,
							'url'			=>	$movie_showtimes_meta['url'],
							'time_is_past'	=>	($time_is_past ? true : false)
							) 
						);

					} else if ( $args['data_format'] == 'select_options' ) {
						$return_out .= '<option value="'. $movie_showtimes_meta['url'] .'">'. $display_date_time .'</option>';
					} else if ( $args['data_format'] == 'list_items' ) {
						$return_out .= '<li><a href="'. $movie_showtimes_meta['url'] .'">'. $display_date_time .'</a></li>';
					}
				}
				return $return_out;
			}
		}
	}
}

/**
 * Given a date (YYYYMMDD) will return a posts list of all movies for that date. If date is not provided will default to current date
 *
 * @since 1.0.0
 * @param args array using the following optional parameters:
 * 		date (required) - date in YYYYMMDD format.
 * 		display_format (optional) - Can be used to control the date format. If not provided the system date_format is used. 
 *		orderby - Default is orderby Title. Can be any orderby per the WordPress WP_Query options.
 *		order - Default is ASC. 
 * @return Array containing the standard Post object. In addition there will be two new elements - 
 *		Movie_meta - This element will contain all the post_meta key/value pairs like rating, runtime, actor list, director, etc.
 * 		Movie_showtimes - This will be an array of all showtimes for the given movie for the given date. The array will contain the displable showtime (example: 11:20 am, 3:30pm) along with the URL with the ScheduleID needed to purchase tickets for the specific TheatreID+MovieID+Showtime combination. 
 *
 */
if ( !function_exists( 'get_all_movies_for_date' ) ) {
	function get_all_movies_for_date( $args = array() ) {
		global $movies;
		
		$defaults = array(
			'date'						=> 	date_i18n( 'Ymd', time() + get_option( 'gmt_offset' ) * 3600, false ),
			'display_format' 			=> 	get_option( 'time_format' ),
			'orderby'					=>	'title',
			'order'						=>	'ASC',
			'post_status'				=>	'publish',
			'posts_per_page'			=>	3,
			'paged' 					=> 	get_query_var( 'paged' ),
			'data_format'				=>	'array',
			'upcoming_times_only'		=>	true,
			'coming_soon_only'			=>	false
		);
		$args = wp_parse_args( $args, $defaults );

		$args['post_type'] 			= 	'movie';
		$args['theatre_id'] 		= 	intval( $movies->settings['theatre-id'] );
		//$args['meta_key'] 			= 	'Showtimes_'. $args['theatre_id'] .'_'. $args['date'] .'_times';

		//echo "args<pre>"; print_r($args); echo "</pre>";

		$query_args = $args;
		unset( $query_args['display_format'] );

		$meta_query = array();

		if ($args['coming_soon_only'] == true) {
			
			$meta_query[] = array(
				'key'     	=> 'FromDate',
				'value'		=>	$args['date'],
				'compare' 	=> '>',
			);
			
			/*
			$meta_query[] = array(
				'key'     	=> 'Showtimes_'. $args['theatre_id'].'_dates',
				'compare' 	=> 'NOT EXISTS',
			);
			*/
		} else {
			$meta_query['relation'] = 'AND';
			$meta_query[] = array(
				'key'		=> 'Showtimes_'. $args['theatre_id'] .'_'. $args['date'] .'_times',
				'compare'	=> 'EXISTS'
			);
		}
		$query_args['meta_query'] = $meta_query;
		//echo "query_args<pre>"; print_r($query_args); echo "</pre>";
		//die();
		
		$movie_query = new WP_Query( $query_args );
	
		if ( !empty( $movie_query->posts ) ) {
			foreach( $movie_query->posts as $idx => $movie_post ) {
				$movie_post_meta = get_movie_post_meta( $movie_post->ID );
				if ( !empty( $movie_post_meta ) ) {
					$movie_query->posts[$idx]->Movie_meta = $movie_post_meta;
				}
				
				unset($movie_showtimes);

				//echo "movie_post->ID[". $movie_post->ID ."]<br />";
				
				if (!$args['coming_soon_only']) {
					$movie_showtimes = get_movie_date_showtimes( array(
							'post_id'				=>	$movie_post->ID,
							'date'					=>	$args['date'],
							'data_format'			=>	$args['data_format'], 
							'display_format'		=>	$args['display_format'],
							'upcoming_times_only'	=>	$args['upcoming_times_only']
						)
					);
					if ( !empty( $movie_showtimes ) ) {
						$movie_query->posts[$idx]->Movie_showtimes = $movie_showtimes;
					}

				} else {
					$movie_post_showtimes = get_movie_post_meta( $movie_post->ID, 'Showtimes_'. $args['theatre_id'] .'_dates' );
					if (!empty($movie_post_showtimes)) {
						$movie_first_showtime = array_slice( $movie_post_showtimes, 0, 1);
						//echo "[". $movie_post->ID ."] movie_first_showtime<pre>"; print_r($movie_first_showtime); echo "</pre>";
						$movie_showtimes = get_movie_date_showtimes( array(
								'post_id'				=>	$movie_post->ID,
								'date'					=>	$movie_first_showtime[0],
								'data_format'			=>	$args['data_format'], 
								'display_format'		=>	$args['display_format'],
								'upcoming_times_only'	=>	$args['upcoming_times_only']
							)
						);
						//echo "movie_showtimes<pre>"; print_r($movie_showtimes); echo "</pre>";
						
						if ( !empty( $movie_showtimes ) ) {
							$movie_query->posts[$idx]->Movie_showtimes = $movie_showtimes;
						}
					}
				}


			
				//$movie_dates =  get_movie_all_dates( $movie_id, 'array', $display_format );
				//if ( !empty( $movie_dates ) ) {
				//	$posts[$post_idx]->Movie_dates = $movie_dates;
				//}

				//echo "post<pre>"; print_r($posts[$post_idx]); echo "</pre>";
				//die();
			}
		}
		return $movie_query;
	}
}

/**
 * Given a post_id will return an array of all post_meta key/value pairs defined for the movie. This ca be called directly but is also called from get_all_movies_for_date() function 
 *
 * @since 1.0.0
 * @param args array using the following optional parameters:
 * 		post_id (required) - (integer) post_id of the movie post type.
 * 		meta_key (optional) - (string) To limmit the meta lookup to a single value you can pass in the meta_key and a single value will be returned. 
 * @uses We store the common meta_keys into the XML processor base. This way it is used here and when processing the movie data. Same meta fields. 
 * @return Array containing the key/value pairs for all post_meta fields for the specified post_id. Or if meta_key is provided as the optional second argument a single value is returned
 *
 */				
if ( !function_exists( 'get_movie_post_meta' ) ) {
	function get_movie_post_meta( $post_id = 0, $meta_key = '' ) {
		global $movies;
		
		$post_id = intval( $post_id );
		if ( !empty( $post_id ) ) {
			if ( empty( $meta_key ) ) {
					
				$meta_data = array();
				foreach( $movies->movies_xml_processor->movie_meta_keys as $key ) {
					$meta_data[$key] = get_post_meta( $post_id, $key, true );
				}
				return $meta_data;
			
			} else {
				return get_post_meta( $post_id, $meta_key, true );
			}
		}
	}
}
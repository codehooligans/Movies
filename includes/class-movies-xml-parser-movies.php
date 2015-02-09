<?php
if ( !class_exists( 'movies_xml_parser_movies' ) ) {
	class movies_xml_parser_movies extends movies_xml_parser_base {
		
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

			if ( !empty( $this->feed_xml_data->MoviesRow ) ) {
				
				//$bookings_object = $movies->movies_xml_processor->get_feeds_object( 'bookings' );
				//if ( is_object( $bookings_object ) ) { 

					foreach( $this->feed_xml_data->MoviesRow as $feed_idx => $feed ) {
					
						$feed = (array)$feed;
					
						foreach($feed as $key => $val) {
							//	echo "key[". $key ."] val[". $val ."][". var_dump($val) ."]<br />";
							if (!is_string($val)) {
								$feed[$key] = (string)$val;
							}
						}
					
						$MovieID = intval( $feed['MovieID'] );
					
						// As the Movie feed_xml_data is ALL movies not just those showing we check that the movie object (MovieID) 
						// is present within the 'advshowtimesdetail' feed data. 
						//if ( isset( $bookings_object->feed_xml_data[$MovieID] ) ) {

							list($start_date_mm, $start_date_dd, $start_date_yyyy) = explode('/', $feed['StartDate']);
							$feed['StartDate'] = $start_date_yyyy.$start_date_mm.$start_date_dd;
							$feed['Year'] = $start_date_yyyy;
							
							$movie_title = (string)$feed['MovieTitle'];

							if ( strpos( $movie_title, '3D ' ) !== false ) {
								$feed['is_3D'] = 'Yes';
							} else {
								$feed['is_3D'] = 'No';
							}

							// Do some other filtering on the movie title
							$feed['MovieTitle'] = $this->filter_title( $movie_title );

							$feed_xml_data[$MovieID] = $feed;
							//}
					}
					//}
				//echo "feed_xml_data<pre>"; print_r( $feed_xml_data ); echo "</pre>";
				//die();
				
				$this->feed_xml_data = $feed_xml_data;
			}
		}

		/**
		 * Utility function called from filter_feed_xml_data() to filter the Movie Title element. 
		 *
		 * @since 1.0.0
		 * @param (string) movie title
		 * @return (string) filtered movie title
		 */				
		function filter_title( $movie_title ) {
			
			// Examples of bad titles.
			//3D BIG HERO 6
			//3D BOOK OF LIFE, THE

			$movie_title = str_replace( ' alt-1', '', $movie_title );
			$movie_title = str_replace( ' alt-2', '', $movie_title );
			$movie_title = str_replace( '3D ', '', $movie_title );

			if ( strpos( $movie_title, ', THE' ) !== false ) {
				$movie_title = str_replace( ', THE', '', $movie_title );
				$movie_title = 'THE '. $movie_title;
			}
			
			$movie_title = strtolower( $movie_title );
			$movie_title = ucwords( $movie_title );
			
			return $movie_title;
		}
		
		/**
		 * For Movies after we process the primary XML data from the source we want to try and lookup the movie by IMDB ID or Title
		 * to retreive extra information like reatings, a better/large post image, trailer video(s)
		 *
		 * @since 1.0.0
		 * @param (string) movie title
		 * @return (string) filtered movie title
		 */				
		
		function remote_data_lookup( $feed ) {
			
			if ( ( isset( $feed['MovieSiteLink'] ) ) && ( !empty( $feed['MovieSiteLink'] ) ) ) {
				$url_parts = parse_url($feed['MovieSiteLink']);
				
				if ( $url_parts['host'] == 'www.imdb.com' ) {
					$imdbID = str_replace(array('/title/', '/'), '', $url_parts['path']);
					if ( !empty( $imdbID ) ) {
						$feed['imdbID'] = $imdbID;
						$feed = $this->movie_lookup_by_imdbid($feed);
						
					}
				} else {
					$feed = $this->movie_lookup_by_name($feed);
				}
			}
			return $feed;
		}
		
		function movie_lookup_by_imdbid( $feed ) {
		
			if ( ( isset( $feed['imdbID'] ) ) && ( !empty( $feed['imdbID'] ) ) ) {

				$movie_query_args = array('i' => $feed['imdbID']);
				//$movie_url = add_query_arg('i', $feed['imdbID'], 'http://www.omdbapi.com' );
				$feed = $this->processlookup_url( $feed, $movie_query_args );
			}
			return $feed;	
		}
		
		function movie_lookup_by_name( $feed ) {
			
			$movie_query_args = array();
			if ( ( isset( $feed['MovieTitle'] ) ) && ( !empty( $feed['MovieTitle'] ) ) ) {
				$movie_query_args['t'] = urlencode( $feed['MovieTitle'] );
			}

			//echo "StartDate[". $feed['StartDate'] ."]<br />";
			if ( ( isset( $feed['Year'] ) ) && ( !empty( $feed['Year'] ) ) ) {
				$movie_query_args['y'] = $feed['Year'];
			}
			
			$feed = $this->processlookup_url( $feed, $movie_query_args );

			return $feed;
		}

		function processlookup_url( $feed, $movie_query_args ) {

			if ( ( !empty( $feed ) ) && ( !empty( $movie_query_args ) ) ) {
				$movie_query_args = array_merge(
					array(
						'plot'		=>	'full',
						'tomatoes'	=>	'true'
					), 
					$movie_query_args);
					
				$movie_url = add_query_arg( $movie_query_args, 'http://www.omdbapi.com' );

				$movie_json = file_get_contents( $movie_url );
				if ( !empty( $movie_json ) ) {
					$movie_details = json_decode( $movie_json );
					$movie_details = (array)$movie_details;

					if ( $movie_details['Response'] == 'True' ) {

						//echo "movie_details<pre>"; print_r($movie_details); echo "</pre>";
						//die();
						
						//media_sideload_image( $url, $post_id, $description );


						// A simple way to map the returned fields to our existing structure. 
						$imdb_movie_keys = array(
							//'Title'				=>	'MovieTitle',
							'Rated'				=> 	'MovieRating',
							'Released'			=>	'StartDate',
							'Runtime'			=>	'MovieRunTime',
							'Director'			=>	'Director',
							'Actors'			=>	'ActorList',	
							'Plot'				=>	'MovieDescription',
							//'Poster'			=>	'MoviePosterLink',
							'imdbID'			=>	'imdbID',
						);


						foreach( $movie_details as $movie_key => $movie_val) {

							if ( $movie_val == 'N/A' ) {
								$movie_val = '';
							}
							
							if ( isset( $imdb_movie_keys[$movie_key] ) ) {
								$ts_key = $imdb_movie_keys[$movie_key];
								
								if (($ts_key == 'MovieTitle') || ($ts_key == 'MoviePosterLink')) {
									$feed[$ts_key] = $movie_val;
								} else if ( ( !isset( $feed[$ts_key] ) ) || ( empty( $feed[$ts_key] ) ) ) {
									$feed[$ts_key] = $movie_val;
								}
							} else {
								$feed[$movie_key] = $movie_val;
							}
						}
					}
				}
			}
			
			return $feed;
		}
		
		function get_movie_trailer( $feed ) {
			
			if ( ( isset( $feed['imdbID'] ) ) && ( !empty( $feed['imdbID'] ) ) ) {
				$imdbID_filtered = str_replace('tt', '', $feed['imdbID']);
				
				$movie_query_args = array(
					'imdb'		=>	$imdbID_filtered,
					'count'		=>	1,
					'width'		=>	apply_filters('movie_video_tailer_width', '480'),
					'credit'	=>	'no',
				);

				$movie_url = add_query_arg( $movie_query_args, 'http://api.traileraddict.com' );
				$movie_trailers = simplexml_load_file( $movie_url ); 
				if ( ( !empty( $movie_trailers ) ) && ( isset( $movie_trailers->trailer ) ) ) {
					foreach($movie_trailers->trailer as $trailer) { 
						
						if ( ( isset( $trailer->embed ) ) && ( !empty( $trailer->embed ) ) ) {
							 $feed['MovieTrailerURL'] = (string)$trailer->embed;
						}
						//$feed['MovieTrailer'] = (array)$trailer;
						
						break;
					}
				}
			}
			
			return $feed;
		}
		
		function download_movie_image($post_id, $feed) {
			//echo "post_id[". $post_id ."]<br />";
			//echo "feed<pre>"; print_r($feed); echo "</pre>";
			//die();
			
			if ( ( !isset( $feed['imdbID'] ) ) || ( empty( $feed['imdbID'] ) ) ) return;
			if ( ( !isset( $feed['Poster'] ) ) || ( empty( $feed['Poster'] ) ) ) return;

			$poster_thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true);
			if ( !empty( $poster_thumbnail_id ) ) {
				$post_thumbnail_image = wp_get_attachment_image_src( $poster_thumbnail_id ); 
				if ( !empty( $post_thumbnail_image ) ) {
					return;
				}
			}
			
			$query_args = array(
				'post_type'		=>	'attachment',
				'name'			=>	$feed['imdbID']
			);
			$movie_query = new WP_Query( $query_args );
			//echo "movie_query<pre>"; print_r($movie_query); echo "</pre>";
			
			if (( isset( $movie_query->posts ) ) && ( count( $movie_query->posts ) ) ) {
				foreach( $movie_query->posts as $post ) {
					update_post_meta( $post_id, '_thumbnail_id', $post->ID);

					// we are done here. 
					return;
				}
			}
			
			// If here we didn't find an existing thumbnail_id OR find an image with the movie_ID slug. 
			// So not download the remote image to the local storage.			
			$file_array = array();
			$file_array['name'] = $feed['imdbID'] .'.'. pathinfo($feed['Poster'], PATHINFO_EXTENSION);

			// Download file to temp location.
			$file_array['tmp_name'] = download_url( $feed['Poster'] );

			// If error storing temporarily, return the error.
			if ( is_wp_error( $file_array['tmp_name'] ) ) {
				//echo "file_array<pre>"; print_r($file_array); echo "</pre>";
				//die();
				return;
			}

			// Do the validation and storage stuff.
			//echo "file_array<pre>"; print_r($file_array); echo "</pre>";
			if (!function_exists('media_handle_sideload')) {
				require_once(ABSPATH . 'wp-admin/includes/media.php');
			}
			$id = media_handle_sideload( $file_array, $post_id, $feed['imdbID'] );
			//echo "id<pre>"; print_r($id); echo "</pre>";
			//die();

			// If error storing permanently, unlink.
			if ( is_wp_error( $id ) ) {
				@unlink( $file_array['tmp_name'] );
				//echo "id<pre>"; print_r($id); echo "</pre>";
				//die();
				return;
			}
			
			update_post_meta( $post_id, '_thumbnail_id', $id);
			
		}
	}
}


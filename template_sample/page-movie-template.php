<?php
/*
Template Name: Movies Listing
Template Name:
 */
?>
<?php
// Get the Page permalink. Used later when displaying date links
$page_permalink = get_permalink();

// Used for compares down in the template
$current_date = date_i18n( 'Ymd', time() + get_option( 'gmt_offset' ) * 3600, false );

$movie_query_args = array();
if ( ( isset( $_GET['date'] ) ) && ( !empty( $_GET['date'] ) ) ) {
	$movie_query_args['date']	=	esc_attr( $_GET['date'] );
	if ( $movie_query_args['date'] < $current_date ) {
		wp_redirect( remove_query_arg( 'date', $page_permalink) );
		die();
	}
} else {
	$movie_query_args['date'] = $current_date;
}
?>
<?php get_header(); ?>

<div id="main-content" class="main-content">
	<div id="primary" class="content-area">
		<div id="content" class="site-content" role="main">

			<?php			

			?><div class="movie-header"><?php

			if (function_exists('get_theatre_info')) {
				$theatre_info = get_theatre_info();
				if (!empty($theatre_info)) {
					//echo "theatre_info<pre>"; print_r($theatre_info); echo "</pre>";
					?>
					<div class="movie-theatre-info">
					<p>Theatre Name: <?php echo $theatre_info['TheatreName'] ?> [<a href="<?php echo $theatre_info['TicketingURL'] ?>">link</a>]<br />
					Address: <?php echo  $theatre_info['TheatreAddress'] ?><br />
					Phone: <?php echo $theatre_info['PhoneInfo'] ?>  Movie Line: <?php echo $theatre_info['PhoneMovieLine'] ?><br />
					Screens: <?php echo $theatre_info['TheatreScreens'] ?></p>
					</div>
					<?php
				}
			}
			//echo "movie_query_date[". $movie_query_args['date'] ."]<br />";
			?><h3>Showing Movies for date: <?php echo date_i18n( 'D M j, Y', strtotime( $movie_query_args['date'] ), true ) ?></h3><?php
			if (function_exists('get_all_dates_all_movies')) {
				$all_movie_dates = get_all_dates_all_movies(array('display_format' => 'm/d/Y'));
				if ( !empty( $all_movie_dates ) ) {
					//echo "all_movie_dates<pre>"; print_r( $all_movie_dates ); echo "</pre>";
					
					?><div class="movie-all-dates">All Dates: <ul class="movie-all-dates-list"><?php

					foreach( $all_movie_dates as $date_yyyymmdd => $display_date ) {
						if ( $date_yyyymmdd == $movie_query_args['date'] ) {
							?><li class="movie-all-date movie-all-date-current"><?php echo $display_date ?>,</li><?php

						} else {
							?><li class="movie-all-date"><a href="<?php echo add_query_arg('date', $date_yyyymmdd, $page_permalink); ?>"><?php echo $display_date ?></a>,</li><?php
						}
					}
					?></ul></div><?php
				}
			}
			?></div><?php
			
			if (function_exists('get_all_movies_for_date')) {
				
				// Here we can cntrol how the showtimes are displays. Passing 'true' will limit the returned showtimes 
				// item to be future times. PAssing false will return all showtimes for the item. And we handle the 
				// strike out via our display logic on this template. 
				$movie_query_args['upcoming_only'] = false;
				
				$movies_query = get_all_movies_for_date( $movie_query_args );
				
				//echo "movies_query<pre>"; print_r($movies_query); echo "</pre>";
				
				//global $post;
				while ( $movies_query->have_posts() ) : $movies_query->the_post();
					//echo "movie<pre>"; print_r($post); echo "</pre>";
					
					?>
					<div class="movie-details">
						<h3 class="movie-title"><?php echo $post->post_title ?><?php
							if ( ( isset( $post->Movie_meta['MovieID'] ) ) && ( !empty( $post->Movie_meta['MovieID'] ) ) ) { 
								echo " (". $post->Movie_meta['MovieID'] .")";
							}							
							?></h3>
						<?php 
							$movie_content = '';
							if ( ( isset( $post->Movie_meta['MoviePosterLink'] ) ) && ( !empty( $post->Movie_meta['MoviePosterLink'] ) ) ) { 
								$movie_content .= '<img class="movie-image" src="'. $post->Movie_meta['MoviePosterLink'] .'" alt="'. $post->post_title .'" />';
							} 

							// Display the movie description
							if ( ( isset( $post->Movie_meta['MovieDescription'] ) ) && ( !empty( $post->Movie_meta['MovieDescription'] ) ) ) {
								$post->Movie_meta['MovieDescription'] = apply_filters( 'the_content', $post->Movie_meta['MovieDescription'] );
								$movie_content .= str_replace( ']]>', ']]&gt;', $post->Movie_meta['MovieDescription'] );
							}
							echo $movie_content
						?>
						<?php
							if ( ( isset( $post->Movie_meta['is_3D'] ) ) && ( !empty( $post->Movie_meta['is_3D'] ) ) ) { 
								echo 'Is 3D: '. $post->Movie_meta['is_3D'] .'<br />';
							}
							if ( ( isset( $post->Movie_meta['MovieRunTime'] ) ) && ( !empty( $post->Movie_meta['MovieRunTime'] ) ) ) { 
								echo 'Run time: '. $post->Movie_meta['MovieRunTime'] .'<br />';
							}
							if ( ( isset( $post->Movie_meta['MovieRating'] ) ) && ( !empty( $post->Movie_meta['MovieRating'] ) ) ) { 
								echo 'Rating: '. $post->Movie_meta['MovieRating'];
								if ( ( isset( $post->Movie_meta['MovieRatingDesc'] ) ) && ( !empty( $post->Movie_meta['MovieRatingDesc'] ) ) ) { 
									echo ' - '. $post->Movie_meta['MovieRatingDesc']; 
								}
								echo '</br />';
							}
							if ( ( isset( $post->Movie_meta['Director'] ) ) && ( !empty( $post->Movie_meta['Director'] ) ) ) { 
								echo 'Director: '. $post->Movie_meta['Director'] .'<br />';
							}
							if ( ( isset( $post->Movie_meta['ActorList'] ) ) && ( !empty( $post->Movie_meta['ActorList'] ) ) ) { 
								echo 'Actors: '. $post->Movie_meta['ActorList'] .'<br />';
							}
							if ( ( isset( $post->Movie_meta['MovieSiteLink'] ) ) && ( !empty( $post->Movie_meta['MovieSiteLink'] ) ) ) { 
								echo '<a target="_blank" href="'. $post->Movie_meta['MovieSiteLink'] .'">IMDB</a><br />';
							}
							
						?>
						
						<div class="movie-showtimes">Showtimes: <?php
						// Display the showtimes. 
						if ( ( isset( $post->Movie_showtimes ) ) && ( !empty( $post->Movie_showtimes ) ) ) {
							//echo "Movie_showtimes<pre>"; print_r($post->Movie_showtimes); echo "</pre>";
							?><ul class="movie-showtimes-list"><?php
							foreach( $post->Movie_showtimes as $showtime ) {
								?><li class="movie-showtime"><a title="<?php echo $showtime['SeatsRemaining'].' seats remaining'; ?>" href="<?php echo $showtime['url'] ?>"><?php if ($showtime['time_is_past']) { echo '<strike>'; } ?><?php echo $showtime['date_display'] ?></a><?php if ($showtime['time_is_past']) { echo '</strike>'; } ?>,</li><?php
							}
							?></ul><?php
						} else {
							echo "No other show time today";
							
						}
						?></div>
						
						<div class="movie-dates">Other Show Dates: <?php
							if ( function_exists( 'get_movie_all_dates' ) ) {
								$movie_dates = get_movie_all_dates(array(
										'post_id'		=>	$post->ID,
										'display_format'	=>	'm/d/Y'
									)
								);

								if ( !empty( $movie_dates ) ) {

									?><ul class="movie-dates-list"><?php

									foreach( $movie_dates as $date_yyyymmdd => $display_date ) {
										if ( $date_yyyymmdd == $movie_query_args['date'] ) {
											?><li class="movie-date movie-date-current"><?php echo $display_date ?>,</li><?php

										} else {
											?><li class="movie-date"><a href="<?php echo add_query_arg('date', $date_yyyymmdd, $page_permalink); ?>"><?php echo $display_date ?></a>,</li><?php
										}
									}
									?></ul><?php
								}
							}
						?></div>						
					</div>
					<?php
						
				endwhile;
				
				?><div class="movie-footer"><div class="movie-pager"><?php
				$big = 999999999; // need an unlikely integer
				echo paginate_links( array(
					'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format' => '?paged=%#%',
					'current' => max( 1, get_query_var('paged') ),
					'total' => $movies_query->max_num_pages
				) );
				?></div></div>

				<style>
				
				.movie-header, .movie-details, .movie-footer {
					clear: both;
					float: left;
					margin-bottom: 20px;
				}
				
				.movie-details, .movie-theatre-info {
					padding-left: 20px;
				}
				.movie-details img.movie-image { float: left; margin-right: 10px; margin-bottom: 10px; }
				.movie-details .movie-description, .movie-details .movie-description p { float: left; }

				.movie-details ul.movie-showtimes-list, 
				.movie-details ul.movie-dates-list, 
				.movie-details ul.movie-all-dates-list {
					list-style: none; margin: 0; padding: 0;
				}

				.movie-details ul.movie-showtimes-list li, 
				.movie-details ul.movie-dates-list li, 
				.movie-header ul.movie-all-dates-list li {
					list-style: none; margin: 0 5px 0 0; padding: 0; float: left;
				}
				
				.movie-details ul.movie-dates-list li.movie-date-current, 
				.movie-header ul.movie-all-dates-list li.movie-all-date-current,
				.movie-footer .movie-pager span.current {
					color: red; font-weight: bold;
				}
				.movie-details .movie-showtimes, .movie-details .movie-dates, .movie-header .movie-all-dates {
					clear: both;
				}
				
				.movie-header {
					border-bottom: 1px solid #CCC;
					width: 100%;
				}
				
				.movie-header h3, .movie-header .movie-all-dates {
					margin-left: 20px;
				}

				.movie-footer {
					border-top: 1px solid #CCC;
					width: 100%;
				}
				
				.movie-footer .movie-pager {
					margin-top: 10px;
					margin-left: 20px;
				}
				
				</style>
				<?php
				
			} else {

				// Start the Loop.
				while ( have_posts() ) : the_post();

					// Include the page content template.
					get_template_part( 'content', 'page' );

					// If comments are open or we have at least one comment, load up the comment template.
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
				endwhile;
			}
			?>

		</div><!-- #content -->
	</div><!-- #primary -->
	<?php get_sidebar( 'content' ); ?>
</div><!-- #main-content -->

<?php
get_sidebar();
get_footer();

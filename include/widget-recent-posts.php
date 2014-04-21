<?php

if(!class_exists('WP_Widget_Recent_Posts')){
	require_once( ABSPATH . '/wp-includes/default-widgets.php' );
}

/*
 * obliged to rewrite the whole functionnality to have a language dependant cache key
 *
 * @since 1.5
 */
class PLL_Widget_Recent_Posts extends WP_Widget_Recent_Posts {

	/*
	 * displays the widget
	 * modified version of the parent function to call to use a language dependant cache key
	 *
	 * @since 1.5
	 *
	 * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
	 * @param array $instance The settings for the particular instance of the widget
	 */
	function widget($args, $instance) {
		$cache = wp_cache_get('widget_recent_posts_' . pll_current_language(), 'widget'); #modified#

		if ( !is_array($cache) )
			$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}

		ob_start();
		extract($args);

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Recent Posts' );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 10;
		if ( ! $number )
 			$number = 10;
		$show_date = isset( $instance['show_date'] ) ? $instance['show_date'] : false;

		$r = new WP_Query( apply_filters( 'widget_posts_args', array( 'posts_per_page' => $number, 'no_found_rows' => true, 'post_status' => 'publish', 'ignore_sticky_posts' => true ) ) );
		if ($r->have_posts()) :
?>
		<?php echo $before_widget; ?>
		<?php if ( $title ) echo $before_title . $title . $after_title; ?>
		<ul>
		<?php while ( $r->have_posts() ) : $r->the_post(); ?>
			<li>
				<a href="<?php the_permalink(); ?>"><?php get_the_title() ? the_title() : the_ID(); ?></a>
			<?php if ( $show_date ) : ?>
				<span class="post-date"><?php echo get_the_date(); ?></span>
			<?php endif; ?>
			</li>
		<?php endwhile; ?>
		</ul>
		<?php echo $after_widget; ?>
<?php
		// Reset the global $the_post as this query will have stomped on it
		wp_reset_postdata();

		endif;

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set('widget_recent_posts_' . pll_current_language(), $cache, 'widget'); #modified#
	}

	/*
	 * empties the cache
	 *
	 * @since 1.5
	 */
	function flush_widget_cache() {
		foreach (pll_languages_list() as $slug) #added#
			wp_cache_delete('widget_recent_posts_' . $slug, 'widget'); #modified#
	}
}

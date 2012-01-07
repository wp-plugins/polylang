<?php

// the language switcher widget
class Polylang_Widget extends WP_Widget {
	function __construct() {
		parent::__construct('polylang', __('Language Switcher', 'polylang'), array( 'description' => __( 'Displays a language switcher', 'polylang')));
	}

	// displays the widget
	function widget($args, $instance) {
		global $polylang;

		// prevents the function to be called from admin side where $polylang is not defined
		if (!isset($polylang))
			return; 

		extract($args);
		extract($instance);

		$title = apply_filters('widget_title', $title, $instance, $this->id_base);

						
		echo "$before_widget\n";
		if ($title)
			echo $before_title . $title . $after_title;
		if (!$dropdown)
			echo "<ul>\n";
		$polylang->the_languages($instance);
		if (!$dropdown)
			echo "</ul>\n";
		echo "$after_widget\n";

		// javascript to switch the language when using a dropdown list
		if ($dropdown) {			
			foreach ($polylang->get_languages_list() as $language) {
				$url = $force_home ? $polylang->get_home_url($language) : $polylang->get_translation_url($language);
				$urls[] = '"'.esc_js($language->slug).'":"'.esc_url($url).'"';
			}

			$urls = implode(',', $urls);

			$js = "
				<script type='text/javascript'>
					var urls = {{$urls}};
					var d = document.getElementById('lang_choice');
					d.onchange = function() {
						for (var i in urls) {
							if (this.value == i)
								location.href = urls[i];
						}
					}
				</script>";

			echo $js;
		}
	}

	// updates the widget options
	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags($new_instance['title']);
		foreach ( array('show_names', 'show_flags','dropdown', 'force_home') as $key)
			$instance[$key] = !empty($new_instance[$key]) ? 1 : 0;

		return $instance;
	}

	// displays the widget form
	function form($instance) {
		// default values
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'show_names' => 1, 'show_flags' => 0, 'dropdown' => 0, 'force_home' => 0) ); 

		// title
		$title = sprintf('<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s" /></p>',
			$this->get_field_id('title'), __('Title:', 'polylang'), $this->get_field_name('title'), esc_attr($instance['title']));
	
		$widget_options = array (
			'show_names' => __('Displays language names', 'polylang'),
			'show_flags' => __('Displays flags', 'polylang'),
			'dropdown' => __('Displays as dropdown', 'polylang'),
			'force_home' => __('Forces link to front page', 'polylang')
		);

		$fields = '';			
		foreach ($widget_options as $key=>$str)	
			$fields .= sprintf('<input type="checkbox" class="checkbox" id="%1$s" name="%2$s" %3$s /> <label for="%1$s">%4$s</label><br />',
				$this->get_field_id($key), $this->get_field_name($key), $instance[$key] ? 'checked="checked"' : '', esc_html($str));

		echo $title.'<p>'.$fields.'</p>';
	}
}
?>

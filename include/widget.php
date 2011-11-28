<?php

class Polylang_Widget extends WP_Widget {
	function __construct() {
		$widget_ops = array( 'description' => __( 'Displays a language switcher', 'polylang') );
		parent::__construct('polylang', __('Language Switcher', 'polylang'), $widget_ops);
	}

	// displays the widget
	function widget($args, $instance) {
		extract($args);
		extract($instance);

		$title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);

		echo "$before_widget\n";
		if ($title)
			echo $before_title . $title . $after_title;
		do_action('the_languages', $instance);
		echo "$after_widget\n";

		// javascript to switch the language when using a dropdown list
		// keep the things simple for now as we switch to the posts page
		if ($dropdown) {
			$url = home_url('?lang=');
			$home_url = get_option('home');
			$options = get_option('polylang');
			$default = $options['hide_default'] ? $options['default_lang'] :  '';

			$js = "
				<script type='text/javascript'>
					var d = document.getElementById('lang_choice');
					d.onchange = function() {
						if (this.value == '$default')
							location.href = '$home_url';
						else 
							location.href ='$url'+this.value;
					}
				</script>";

			echo $js;
		}
	}

	// updates the widget options
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['show_names'] = !empty($new_instance['show_names']) ? 1 : 0;
		$instance['show_flags'] = !empty($new_instance['show_flags']) ? 1 : 0;
		$instance['dropdown'] = !empty($new_instance['dropdown']) ? 1 : 0;

		return $instance;
	}

	// displays the widget form
	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'show_names' => 1, 'show_flags' => 0, 'dropdown' => 0) ); // default values

		// title
		$id = $this->get_field_id('title');
		$name = $this->get_field_name('title');
		$title = sprintf('<p><label for="%s">%s</label><input class="widefat" id="%s" name="%s" type="text" value="%s" /></p>',
			$id, __('Title:', 'polylang'), $id, $name, esc_attr($instance['title']));

		// language names checkbox
		$id = $this->get_field_id('show_names');
		$name = $this->get_field_name('show_names');
		$fields = sprintf('<input type="checkbox" class="checkbox" id="%s" name="%s"%s /> <label for="%s">%s</label><br />',
			$id, $name, $instance['show_names'] ? 'checked="checked"' : '', $id, __('Display language names', 'polylang'));

		// flags checkbox
		$id = $this->get_field_id('show_flags');
		$name = $this->get_field_name('show_flags');
		$fields .= sprintf('<input type="checkbox" class="checkbox" id="%s" name="%s"%s /> <label for="%s">%s</label><br />',
			$id, $name, $instance['show_flags'] ? 'checked="checked"' : '', $id, __('Display flags', 'polylang'));

		// dropdown checkbox
		$id = $this->get_field_id('dropdown');
		$name = $this->get_field_name('dropdown');
		$fields .= sprintf('<input type="checkbox" class="checkbox" id="%s" name="%s"%s /> <label for="%s">%s</label><br />',
			$id, $name, $instance['dropdown'] ? 'checked="checked"' : '', $id, __('Display as dropdown', 'polylang'));

		echo $title.'<p>'.$fields.'</p>';
	}

} // Class Polylang_Widget

?>

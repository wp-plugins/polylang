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

		echo "$before_widget\n";
		do_action('the_languages', $instance);
		echo "$after_widget\n";

		// javascript to switch the language when using a dropdown list
		// keep the things simple for now as we switch to the posts page
		if ($dropdown) {
			$url = home_url('?lang=');
			$js = "
				<script type='text/javascript'>
					var d = document.getElementById('lang_choice');
					d.onchange = function() {location.href ='$url'+this.value;}
				</script>";

			echo $js;
		}
	}

	// updates the widget options
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['show_names'] = !empty($new_instance['show_names']) ? 1 : 0;
		$instance['show_flags'] = !empty($new_instance['show_flags']) ? 1 : 0;
		$instance['dropdown'] = !empty($new_instance['dropdown']) ? 1 : 0;

		return $instance;
	}

	// displays the widget form
	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'show_names' => 1, 'show_flags' => 0, 'dropdown' => 0) ); // default values

		// language names checkbox
		$id = $this->get_field_id('show_names');
		$name = $this->get_field_name('show_names');
		$output = sprintf('<input type="checkbox" class="checkbox" id="%s" name="%s"%s /> <label for="%s">%s</label><br />',
			$id, $name, $instance['show_names'] ? 'checked="checked"' : '', $id, __('Display language names', 'polylang'));

		// flags checkbox
		$id = $this->get_field_id('show_flags');
		$name = $this->get_field_name('show_flags');
		$output .= sprintf('<input type="checkbox" class="checkbox" id="%s" name="%s"%s /> <label for="%s">%s</label><br />',
			$id, $name, $instance['show_flags'] ? 'checked="checked"' : '', $id, __('Display flags', 'polylang'));

		// dropdown checkbox
		$id = $this->get_field_id('dropdown');
		$name = $this->get_field_name('dropdown');
		$output .= sprintf('<input type="checkbox" class="checkbox" id="%s" name="%s"%s /> <label for="%s">%s</label><br />',
			$id, $name, $instance['dropdown'] ? 'checked="checked"' : '', $id, __('Display as dropdown', 'polylang'));

		echo '<p>'.$output.'</p>';
	}

} // Class Polylang_Widget

?>

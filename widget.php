<?php

class Polylang_Widget extends WP_Widget {
	function __construct() {
		$widget_ops = array( 'description' => __( 'Displays a language switcher', 'polylang') );
		parent::__construct('polylang', __('Language Switcher', 'polylang'), $widget_ops);
	}

	function widget($args, $instance) {
		extract( $args );

		echo "$before_widget\n";
		do_action('the_languages');
		echo "$after_widget\n";
	}

} // Class Polylang_Widget

?>

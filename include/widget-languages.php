<?php

/*
 * the language switcher widget
 *
 * @since 0.1
 */
class PLL_Widget_Languages extends WP_Widget {

	/*
	 * constructor
	 *
	 * @since 0.1
	 */
	function __construct() {
		parent::__construct('polylang', __('Language Switcher', 'polylang'), array( 'description' => __( 'Displays a language switcher', 'polylang')));
	}

	/*
	 * displays the widget
	 *
	 * @since 0.1
	 *
	 * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
	 * @param array $instance The settings for the particular instance of the widget
	 */
	function widget($args, $instance) {
		global $polylang;
		if (!(isset($polylang) && $polylang->model->get_languages_list() && $list = pll_the_languages(array_merge($instance, array('echo' => 0)))))
			return;

		extract($args);
		extract($instance);

		echo "$before_widget\n";
		if ($title = apply_filters('widget_title', $title, $instance, $this->id_base))
			echo $before_title . $title . $after_title;
		echo $dropdown ? $list : "<ul>\n" . $list . "</ul>\n";
		echo "$after_widget\n";

		// javascript to switch the language when using a dropdown list
		if ($dropdown) {
			foreach ($polylang->model->get_languages_list() as $language) {
				$url = $force_home || ($url = $polylang->links->get_translation_url($language)) == null ? $polylang->links->get_home_url($language) : $url;
				$urls[] = '"'.esc_js($language->slug).'":"'.esc_url($url).'"';
			}

			$urls = implode(',', $urls);

			$js = "
				<script type='text/javascript'>
					//<![CDATA[
					var urls = {{$urls}};
					var d = document.getElementById('lang_choice');
					d.onchange = function() {
						for (var i in urls) {
							if (this.value == i)
								location.href = urls[i];
						}
					}
					//]]>
				</script>";

			echo $js;
		}
	}

	/*
	 * updates the widget options
	 *
	 * @since 0.4
	 *
	 * @param array $new_instance New settings for this instance as input by the user via form()
	 * @param array $old_instance Old settings for this instance
	 * @return array Settings to save or bool false to cancel saving
	 */
	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags($new_instance['title']);
		foreach (array_keys(PLL_Switcher::get_switcher_options('widget')) as $key)
			$instance[$key] = !empty($new_instance[$key]) ? 1 : 0;

		return $instance;
	}

	/*
	 * displays the widget form
	 *
	 * @since 0.4
	 *
	 * @param array $instance Current settings
	 */
	function form($instance) {
		// default values
		$instance = wp_parse_args( (array)$instance, array_merge(array('title' => ''), PLL_Switcher::get_switcher_options('widget', 'default')) );

		// title
		$title = sprintf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s" /></p>',
			$this->get_field_id('title'),
			__('Title:', 'polylang'),
			$this->get_field_name('title'),
			esc_attr($instance['title'])
		);

		$fields = '';
		foreach (PLL_Switcher::get_switcher_options('widget') as $key => $str)
			$fields .= sprintf(
				'<input type="checkbox" class="checkbox" id="%1$s" name="%2$s" %3$s /> <label for="%1$s">%4$s</label><br />',
				$this->get_field_id($key),
				$this->get_field_name($key),
				$instance[$key] ? 'checked="checked"' : '',
				esc_html($str)
			);

		echo $title.'<p>'.$fields.'</p>';
	}
}

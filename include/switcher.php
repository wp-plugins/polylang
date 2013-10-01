<?php

/*
 * a class to display a language switcher on frontend
 *
 * @since 1.2
 */
class PLL_Switcher {

	/*
	 * returns options available for the language switcher (menu or widget)
	 *
	 * @since 0.7
	 */
	static public function get_switcher_options($type = 'widget', $key ='string') {
		$options = array(
			'show_names'   => array('string' => __('Displays language names', 'polylang'), 'default' => 1),
			'show_flags'   => array('string' => __('Displays flags', 'polylang'), 'default' => 0),
			'force_home'   => array('string' => __('Forces link to front page', 'polylang'), 'default' => 0),
			'hide_current' => array('string' => __('Hides the current language', 'polylang'), 'default' => 0),
		);

		if ($type != 'menu')
			$options['dropdown'] = array('string' => __('Displays as dropdown', 'polylang'), 'default' => 0);

		return array_map(create_function('$v', "return \$v['$key'];"), $options);
	}

	/*
	 * returns the language elements for use in a walker
	 *
	 * @since 1.2
	 */
	protected function get_elements($links, $args) {
		extract($args);

		foreach ($links->model->get_languages_list(array('hide_empty' => $hide_if_empty)) as $language) {
			$id = (int) $language->term_id;
			$slug = $language->slug;
			$classes = array('lang-item', 'lang-item-' . esc_attr($id), 'lang-item-' . esc_attr($slug));

			if (pll_current_language() == $slug) {
				if ($hide_current)
					continue; // hide current language
				else
					$classes[] = 'current-lang';
			}

			$url = $post_id !== null && ($tr_id = $links->model->get_post($post_id, $language)) ? get_permalink($tr_id) :
				($post_id === null && !$force_home ? $links->get_translation_url($language) : null);

			if (empty($url))
				$classes[] = 'no-translation';

			$url = apply_filters('pll_the_language_link', $url, $slug, $language->locale);

			// hide if no translation exists
			if (empty($url) && $hide_if_no_translation)
				continue;

			$url = empty($url) ? $links->get_home_url($language) : $url ; // if the page is not translated, link to the home page

			$name = $show_names || !$show_flags || $raw ? esc_html($display_names_as == 'slug' ? $slug : $language->name) : '';
			$flag = $raw && !$show_flags ? $language->flag_url : ($show_flags ? $language->flag : '');

			$out[] = compact('id', 'slug', 'name', 'url', 'flag', 'current_lang', 'no_translation', 'classes');
		}
		return empty($out) ? array() : $out;

	}

	/*
	 * displays a language switcher
	 * or returns the raw elements to build a custom language switcher
	 *
	 * @since 0.1
	 */
	public function the_languages($links, $args = '') {
		$defaults = array(
			'dropdown'               => 0, // display as list and not as dropdown
			'echo'                   => 1, // echoes the list
			'hide_if_empty'          => 1, // hides languages with no posts (or pages)
			'menu'                   => 0, // not for nav menu (this argument is deprecated since v1.1.1)
			'show_flags'             => 0, // don't show flags
			'show_names'             => 1, // show language names
			'display_names_as'       => 'name', // valid options are slug and name
			'force_home'             => 0, // tries to find a translation
			'hide_if_no_translation' => 0, // don't hide the link if there is no translation
			'hide_current'           => 0, // don't hide current language
			'post_id'                => null, // if not null, link to translations of post defined by post_id
			'raw'                    => 0, // set this to true to build your own custom language switcher
		);
		$args = wp_parse_args($args, $defaults);
		$elements = $this->get_elements($links, $args);

		if ($args['raw'])
			return $elements;

		$out = $args['dropdown'] ? (new PLL_Walker_Dropdown)->walk($elements, $args) : (new PLL_Walker_List)->walk($elements, $args);
		$out = apply_filters('pll_the_languages', $out, $args);

		if ($args['echo'])
			echo $out;
		return $out;
	}
}

/*
 * displays a language list
 *
 * @since 1.2
 */
class PLL_Walker_List extends Walker {
	var $db_fields = array ('parent' => 'parent', 'id' => 'id');

	/*
	 * outputs one element
	 *
	 * @since 1.2
	 */
	 function start_el( &$output, $element, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$output .= sprintf(
			"\t".'<li class="%s"><a hreflang="%s" href="%s">%s</a></li>'."\n",
			implode(' ', $element->classes),
			esc_attr($element->slug),
			esc_url($element->url),
			$args['show_flags'] && $args['show_names'] ? $element->flag.'&nbsp;'.$element->name : $element->flag.$element->name
		);
	}

	/*
	 * overrides Walker::display_element as it expects an object with a parent property
	 *
	 * @since 1.2
	 */
	function display_element( $element, &$children_elements, $max_depth, $depth = 0, $args, &$output ) {
		$element = (object) $element; // make sure we have an object
		$element->parent = $element->id = 0; // don't care about this
		parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );
	}

	/*
	 * overrides Walker:walk to set depth argument
	 *
	 * @since 1.2
	 */
	function walk($elements, $args = array()) {
		return parent::walk($elements, -1, $args);
	}
}

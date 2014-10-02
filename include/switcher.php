<?php

/*
 * a class to display a language switcher on frontend
 *
 * @since 1.2
 */
class PLL_Switcher {

	/*
	 * returns options available for the language switcher - menu or widget
	 * either strings to display the options or default values
	 *
	 * @since 0.7
	 *
	 * @param string $type optional either 'menu' or 'widget', defaults to 'widget'
	 * @param string $key optional either 'string' or 'default', defaults to 'string'
	 * @return array list of switcher options srings or default values
	 */
	static public function get_switcher_options($type = 'widget', $key ='string') {
		$options = array(
			'show_names'   => array('string' => __('Displays language names', 'polylang'), 'default' => 1),
			'show_flags'   => array('string' => __('Displays flags', 'polylang'), 'default' => 0),
			'force_home'   => array('string' => __('Forces link to front page', 'polylang'), 'default' => 0),
			'hide_current' => array('string' => __('Hides the current language', 'polylang'), 'default' => 0),
		);

		if ($type != 'menu')
			$options = array('dropdown' => array('string' => __('Displays as dropdown', 'polylang'), 'default' => 0)) + $options;

		return array_map(create_function('$v', "return \$v['$key'];"), $options);
	}

	/*
	 * get the language elements for use in a walker
	 *
	 * list of parameters accepted in $args:
	 * @see PLL_Switcher::the_languages
	 *
	 * @since 1.2
	 *
	 * @param object $links instance of PLL_Frontend_Links
	 * @param array $args
	 * @return array
	 */
	protected function get_elements($links, $args) {
		foreach ($links->model->get_languages_list(array('hide_empty' => $args['hide_if_empty'])) as $language) {
			$id = (int) $language->term_id;
			$slug = $language->slug;
			$classes = array('lang-item', 'lang-item-' . esc_attr($id), 'lang-item-' . esc_attr($slug));

			if ($current_lang = pll_current_language() == $slug) {
				if ($args['hide_current'] && !$args['dropdown'])
					continue; // hide current language except for dropdown
				else
					$classes[] = 'current-lang';
			}

			$url = $args['post_id'] !== null && ($tr_id = $links->model->get_post($args['post_id'], $language)) && $links->current_user_can_read($tr_id) ? get_permalink($tr_id) :
				($args['post_id'] === null && !$args['force_home'] ? $links->get_translation_url($language) : null);

			if ($no_translation = empty($url))
				$classes[] = 'no-translation';

			$url = apply_filters('pll_the_language_link', $url, $slug, $language->locale);

			// hide if no translation exists
			if (empty($url) && $args['hide_if_no_translation'])
				continue;

			$url = empty($url) ? $links->get_home_url($language) : $url ; // if the page is not translated, link to the home page

			$name = $args['show_names'] || !$args['show_flags'] || $args['raw'] ? ($args['display_names_as'] == 'slug' ? $slug : $language->name) : '';
			$flag = $args['raw'] && !$args['show_flags'] ? $language->flag_url : ($args['show_flags'] ? $language->flag : '');

			$out[] = compact('id', 'slug', 'name', 'url', 'flag', 'current_lang', 'no_translation', 'classes');
		}
		return empty($out) ? array() : $out;

	}

	/*
	 * displays a language switcher
	 * or returns the raw elements to build a custom language switcher
	 *
	 * list of parameters accepted in $args:
	 *
	 * dropdown               => the list is displayed as dropdown if set to 1, defaults to 0
	 * echo                   => echoes the list if set to 1, defaults to 1
	 * hide_if_empty          => hides languages with no posts (or pages) if set to 1, defaults to 1
	 * show_flags             => displays flags if set to 1, defaults to 0
	 * show_names             => show language names if set to 1, defaults to 1
	 * display_names_as       => wether to display the language name or its slug, valid options are 'slug' and 'name', defaults to name
	 * force_home             => will always link to home in translated language if set to 1, defaults to 0
	 * hide_if_no_translation => hide the link if there is no translation if set to 1, defaults to 0
	 * hide_current           => hide the current language if set to 1, defaults to 0
	 * post_id                => returns links to translations of post defined by post_id if set, defaults not set
	 * raw                    => return a raw array instead of html markup if set to 1, defaults to 0
	 *
	 * @since 0.1
	 *
	 * @param object $links instance of PLL_Frontend_Links
	 * @param array $args
	 * @return string|array either the html markup of the switcher or the raw elements to build a custom language switcher
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
		$args = apply_filters('pll_the_languages_args', $args);
		$elements = $this->get_elements($links, $args);

		if ($args['raw'])
			return $elements;

		if ($args['dropdown']) {
			$walker = new PLL_Walker_Dropdown();
			$args['selected'] = pll_current_language();
		}
		else
			$walker = new PLL_Walker_List();

		$out = apply_filters('pll_the_languages', $walker->walk($elements, $args), $args);

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
	 *
	 * @see Walker::start_el
	 */
	 function start_el( &$output, $element, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$output .= sprintf(
			"\t".'<li class="%s"><a hreflang="%s" href="%s">%s</a></li>'."\n",
			implode(' ', $element->classes),
			esc_attr($element->slug),
			esc_url($element->url),
			$args['show_flags'] && $args['show_names'] ? $element->flag.'&nbsp;'.esc_html($element->name) : $element->flag.esc_html($element->name)
		);
	}

	/*
	 * overrides Walker::display_element as it expects an object with a parent property
	 *
	 * @since 1.2
	 *
	 * @see Walker::display_element
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
	 *
	 * @param array $elements elements to display
	 * @param array $args
	 * @return string
	 */
	function walk($elements, $args = array()) {
		return parent::walk($elements, -1, $args);
	}
}

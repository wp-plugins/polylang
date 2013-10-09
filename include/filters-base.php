<?php

/*
 * base class for frontend and admin filters
 * setups common filters used on both frontend and admin
 * setups helper functions that can be used by both frontend and admin filters
 *
 * @since 1.2
 */
abstract class PLL_Filters_Base {
	public $links_model, $model, $options;

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $links_model
	 */
	public function __construct(&$links_model) {
		$this->links_model = &$links_model;
		$this->model = &$links_model->model;
		$this->options = &$this->model->options;

		// just in case someone would like to display the language description ;-)
		add_filter('language_description', create_function('$v', "return '';"));

		if ($this->options['force_lang']) {
			foreach (array('post_link', '_get_page_link', 'post_type_link') as $filter)
				add_filter($filter, array(&$this, 'post_link'), 10, 2);

			add_filter('term_link', array(&$this, 'term_link'), 10, 3);
		}
	}

	/*
	 * modifies post & page links
	 *
	 * @since 0.7
	 *
	 * @param string $link post link
	 * @param object|int $post post object or post ID
	 * @return string modified post link
	 */
	public function post_link($link, $post) {
		static $links = array();

		if (isset($links[$link]))
			return $links[$link];

		if ('post_type_link' == current_filter() && !in_array($post->post_type, $this->model->post_types))
			return $links[$link] = $link;

		if ('_get_page_link' == current_filter()) // this filter uses the ID instead of the post object
			$post = get_post($post);

		// /!\ when post_status in not "publish", WP does not use pretty permalinks
		return $links[$link] = $post->post_status != 'publish' ? $link : $this->links_model->add_language_to_link($link, $this->model->get_post_language($post->ID));
	}

	/*
	 * modifies term link
	 *
	 * @since 0.7
	 *
	 * @param string $link term link
	 * @param object $post term object
	 * @param string $tax taxonomy name
	 * @return string modified term link
	 */
	public function term_link($link, $term, $tax) {
		static $links = array();

		if (isset($links[$link]))
			return $links[$link];

		return $links[$link] = in_array($tax, $this->model->taxonomies) ?
			$this->links_model->add_language_to_link($link, $this->model->get_term_language($term->term_id)) : $link;
	}

	/*
	 * returns all page ids *not in* language defined by $lang_id
	 * works for all translated hierarchical post types
	 *
	 * @since 0.6
	 *
	 * @param object $lang language object
	 * @return array list of page ids to exclude
	 */
	public function exclude_pages($lang) {
		$args = array(
			'lang' => 0, // so this query is not filtered by our pre_get_post filter in core.php
			'numberposts' => -1,
			'nopaging'    => true,
			'post_type'   => array_intersect(get_post_types(array('hierarchical' => 1)), $this->model->post_types),
			'fields'      => 'ids',
			'tax_query'   => array(array(
				'taxonomy' => 'language',
				'field'    => 'term_taxonomy_id', // since WP 3.5
				'terms'    => $lang->term_taxonomy_id,
				'operator' => 'NOT IN'
			))
		);

		// backward compatibility WP < 3.5
		if (version_compare($GLOBALS['wp_version'], '3.5' , '<')) {
			unset($args['tax_query']['field']);
			$args['tax_query']['terms'] = $lang->term_id;
		}

		return get_posts($args);
	}
}


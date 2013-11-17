<?php

/*
 * manages links filters needed on both frontend and admin
 *
 * @since 1.2
 */
class PLL_Links {
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

		// low priority on links filters to come after any other modifications
		if ($this->options['force_lang']) {
			foreach (array('post_link', '_get_page_link', 'post_type_link') as $filter)
				add_filter($filter, array(&$this, 'post_link'), 20, 2);

			add_filter('term_link', array(&$this, 'term_link'), 20, 3);
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
}


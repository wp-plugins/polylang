<?php

/*
 * manages links filters needed on both frontend and admin
 *
 * @since 1.2
 */
class PLL_Links {
	public $links_model, $model, $options;
	public $links;

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

		// adds our domains or subdomains to allowed hosts for safe redirection
		add_filter('allowed_redirect_hosts', array(&$this, 'allowed_redirect_hosts'));

		// low priority on links filters to come after any other modifications
		if ($this->options['force_lang']) {
			foreach (array('post_link', '_get_page_link', 'post_type_link') as $filter)
				add_filter($filter, array(&$this, 'post_link'), 20, 2);

			add_filter('term_link', array(&$this, 'term_link'), 20, 3);
		}
	}

	/*
	 * adds our domains or subdomains to allowed hosts for safe redirection
	 *
	 * @since 1.4.3
	 *
	 * @param array $hosts allowed hosts
	 * @return array
	 */
	public function allowed_redirect_hosts($hosts) {
		return array_unique(array_merge($hosts, $this->links_model->get_hosts()));
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
		if (isset($this->links[$link]))
			return $this->links[$link];

		if ('post_type_link' == current_filter() && !$this->model->is_translated_post_type($post->post_type))
			return $this->links[$link] = $link;

		if ('_get_page_link' == current_filter()) // this filter uses the ID instead of the post object
			$post = get_post($post);

		// /!\ when post_status is not "publish", WP does not use pretty permalinks
		return $this->links[$link] = $post->post_status != 'publish' ? $link : $this->links_model->add_language_to_link($link, $this->model->get_post_language($post->ID));
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
		if (isset($this->links[$link]))
			return $this->links[$link];

		return $this->links[$link] = $this->model->is_translated_taxonomy($tax) ?
			$this->links_model->add_language_to_link($link, $this->model->get_term_language($term->term_id)) : $link;
	}

	/*
	 * returns the home url in the requested language
	 *
	 * @since 1.3
	 *
	 * @param object|string $language
	 * @param bool $is_search optional wether we need the home url for a search form, defaults to false
	 */
	public function get_home_url($language, $is_search = false) {
		$language = is_object($language) ? $language : $this->model->get_language($language);
		return $is_search ? $language->search_url : $language->home_url;
	}
}


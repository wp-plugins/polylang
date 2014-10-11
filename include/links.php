<?php

/*
 * manages links filters needed on both frontend and admin
 *
 * @since 1.2
 */
class PLL_Links {
	public $links_model, $model, $options;
	protected $_links;

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $polylang
	 */
	public function __construct(&$polylang) {
		$this->links_model = &$polylang->links_model;
		$this->model = &$polylang->model;
		$this->options = &$polylang->options;

		// adds our domains or subdomains to allowed hosts for safe redirection
		add_filter('allowed_redirect_hosts', array(&$this, 'allowed_redirect_hosts'));

		// low priority on links filters to come after any other modifications
		if ($this->options['force_lang']) {
			add_filter('post_link', array(&$this, 'post_link'), 20, 2);
			add_filter('_get_page_link', array(&$this, 'post_link'), 20, 2);
		}

		if ($this->links_model->using_permalinks) {
			add_filter('post_type_link', array(&$this, 'post_type_link'), 20, 2);
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
		if (isset($this->_links[$link]))
			return $this->_links[$link];

		if ('_get_page_link' == current_filter()) // this filter uses the ID instead of the post object
			$post = get_post($post);

		// /!\ when post_status is not "publish", WP does not use pretty permalinks
		return $this->_links[$link] = $post->post_status != 'publish' ? $link : $this->links_model->add_language_to_link($link, $this->model->get_post_language($post->ID));
	}

	/*
	 * modifies custom posts links
	 *
	 * @since 1.6
	 *
	 * @param string $link post link
	 * @param object|int $post post object or post ID
	 * @return string modified post link
	 */
	public function post_type_link($link, $post) {
		if (isset($this->_links[$link]))
			return $this->_links[$link];

		// /!\ when post_status is not "publish", WP does not use pretty permalinks
		if ('publish' == $post->post_status && $this->model->is_translated_post_type($post->post_type)) {
			$lang = $this->model->get_post_language($post->ID);

			if ($this->options['force_lang'])
				$link = $this->links_model->add_language_to_link($link, $lang);

			$link = apply_filters('pll_post_type_link', $link, $lang, $post);
		}

		return $this->_links[$link] = $link;
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
		if (isset($this->_links[$link]))
			return $this->_links[$link];

		if ($this->model->is_translated_taxonomy($tax)) {
			$lang = $this->model->get_term_language($term->term_id);

			if ($this->options['force_lang'])
				$link = $this->links_model->add_language_to_link($link, $lang);

			$link = apply_filters('pll_term_link', $link, $lang, $term);
		}

		return $this->_links[$link] = $link;
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

	/*
	 * get the link to create a new post translation
	 *
	 * @since 1.5
	 *
	 * @param int $post_id
	 * @param object $language
	 * @return string
	 */
	public function get_new_post_translation_link($post_id, $language) {
		$post_type = get_post_type($post_id);

		if ('attachment' == $post_type) {
			$args = array(
				'action' => 'translate_media',
				'from_media' => $post_id,
				'new_lang'  => $language->slug
			);

			// add nonce for media as we will directly publish a new attachment from a clic on this link
			return wp_nonce_url(add_query_arg($args, admin_url('admin.php')), 'translate_media');
		}
		else {
			$args = array(
				'post_type' => $post_type,
				'from_post' => $post_id,
				'new_lang'  => $language->slug
			);

			return add_query_arg($args, admin_url('post-new.php'));
		}
	}

	/*
	 * get the link to create a new term translation
	 *
	 * @since 1.5
	 *
	 * @param int $term_id
	 * @param string $taxonomy
	 * @param string $post_type
	 * @param object $language
	 * @return string
	 */
	public function get_new_term_translation_link($term_id, $taxonomy, $post_type, $language) {
 		$args = array(
			'taxonomy'  => $taxonomy,
			'post_type' => $post_type,
			'from_tag'  => $term_id,
			'new_lang'  => $language->slug
		);

		return add_query_arg($args, admin_url('edit-tags.php'));
	}

	/*
	 * checks if the current user can read the post
	 *
	 * @since 1.5
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public function current_user_can_read($post_id) {
		$post = get_post($post_id);
		if (in_array($post->post_status, get_post_stati(array('public' => true))))
			return true;

		$post_type_object = get_post_type_object($post->post_type);
		$user = wp_get_current_user();
		return is_user_logged_in() && (current_user_can($post_type_object->cap->read_private_posts) || $user->ID == $post->post_author);
	}
}


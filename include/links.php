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
			add_filter('_get_page_link', array(&$this, '_get_page_link'), 20, 2);
		}

		add_filter('post_type_link', array(&$this, 'post_type_link'), 20, 2);
		add_filter('term_link', array(&$this, 'term_link'), 20, 3);

		if ($this->options['force_lang'] > 1)
			add_filter('attachment_link', array(&$this, 'attachment_link'), 20, 2);

		if (3 == $this->options['force_lang'])
			add_filter('preview_post_link', array(&$this, 'preview_post_link'), 20);
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
	 * @param object $post post object
	 * @return string modified post link
	 */
	public function post_link($link, $post) {
		// /!\ when post_status is not "publish", WP does not use pretty permalinks
		return $post->post_status != 'publish' ? $link : $this->links_model->add_language_to_link($link, $this->model->get_post_language($post->ID));
	}


	/*
	 * modifies page links
	 *
	 * @since 1.7
	 *
	 * @param string $link post link
	 * @param int $post_id post ID
	 * @return string modified post link
	 */
	public function _get_page_link($link, $post_id) {
		$post = get_post($post_id);

		// /!\ when post_status is not "publish", WP does not use pretty permalinks
		return $post->post_status != 'publish' ? $link : $this->links_model->add_language_to_link($link, $this->model->get_post_language($post->ID));
	}

	/*
	 * modifies attachment links
	 *
	 * @since 1.6.2
	 *
	 * @param string $link attachment link
	 * @param int $post_id attachment link
	 * @return string modified attachment link
	 */
	public function attachment_link($link, $post_id) {
		return $this->links_model->add_language_to_link($link, $this->model->get_post_language($post_id));
	}

	/*
	 * modifies custom posts links
	 *
	 * @since 1.6
	 *
	 * @param string $link post link
	 * @param object $post post object
	 * @return string modified post link
	 */
	public function post_type_link($link, $post) {
		// /!\ when post_status is not "publish", WP does not use pretty permalinks
		if ('publish' == $post->post_status && $this->model->is_translated_post_type($post->post_type)) {
			$lang = $this->model->get_post_language($post->ID);
			$link = $this->options['force_lang'] ? $this->links_model->add_language_to_link($link, $lang) : $link;
			$link = apply_filters('pll_post_type_link', $link, $lang, $post);
		}

		return $link;
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
		if ($this->model->is_translated_taxonomy($tax)) {
			$lang = $this->model->get_term_language($term->term_id);
			$link = $this->options['force_lang'] ? $this->links_model->add_language_to_link($link, $lang) : $link;
			$link = apply_filters('pll_term_link', $link, $lang, $term);
		}

		return $link;
 	}

	/*
	 * FIXME: keeps the preview post link on default domain when using multiple domains
	 *
	 * @since 1.6.1
	 *
	 * @param string $url
	 * @return string modified url
	 */
	public function preview_post_link($url) {
		return $this->links_model->remove_language_from_link($url);
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


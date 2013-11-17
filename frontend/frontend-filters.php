<?php

/*
 * filters content by language on frontend
 *
 * @since 1.2
 */
class PLL_Frontend_Filters {
	public $links_model, $model, $options;
	public $curlang;

	/*
	 * constructor: setups filters and actions
	 *
	 * @since 1.2
	 *
	 * @param object $links_model
	 * @param object $curlang
	 */
	public function __construct(&$links_model, &$curlang) {
		$this->links_model = &$links_model;
		$this->model = &$links_model->model;
		$this->options = &$this->model->options;

		$this->curlang = &$curlang;

		// filters the WordPress locale
		add_filter('locale', array(&$this, 'get_locale'));

		// backward compatibility WP < 3.4, modifies the language information in rss feed
		add_filter('option_rss_language', array(&$this, 'option_rss_language'));

		// translates page for posts and page on front
		add_filter('option_page_for_posts', array(&$this, 'translate_page'));
		add_filter('option_page_on_front', array(&$this, 'translate_page'));

		// filter sticky posts by current language
		add_filter('option_sticky_posts', array(&$this, 'option_sticky_posts'));

		// filters categories and post tags by language
		add_filter('terms_clauses', array(&$this, 'terms_clauses'), 10, 3);

		// filters the pages according to the current language in wp_list_pages
		add_filter('wp_list_pages_excludes', array(&$this, 'wp_list_pages_excludes'));

		// filters the comments according to the current language
		add_filter('comments_clauses', array(&$this, 'comments_clauses'), 10, 2);

		// rewrites archives, next and previous post links to filter them by language
		foreach (array('getarchives', 'get_previous_post', 'get_next_post') as $filter)
			foreach (array('_join', '_where') as $clause)
				add_filter($filter.$clause, array(&$this, 'posts'.$clause));

		// filters the widgets according to the current language
		add_filter('widget_display_callback', array(&$this, 'widget_display_callback'), 10, 2);

		// strings translation (must be applied before WordPress applies its default formatting filters)
		foreach (array('widget_title', 'option_blogname', 'option_blogdescription', 'option_date_format', 'option_time_format') as $filter)
			add_filter($filter, 'pll__', 1);

		// translates biography
		add_filter('get_user_metadata', array(&$this,'get_user_metadata'), 10, 3);

		// set posts and terms language when created from frontend (ex with P2 theme)
		add_action('save_post', array(&$this, 'save_post'), 200, 2);
		add_action('create_term', array(&$this, 'save_term'), 10, 3);
		add_action('edit_term', array(&$this, 'save_term'), 10, 3);
	}

	/*
	 * returns the locale based on current language
	 *
	 * @since 0.1
	 *
	 * @param string $locale
	 * @return string
	 */
	public function get_locale($locale) {
		return $this->curlang->locale;
	}

	/*
	 * modifies the language information in rss feed
	 * backward compatibility WP < 3.4
	 *
	 * @since 0.8
	 *
	 * @param string $value
	 * @return string
	 */
	public function option_rss_language($value) {
		return get_bloginfo_rss('language');
	}

	/*
	 * translates page for posts and page on front
	 *
	 * @since 0.8
	 *
	 * @param int $v page for posts or page on front page id
	 * @return int
	 */
	public function translate_page($v) {
		// FIXME comes too late when language is set from content
		static $posts = array(); // the fonction may be often called so let's store the result

		// returns the current page if there is no translation to avoid ugly notices
		return isset($this->curlang) && $v && (isset($posts[$v]) || $posts[$v] = $this->model->get_post($v, $this->curlang)) ? $posts[$v] : $v;
	}

	/*
	 * filters sticky posts by current language
	 *
	 * @since 0.8
	 *
	 * @param array $posts list of sticky posts ids
	 * @return array modified list of sticky posts ids
	 */
	public function option_sticky_posts($posts) {
		if ($this->curlang && !empty($posts)) {
			update_object_term_cache($posts, 'post'); // to avoid queries in foreach
			foreach ($posts as $key => $post_id) {
				$lang = $this->model->get_post_language($post_id);
				if (empty($lang) || $lang->term_id != $this->curlang->term_id)
					unset($posts[$key]);
			}
		}
		return $posts;
	}

	/*
	 * filters categories and post tags by language when needed
	 *
	 * @since 0.2
	 *
	 * @param array $clauses sql clauses
	 * @param array $taxonomies
	 * @param array $args get_terms arguments
	 * @return array modified sql clauses
	 */
	public function terms_clauses($clauses, $taxonomies, $args) {
		// does nothing except on taxonomies which are filterable
		if (!array_intersect($taxonomies, $this->model->taxonomies))
			return $clauses;

		// adds our clauses to filter by language
		return $this->model->terms_clauses($clauses, isset($args['lang']) ? $args['lang'] : $this->curlang);
	}

	/*
	 * excludes pages which are not in the current language for wp_list_pages
	 * useful for the pages widget
	 *
	 * @since 0.4
	 *
	 * @param array $pages list of page ids to exclude from wp_list_pages
	 * @return array modified list of page ids
	 */
	public function wp_list_pages_excludes($pages) {
		return array_merge($pages, pll_exclude_pages($this->curlang));
	}

	/*
	 * filters the comments according to the current language mainly for the recent comments widget
	 *
	 * @since 0.2
	 *
	 * @param array $clauses
	 * @param object $query
	 * @return array modified $clauses
	 */
	public function comments_clauses($clauses, $query) {
		return $this->model->comments_clauses($clauses, isset($query->query_vars['lang']) ? $query->query_vars['lang'] : $this->curlang);
	}

	/*
	 * modifies the sql request for wp_get_archives an get_adjacent_post to filter by the current language
	 *
	 * @since 0.1
	 *
	 * @param string $sql join clause
	 * @return string modified join clause
	 */
	public function posts_join($sql) {
		return $sql . $this->model->join_clause('post');
	}

	/*
	 * modifies the sql request for wp_get_archives and get_adjacent_post to filter by the current language
	 *
	 * @since 0.1
	 *
	 * @param string $sql where clause
	 * @return string modified where clause
	 */
	public function posts_where($sql) {
		preg_match("#post_type = '([^']+)'#", $sql, $matches);	// find the queried post type
		return !empty($matches[1]) && in_array($matches[1], $this->model->post_types) ? $sql . $this->model->where_clause($this->curlang, 'post') : $sql;
	}

	/*
	 * filters the widgets according to the current language
	 * don't display if a language filter is set and this is not the current one
	 *
	 * @since 0.3
	 *
	 * @param array $instance widget settings
	 * @param object $widget WP_Widget object
	 * @return bool|array false if we hide the widget, unmodified $instance otherwise
	 */
	public function widget_display_callback($instance, $widget) {
		return !empty($this->options['widgets'][$widget->id]) && $this->options['widgets'][$widget->id] != $this->curlang->slug ? false : $instance;
	}

	/*
	 * translates biography
	 *
	 * @since 0.9
	 *
	 * @param null $null
	 * @param int $id user id
	 * @param string $meta_key
	 * @return null|string
	 */
	public function get_user_metadata($null, $id, $meta_key) {
		return $meta_key == 'description' ? get_user_meta($id, 'description_'.$this->curlang->slug, true) : $null;
	}

	/*
	 * called when a post (or page) is saved, published or updated
	 * does nothing except on post types which are filterable
	 *
	 * @since 1.1
	 *
	 * @param int $post_id
	 * @param object $post
	 */
	public function save_post($post_id, $post) {
		if (in_array($post->post_type, $this->model->post_types)) {
			if (isset($_REQUEST['lang']))
				$this->model->set_post_language($post_id, $_REQUEST['lang']);

			elseif ($this->model->get_post_language($post_id))
				{}

			elseif (($parent_id = wp_get_post_parent_id($post_id)) && $parent_lang = $this->model->get_post_language($parent_id))
				$this->model->set_post_language($post_id, $parent_lang);

			else
				$this->model->set_post_language($post_id, $this->curlang);
		}
	}

	/*
	 * called when a category or post tag is created or edited
	 * does nothing except on taxonomies which are filterable
	 *
	 * @since 1.1
	 *
	 * @param int $term_id
	 * @param int $tt_id term taxonomy id
	 * @param string $taxonomy
	 */
	public function save_term($term_id, $tt_id, $taxonomy) {
		if (in_array($taxonomy, $this->model->taxonomies)) {
			if (isset($_REQUEST['lang']))
				$this->model->set_term_language($term_id, $_REQUEST['lang']);

			elseif ($this->model->get_term_language($term_id))
				{}

			elseif (($term = get_term($term_id, $taxonomy)) && !empty($term->parent) && $parent_lang = $this->model->get_term_language($term->parent))
				$this->model->set_term_language($term_id, $parent_lang);

			else
				$this->model->set_term_language($term_id, $this->curlang);
		}
	}
}

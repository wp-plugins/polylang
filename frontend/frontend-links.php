<?php

/*
 * manages links filters and url of translations on frontend
 *
 * @since 1.2
 */
class PLL_Frontend_Links extends PLL_Links {
	public $curlang, $page_on_front = 0, $page_for_posts = 0;
	protected $cache; // our internal non persistent cache object

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $polylang
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);

		$this->curlang = &$polylang->curlang;
		$this->init_page_on_front_cache();

		$this->cache = new PLL_Cache();

		add_action('pll_language_defined', array(&$this, 'pll_language_defined'));
		add_action('pll_language_defined', array(&$this, 'init_page_on_front_cache')); // translate page on front and page for posts
	}

	/*
	 * stores the page on front and page for posts ids
	 *
	 * @since 1.6
	 */
	public function init_page_on_front_cache() {
		if ('page' == get_option('show_on_front')) {
			$this->page_on_front = get_option('page_on_front');
			$this->page_for_posts = get_option('page_for_posts');
		}
	}

	/*
	 * adds filters once the language is defined
	 * low priority on links filters to come after any other modification
	 *
	 * @since 1.2
	 */
	public function pll_language_defined() {
		// rewrites author and date links to filter them by language
		foreach (array('feed_link', 'author_link', 'search_link', 'year_link', 'month_link', 'day_link') as $filter)
			add_filter($filter, array(&$this, 'archive_link'), 20);

		// rewrites post types archives links to filter them by language
		add_filter('post_type_archive_link', array(&$this, 'post_type_archive_link'), 20, 2);

		// modifies the page link in case the front page is not in the default language
		add_filter('page_link', array(&$this, 'page_link'), 20, 2);

		// meta in the html head section
		add_action('wp_head', array(&$this, 'wp_head'));

		// manages the redirection of the homepage
		add_filter('redirect_canonical', array(&$this, 'redirect_canonical'), 10, 2);

		// modifies the home url
		if (!defined('PLL_FILTER_HOME_URL') || PLL_FILTER_HOME_URL)
			add_filter('home_url', array(&$this, 'home_url'), 10, 2);

		if ($this->options['force_lang'] > 1) {
			// rewrites next and previous post links when not automatically done by WordPress
			add_filter('get_pagenum_link', array(&$this, 'archive_link'), 20);

			// rewrites ajax url
			add_filter('admin_url', array(&$this, 'admin_url'), 10, 2);
		}

		// redirects to canonical url
		add_action('wp', array(&$this, 'check_canonical_url'), 10, 0); // before WordPress redirect_canonical, avoid passing the WP object
	}

	/*
	 * modifies the author and date links to add the language parameter (as well as feed link)
	 *
	 * @since 0.4
	 *
	 * @param string $link
	 * @return string modified link
	 */
	public function archive_link($link) {
		return $this->links_model->add_language_to_link($link, $this->curlang);
	}
	
	/*
	 * modifies the post type archive links to add the language parameter
	 * only if the post type is translated
	 *
	 * @since 1.7.6
	 *
	 * @param string $link
	 * @param string $post_type
	 * @return string modified link
	 */
	public function post_type_archive_link($link, $post_type) {
		return $this->model->is_translated_post_type($post_type) ? $this->links_model->add_language_to_link($link, $this->curlang) : $link;
	}
	
	/*
	 * modifies post & page links
	 * caches the result
	 *
	 * @since 0.7
	 *
	 * @param string $link post link
	 * @param object $post post object
	 * @return string modified post link
	 */
	public function post_link($link, $post) {
		$cache_key = 'post:' . $post->ID;
		if (false === $_link = $this->cache->get($cache_key)) {
			$_link = parent::post_link($link, $post);
			$this->cache->set($cache_key, $_link);
		}
		return $_link;
	}
	
	/*
	 * modifies page links
	 * caches the result
	 *
	 * @since 1.7
	 *
	 * @param string $link post link
	 * @param int $post_id post ID
	 * @return string modified post link
	 */
	public function _get_page_link($link, $post_id) {
		$cache_key = 'post:' . $post_id;
		if (false === $_link = $this->cache->get($cache_key)) {
			$_link = parent::_get_page_link($link, $post_id);
			$this->cache->set($cache_key, $_link);
		}
		return $_link;
	}

	/*
	 * modifies attachment links
	 * caches the result
	 *
	 * @since 1.6.2
	 *
	 * @param string $link attachment link
	 * @param int $post_id attachment link
	 * @return string modified attachment link
	 */
	public function attachment_link($link, $post_id) {
		$cache_key = 'post:' . $post_id;
		if (false === $_link = $this->cache->get($cache_key)) {
			$_link = parent::attachment_link($link, $post_id);
			$this->cache->set($cache_key, $_link);
		}
		return $_link;
	}

	/*
	 * modifies custom posts links
	 * caches the result
	 *
	 * @since 1.6
	 *
	 * @param string $link post link
	 * @param object $post post object
	 * @return string modified post link
	 */
	public function post_type_link($link, $post) {
		$cache_key = 'post:' . $post->ID;
		if (false === $_link = $this->cache->get($cache_key)) {
			$_link = parent::post_type_link($link, $post);
			$this->cache->set($cache_key, $_link);
		}
		return $_link;
	}

	/*
	 * modifies filtered taxonomies (post format like) and translated taxonomies links
	 * caches the result
	 *
	 * @since 0.7
	 *
	 * @param string $link
	 * @param object $term term object
	 * @param string $tax taxonomy name
	 * @return string modified link
	 */
	public function term_link($link, $term, $tax) {
		$cache_key = 'term:' . $term->term_id;
		if (false === $_link = $this->cache->get($cache_key)) {
			if (in_array($tax, $this->model->get_filtered_taxonomies())) {
				$_link = $this->links_model->add_language_to_link($link, $this->curlang);
				$_link = apply_filters('pll_term_link', $_link, $this->curlang, $term);
			}

			else {
				$_link = parent::term_link($link, $term, $tax);
			}
			$this->cache->set($cache_key, $_link);
		}
		return $_link;
	}

	/*
	 * modifies the page link in case the front page is not in the default language
	 *
	 * @since 0.7.2
	 *
	 * @param string $link
	 * @param int $id
	 * @return string modified link
	 */
	public function page_link($link, $id) {
		if ($this->page_on_front && ($lang = $this->model->get_post_language($id)) && in_array($id, $this->model->get_translations('post', $this->page_on_front)))
			return $lang->home_url;

		return $link;
	}

	/*
	 * outputs references to translated pages (if exists) in the html head section
	 *
	 * @since 0.1
	 */
	public function wp_head() {
		// google recommends to include self link https://support.google.com/webmasters/answer/189077?hl=en
		foreach ($this->model->get_languages_list() as $language) {
			if ($url = $this->get_translation_url($language))
				$urls[$language->slug] = $url;
		}

		// ouptputs the section only if there are translations ($urls always contains self link)
		if (!empty($urls) && count($urls) > 1) {
			foreach ($urls as $lang => $url)
				printf('<link rel="alternate" href="%s" hreflang="%s" />'."\n", esc_url($url), esc_attr($lang));
		}
	}

	/*
	 * manages canonical redirection of the homepage when using page on front
	 *
	 * @since 0.1
	 *
	 * @param string $redirect_url
	 * @param string $requested_url
	 * @return bool|string modified url, false if redirection is canceled
	 */
	public function redirect_canonical($redirect_url, $requested_url) {
		global $wp_query;
		if (is_page() && !is_feed() && isset($wp_query->queried_object) && $wp_query->queried_object->ID == $this->page_on_front) {
			$url = is_paged() ? $this->links_model->add_paged_to_link($this->get_home_url(), $wp_query->query_vars['page']) : $this->get_home_url();

			// don't forget additional query vars
			$query = parse_url($redirect_url, PHP_URL_QUERY);
			if (!empty($query)) {
				parse_str($query, $query_vars);
				$url = add_query_arg($query_vars, $url);
			}
			
			return $url;
		}

		// protect against chained redirects
		if ($redirect_url != $this->check_canonical_url($redirect_url, false))
			return false;

		return $redirect_url;
	}
	
	/*
	 * checks if a file is in a directory or its subdirectories
	 * the comparison takes care of Windows on which WP can mix \ and /
	 * 
	 * @since 1.7
	 *  
	 * @param string $file
	 * @param string $dir
	 * @return bool
	 */
	protected function is_file_in($file, $dir) {
		return strpos(str_replace('\\', '/', $file), str_replace('\\', '/', $dir)) !== false;
	}

	/*
	 * filters the home url to get the right language
	 *
	 * @since 0.4
	 *
	 * @param string $url
	 * @param string $path
	 * @return string
	 */
	public function home_url($url, $path) {
		if (!(did_action('template_redirect') || did_action('login_init')) || rtrim($url,'/') != $this->links_model->home)
			return $url;

		static $white_list, $black_list; // avoid evaluating this at each function call

		// we *want* to filter the home url in these cases
		if (empty($white_list)) {
			$white_list = apply_filters('pll_home_url_white_list',  array(
				array('file' => get_theme_root()),
				array('function' => 'wp_nav_menu'),
				array('function' => 'login_footer')
			));
		}

		// we don't want to filter the home url in these cases
		if (empty($black_list)) {
			$black_list = apply_filters('pll_home_url_black_list',  array(
				array('file' => 'searchform.php'), // since WP 3.6 searchform.php is passed through get_search_form
				array('function' => 'get_search_form')
			));
		}

		$traces = version_compare(PHP_VERSION, '5.2.5', '>=') ? debug_backtrace(false) : debug_backtrace();

		foreach ($traces as $trace) {
			// black list first
			foreach ($black_list as $v) {
				if ((isset($trace['file'], $v['file']) && $this->is_file_in($trace['file'], $v['file'])) || (isset($trace['function'], $v['function']) && $trace['function'] == $v['function']))
					return $url;
			}

			foreach ($white_list as $v) {
				if ((isset($trace['function'], $v['function']) && $trace['function'] == $v['function']) ||
					(isset($trace['file'], $v['file']) && $this->is_file_in($trace['file'], $v['file']) && in_array($trace['function'], array('home_url', 'get_home_url', 'bloginfo', 'get_bloginfo'))))
					$ok = true;
			}
		}

		return empty($ok) ? $url : (empty($path) ? rtrim($this->get_home_url($this->curlang), '/') : $this->get_home_url($this->curlang));
	}

	/*
	 * returns the url of the translation (if exists) of the current page
	 *
	 * @since 0.1
	 *
	 * @param object $language
	 * @return string
	 */
	public function get_translation_url($language) {
		if (false !== $translation_url = $this->cache->get('translation_url:' . $language->slug))
			return $translation_url;

		global $wp_query;
		$qv = $wp_query->query_vars;
		$hide = $this->options['default_lang'] == $language->slug && $this->options['hide_default'];
		
		// make sure that we have the queried object
		// see https://wordpress.org/support/topic/patch-for-fixing-a-notice
		$queried_object_id = $wp_query->get_queried_object_id();

		// post and attachment
		if (is_single() && ($this->options['media_support'] || !is_attachment()) && ($id = $this->model->get_post($queried_object_id, $language)) && $this->current_user_can_read($id))
			$url = get_permalink($id);

		// page for posts
		elseif ($wp_query->is_posts_page && !empty($queried_object_id) && ($id = $this->model->get_post($wp_query->queried_object_id, $language)))
			$url = get_permalink($id);

		// page
		elseif (is_page() && ($id = $this->model->get_post($queried_object_id, $language)) && $this->current_user_can_read($id))
			$url = $hide && $queried_object_id == $this->page_on_front ? $this->links_model->home : get_page_link($id);

		elseif (is_search()) {
			$url = $this->get_archive_url($language);
			
			// special case for search filtered by translated taxonomies: taxonomy terms are translated in the translation url
			if (!empty($wp_query->tax_query->queries)) {

				foreach ($wp_query->tax_query->queries as $tax_query) {
					if (!empty($tax_query['taxonomy']) && $this->model->is_translated_taxonomy($tax_query['taxonomy'])) {

						$tax = get_taxonomy($tax_query['taxonomy']);
						$terms = get_terms($tax->name, array('fields' => 'id=>slug')); // filtered by current language

						foreach ($tax_query['terms'] as $slug) {
							$term_id = array_search($slug, $terms); // what is the term_id corresponding to taxonomy term?
							if ($term_id && $term_id = $this->model->get_translation('term', $term_id, $language)) { // get the translated term_id
								$term = get_term($term_id, $tax->name);
								$url = str_replace($slug, $term->slug, $url);
							}
						}
					}
				}
			}
		}
		
		// translated taxonomy
		// take care that is_tax() is false for categories and tags
		elseif ((is_category() || is_tag() || is_tax()) && ($term = get_queried_object()) && $this->model->is_translated_taxonomy($term->taxonomy)) {
			$lang = $this->model->get_term_language($term->term_id);

			if (!$lang || $language->slug == $lang->slug)
				$url = get_term_link($term, $term->taxonomy); // self link
			elseif ($tr_id = $this->model->get_translation('term', $term->term_id, $language)) {
				$tr_term = get_term($tr_id, $term->taxonomy);
				// check if translated term (or children) have posts
				if ($tr_term && ($tr_term->count || (is_taxonomy_hierarchical($term->taxonomy) && array_sum(wp_list_pluck(get_terms($term->taxonomy, array('child_of' => $tr_term->term_id, 'lang' => $language->slug)), 'count')))))
					$url = get_term_link($tr_term, $term->taxonomy);
			}
		}

		elseif (is_archive()) {
			$keys = array('post_type', 'm', 'year', 'monthnum', 'day', 'author', 'author_name');
			$keys = array_merge($keys, $this->model->get_filtered_taxonomies_query_vars());

			// check if there are existing translations before creating the url
			if ($this->model->count_posts($language, array_intersect_key($qv, array_flip($keys))))
				$url = $this->get_archive_url($language);
		}

		elseif (is_home() || is_tax('language') )
			$url = $this->get_home_url($language);

		$translation_url = apply_filters('pll_translation_url', (isset($url) && !is_wp_error($url) ? $url : null), $language->slug);
		$this->cache->set('translation_url:' . $language->slug, $translation_url);
		return $translation_url;
	}

	/*
	 * get the translation of the current archive url
	 * used also for search
	 *
	 * @since 1.2
	 *
	 * @param object $language
	 * @return string
	 */
	public function get_archive_url($language) {
		$url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$url = $this->links_model->switch_language_in_link($url, $language);
		$url = $this->links_model->remove_paged_from_link($url);
		return apply_filters('pll_get_archive_url', $url, $language);
	}

	/*
	 * returns the home url in the right language
	 *
	 * @since 0.1
	 *
	 * @param object $language optional defaults to current language
	 * @param bool $is_search optional wether we need the home url for a search form, defaults to false
	 */
	public function get_home_url($language = '', $is_search = false) {
		if (empty($language))
			$language = $this->curlang;

		return parent::get_home_url($language, $is_search);
	}

	/*
	 * rewrites ajax url when using domains or subdomains
	 *
	 * @since 1.5
	 *
	 * @param string $url admin url with path evaluated by WordPress
	 * @param string $path admin path
	 * @return string
	 */
	public function admin_url($url, $path) {
		return 'admin-ajax.php' === $path ? $this->links_model->switch_language_in_link($url, $this->curlang) : $url;
	}

	/*
	 * if the language code is not in agreement with the language of the content
	 * redirects incoming links to the proper URL to avoid duplicate content
	 *
	 * @since 0.9.6
	 *
	 * @param string $requested_url optional
	 * @param bool $do_redirect optional, whether to perform the redirection or not
	 * @return string if redirect is not performed
	 */
	public function check_canonical_url($requested_url = '', $do_redirect = true) {
		global $wp_query, $post, $is_IIS;

		// don't redirect in same cases as WP
		if ( is_trackback() || is_search() || is_comments_popup() || is_admin() || is_preview() || is_robots() || ( $is_IIS && !iis7_supports_permalinks() ) ) {
			return;
		}

		// don't redirect mysite.com/?attachment_id= to mysite.com/en/?attachment_id=
		if (1 == $this->options['force_lang'] && is_attachment() && isset($_GET['attachment_id']))
			return;

		// if the default language code is not hidden and the static front page url contains the page name
		// the customizer lands here and the code below would redirect to the list of posts
		if (isset($_POST['wp_customize'], $_POST['customized']))
			return;

		// don't redirect if we are on a static front page
		if ($this->options['redirect_lang'] && isset($this->page_on_front) && is_page($this->page_on_front))
			return;

		if (empty($requested_url))
			$requested_url  = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		if (is_single() || is_page()) {
			if (isset($post->ID) && $this->model->is_translated_post_type($post->post_type)) {
				$language = $this->model->get_post_language((int)$post->ID);
			}
		}

		elseif (is_category() || is_tag() || is_tax()) {
			$obj = $wp_query->get_queried_object();
			if ($this->model->is_translated_taxonomy($obj->taxonomy)) {
				$language = $this->model->get_term_language((int)$obj->term_id);
			}
		}

		elseif ($wp_query->is_posts_page) {
			$obj = $wp_query->get_queried_object();
			$language = $this->model->get_post_language((int)$obj->ID);
		}

		if (empty($language)) {
			$language = $this->curlang;
			$redirect_url = $requested_url;
		}
		else {
			// first get the canonical url evaluated by WP
			$redirect_url = (!$redirect_url = redirect_canonical($requested_url, false)) ? $requested_url : $redirect_url;
			
			// then get the right language code in url
			$redirect_url = $this->options['force_lang'] ?
				$this->links_model->switch_language_in_link($redirect_url, $language) :
				$this->links_model->remove_language_from_link($redirect_url); // works only for default permalinks
		}

		// allow plugins to change the redirection or even cancel it by setting $redirect_url to false 
		$redirect_url = apply_filters('pll_check_canonical_url', $redirect_url, $language);

		// the language is not correctly set so let's redirect to the correct url for this object
		if ($do_redirect && $redirect_url && $requested_url != $redirect_url) {
			wp_redirect($redirect_url, 301);
			exit;
		}

		return $redirect_url;
	}
}

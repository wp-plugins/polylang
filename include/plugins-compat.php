<?php

class Polylang_Plugins_Compat {
	function __construct() {
		global $polylang;

		// YARPP
		// just makes YARPP aware of the language taxonomy (after Polylang registered it)
		add_action('init', create_function('',"\$GLOBALS['wp_taxonomies']['language']->yarpp_support = 1;"), 20);

		// WordPress SEO by Yoast
		add_filter('override_load_textdomain', array(&$this, 'wpseo_override_load_textdomain'), 10, 2);
		add_filter('get_terms_args', array(&$this, 'wpseo_remove_terms_filter'));
		add_filter('pll_home_url_white_list', create_function('$arr', "return array_merge(\$arr, array(array('file' => 'wordpress-seo')));"));

		// Custom field template
		add_action('dbx_post_advanced', array(&$this, 'cft_copy'));

		// Jetpack infinite scroll
		add_filter('pre_get_posts', array(&$this, 'jetpack_infinite_scroll'));
	}

	// Unfortunately WPSEO loads the text domain before Polylang is able to modify the locale
	// this hack works because Polylang is loaded before WPSEO...
	function wpseo_override_load_textdomain( $return, $domain ) {
		if ($domain == 'wordpress-seo' && !did_action('plugins_loaded')) {
			add_filter('plugins_loaded', create_function('', "load_plugin_textdomain('wordpress-seo', false, dirname(WPSEO_BASENAME).'/languages');"));
			return true;
		}
		return $return;
	}

	// removes the language filter for the taxonomy sitemaps
	function wpseo_remove_terms_filter($args) {
		if (defined('WPSEO_VERSION') && isset($GLOBALS['wp_query']->query['sitemap']))
			$args['lang'] = 0;
		return $args;
	}

	// Custom field template does check $_REQUEST['post'] to populate the custom fields values
	function cft_copy() {
		global $post, $custom_field_template;
		if (isset($custom_field_template, $_REQUEST['from_post'], $_REQUEST['new_lang']) && !empty($post))
			$_REQUEST['post'] = $post->ID;
	}

	// Currently it is not possible to set the language in ajax url so let's use our cookie
	function jetpack_infinite_scroll($query) {
		if (isset($_GET['infinity'], $_GET['page'])) {
			$query->set('lang', $GLOBALS['polylang']->get_preferred_language()->slug);
			if (empty($qv['post_type']) && !$query->is_search)
				$query->set('post_type', 'post');
		}
	}
}

new Polylang_Plugins_Compat();

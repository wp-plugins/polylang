<?php

class Polylang_Plugins_Compat {
	function __construct() {
		global $polylang;

		// YARPP
		// just makes YARPP aware of the language taxonomy (after Polylang registered it)
		add_action('init', create_function('',"\$GLOBALS['wp_taxonomies']['language']->yarpp_support = 1;"), 20);

		// WordPress SEO by Yoast
		if ($polylang->is_admin)
			add_filter('override_load_textdomain', array(&$this, 'wpseo_override_load_textdomain'), 10, 2);

		add_filter('get_terms_args', array(&$this, 'wpseo_remove_terms_filter'));

		// Custom field template
		add_action('dbx_post_advanced', array(&$this, 'cft_copy'));
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
		if (isset($custom_field_template) && !empty($post) && isset($_REQUEST['from_post']) && isset($_REQUEST['new_lang']))
			$_REQUEST['post'] = $post->ID;
	}
}

new Polylang_Plugins_Compat();

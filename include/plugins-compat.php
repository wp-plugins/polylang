<?php

class Polylang_Plugins_Compat{
	function __construct() {
		// YARPP
		// just makes YARPP aware of the language taxonomy (after Polylang registered it)
		add_action('init', create_function('',"\$GLOBALS['wp_taxonomies']['language']->yarpp_support = 1;"), 20);

		// WordPress SEO by Yoast
		// Unfortunately this plugin loads the text domain before Polylang is able to modify the locale
		if (is_admin())
			add_filter('override_load_textdomain', array(&$this, 'wp_seo_override_load_textdomain'), 10, 2);

	}

	function wp_seo_override_load_textdomain( $return, $domain ) {
		if ($domain == 'wordpress-seo' && !did_action('plugins_loaded')) {
			add_filter('plugins_loaded', create_function('', "load_plugin_textdomain('wordpress-seo', false, dirname(WPSEO_BASENAME).'/languages');"));
			return true;
		}
		return $return;
	}

}

new Polylang_Plugins_Compat();

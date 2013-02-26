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

		// Custom field template
		// This plugin does check $_REQUEST['post'] to populate the custom fields values
		add_action('dbx_post_advanced', array(&$this, 'cft_copy'));

	}

	function wp_seo_override_load_textdomain( $return, $domain ) {
		if ($domain == 'wordpress-seo' && !did_action('plugins_loaded')) {
			add_filter('plugins_loaded', create_function('', "load_plugin_textdomain('wordpress-seo', false, dirname(WPSEO_BASENAME).'/languages');"));
			return true;
		}
		return $return;
	}

	function cft_copy() {
		global $post, $custom_field_template;
		if (isset($custom_field_template) && !empty($post) && isset($_REQUEST['from_post']) && isset($_REQUEST['new_lang']))
			$_REQUEST['post'] = $post->ID;
	}

}

new Polylang_Plugins_Compat();

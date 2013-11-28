<?php

/*
 * manages compatibility with 3rd party plugins
 * this class is available as soon as the plugin is loaded
 *
 * @since 1.0
 */
class PLL_Plugins_Compat {

	/*
	 * constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {
		// WordPress Importer
		add_action('init', array(&$this, 'maybe_wordpress_importer'));

		// YARPP
		// just makes YARPP aware of the language taxonomy (after Polylang registered it)
		add_action('init', create_function('',"\$GLOBALS['wp_taxonomies']['language']->yarpp_support = 1;"), 20);

		// WordPress SEO by Yoast
		add_action('pll_language_defined', array(&$this, 'wpseo_translate_options'));
		add_filter('get_terms_args', array(&$this, 'wpseo_remove_terms_filter'));
		add_filter('pll_home_url_white_list', create_function('$arr', "return array_merge(\$arr, array(array('file' => 'wordpress-seo')));"));

		// Custom field template
		add_action('add_meta_boxes', array(&$this, 'cft_copy'), 10, 2);

		// Aqua Resizer
		add_filter('pll_home_url_black_list', create_function('$arr', "return array_merge(\$arr, array(array('function' => 'aq_resize')));"));
	}

	/*
	 * WordPress Importer
	 * if WordPress Importer is active, replace the wordpress_importer_init function
	 *
	 * @since 1.2
	 */
	function maybe_wordpress_importer() {
		if (class_exists('WP_Import')) {
			remove_action('admin_init', 'wordpress_importer_init');
			add_action('admin_init', array(&$this, 'wordpress_importer_init'));
		}
	}

	/*
	 * WordPress Importer
	 * loads our child class PLL_WP_Import instead of WP_Import
	 *
	 * @since 1.2
	 */
	function wordpress_importer_init() {
		$class = new ReflectionClass('WP_Import');
		load_plugin_textdomain( 'wordpress-importer', false, basename(dirname( $class->getFileName() )) . '/languages' );

		$GLOBALS['wp_import'] = new PLL_WP_Import();
		register_importer( 'wordpress', 'WordPress', __('Import <strong>posts, pages, comments, custom fields, categories, and tags</strong> from a WordPress export file.', 'wordpress-importer'), array( $GLOBALS['wp_import'], 'dispatch' ) );
	}

	/*
	 * WordPress SEO by Yoast
	 * reloads options once the language has been defined to enable translations
	 * useful only when the language is set from content
	 *
	 * @since 1.2
	 *
	 */
	public function wpseo_translate_options() {
		if (defined('WPSEO_VERSION') && !PLL_ADMIN && did_action('wp_loaded')) {
			global $wpseo_front;
			foreach ( get_wpseo_options_arr() as $opt )
				$wpseo_front->options = array_merge( $wpseo_front->options, (array) get_option( $opt ) );
		}
	}

	/*
	 * WordPress SEO by Yoast
	 * removes the language filter for the taxonomy sitemaps
	 *
	 * @since 1.0.3
	 *
	 * @param array $args get_terms arguments
	 * @return array modified list of arguments
	 */
	public function wpseo_remove_terms_filter($args) {
		if (defined('WPSEO_VERSION') && isset($GLOBALS['wp_query']->query['sitemap']))
			$args['lang'] = 0;
		return $args;
	}

	/*
	 * Custom field template does check $_REQUEST['post'] to populate the custom fields values
	 *
	 * @since 1.0.2
	 *
	 * @param string $post_type unused
	 * @param object $post current post object
	 */
	public function cft_copy($post_type, $post) {
		global $custom_field_template;
		if (isset($custom_field_template, $_REQUEST['from_post'], $_REQUEST['new_lang']) && !empty($post))
			$_REQUEST['post'] = $post->ID;
	}
}

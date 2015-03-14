<?php

/*
 * manages compatibility with 3rd party plugins (and themes)
 * this class is available as soon as the plugin is loaded
 *
 * @since 1.0
 */
class PLL_Plugins_Compat {
	static protected $instance; // for singleton

	/*
	 * constructor
	 *
	 * @since 1.0
	 */
	protected function __construct() {
		// WordPress Importer
		add_action('init', array(&$this, 'maybe_wordpress_importer'));

		// YARPP
		// just makes YARPP aware of the language taxonomy (after Polylang registered it)
		add_action('init', create_function('',"\$GLOBALS['wp_taxonomies']['language']->yarpp_support = 1;"), 20);

		// WordPress SEO by Yoast
		add_action('pll_language_defined', array(&$this, 'wpseo_init'));

		// Custom field template
		add_action('add_meta_boxes', array(&$this, 'cft_copy'), 10, 2);

		// Aqua Resizer
		add_filter('pll_home_url_black_list', create_function('$arr', "return array_merge(\$arr, array(array('function' => 'aq_resize')));"));

		// Twenty Fourteen
		add_filter('transient_featured_content_ids', array(&$this, 'twenty_fourteen_featured_content_ids'));
		add_filter('option_featured-content', array(&$this, 'twenty_fourteen_option_featured_content'));

		// Jetpack 3
		add_action('jetpack_widget_get_top_posts', array(&$this, 'jetpack_widget_get_top_posts'), 10, 3);
		add_filter('grunion_contact_form_field_html', array(&$this, 'grunion_contact_form_field_html_filter'), 10, 3);
		add_filter('jetpack_open_graph_tags', array(&$this, 'jetpack_ogp'));
	}

	/*
	 * access to the single instance of the class
	 *
	 * @since 1.7
	 *
	 * @return object
	 */
	static public function instance() {
		if (empty(self::$instance))
			self::$instance = new self();

		return self::$instance;
	}
	
	/*
	 * WordPress Importer
	 * if WordPress Importer is active, replace the wordpress_importer_init function
	 *
	 * @since 1.2
	 */
	function maybe_wordpress_importer() {
		if (defined('WP_LOAD_IMPORTERS') && class_exists('WP_Import')) {
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
	 * translate options
	 * add specific filters and actions
	 *
	 * @since 1.6.4
	 */
	public function wpseo_init() {
		global $polylang;

		if (!defined('WPSEO_VERSION') || PLL_ADMIN)
			return;

		// reloads options once the language has been defined to enable translations
		// useful only when the language is set from content
		if (did_action('wp_loaded')) {
			if (version_compare(WPSEO_VERSION, '1.7.2', '<')) {
				global $wpseo_front;
			}
			else {
				$wpseo_front = WPSEO_Frontend::get_instance();
			}

			$options = version_compare(WPSEO_VERSION, '1.5', '<') ? get_wpseo_options_arr() : WPSEO_Options::get_option_names();
			foreach ( $options as $opt )
				$wpseo_front->options = array_merge( $wpseo_front->options, (array) get_option( $opt ) );
		}

		// one sitemap per language when using multiple domains or subdomains
		// because WPSEO does not accept several domains or subdomains in one sitemap
		if ($polylang->options['force_lang'] > 1) {
			add_filter('home_url', array(&$this, 'wpseo_home_url'), 10, 2); // fix home_url
			add_filter('wpseo_posts_join', array(&$this, 'wpseo_posts_join'), 10, 2);
			add_filter('wpseo_posts_where', array(&$this, 'wpseo_posts_where'), 10, 2);
		}

		// one sitemap for all languages when the language is set from the content or directory name
		else {
			add_filter('get_terms_args', array(&$this, 'wpseo_remove_terms_filter'));
		}

		add_filter('pll_home_url_white_list', create_function('$arr', "return array_merge(\$arr, array(array('file' => 'wordpress-seo')));"));
		add_action('wpseo_opengraph', array(&$this, 'wpseo_ogp'), 2);
	}

	/*
	 * WordPress SEO by Yoast
	 * fixes the home url as well as the stylesheet url
	 * only when using multiple domains or subdomains
	 *
	 * @since 1.6.4
	 *
	 * @param string $url
	 * @return $url
	 */
	public function wpseo_home_url($url, $path) {
		global $polylang;

		$uri = empty($path) ? ltrim($_SERVER['REQUEST_URI'], '/') : $path;

		if ('sitemap_index.xml' === $uri || preg_match('#([^/]+?)-sitemap([0-9]+)?\.xml|([a-z]+)?-?sitemap\.xsl#', $uri))
			$url = $polylang->links_model->switch_language_in_link($url, $polylang->curlang);

		return $url;
	}

	/*
	 * WordPress SEO by Yoast
	 * modifies the sql request for posts sitemaps
	 * only when using multiple domains or subdomains
	 *
	 * @since 1.6.4
	 *
	 * @param string $sql join clause
	 * @param string $post_type
	 * @return string
	 */
	public function wpseo_posts_join($sql, $post_type) {
		global $polylang;
		return pll_is_translated_post_type($post_type) ? $sql. $polylang->model->join_clause('post') : $sql;
	}

	/*
	 * WordPress SEO by Yoast
	 * modifies the sql request for posts sitemaps
	 * only when using multiple domains or subdomains
	 *
	 * @since 1.6.4
	 *
	 * @param string $sql where clause
	 * @param string $post_type
	 * @return string
	 */
	public function wpseo_posts_where($sql, $post_type) {
		global $polylang;
		return pll_is_translated_post_type($post_type) ? $sql . $polylang->model->where_clause($polylang->curlang, 'post') : $sql;
	}

	/*
	 * WordPress SEO by Yoast
	 * removes the language filter for the taxonomy sitemaps
	 * only when the language is set from the content or directory name
	 *
	 * @since 1.0.3
	 *
	 * @param array $args get_terms arguments
	 * @return array modified list of arguments
	 */
	public function wpseo_remove_terms_filter($args) {
		if (isset($GLOBALS['wp_query']->query['sitemap']))
			$args['lang'] = 0;
		return $args;
	}

	/*
	 * WordPress SEO by Yoast
	 * adds opengraph support for translations
	 *
	 * @since 1.6
	 */
	public function wpseo_ogp() {
		global $polylang, $wpseo_og;

		// WPSEO already deals with the locale
		if (isset($polylang) && method_exists($wpseo_og, 'og_tag')) {
			foreach ($polylang->model->get_languages_list() as $language) {
				if ($language->slug != $polylang->curlang->slug && $polylang->links->get_translation_url($language) && $fb_locale = self::get_fb_locale($language)) {
					$wpseo_og->og_tag('og:locale:alternate', $fb_locale);
				}
			}
		}
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

	/*
	 * rewrites the function Featured_Content::get_featured_post_ids()
	 *
	 * @since 1.4
	 *
	 * @param array $ids featured posts ids
	 * @return array modified featured posts ids (include all languages)
	 */
	public function twenty_fourteen_featured_content_ids($featured_ids) {
		global $polylang;

		if (empty($polylang) || false !== $featured_ids)
			return $featured_ids;

		$settings = Featured_Content::get_setting();

		if (!$term = get_term_by( 'name', $settings['tag-name'], 'post_tag' ))
			return $featured_ids;

		// get featured tag translations
		$tags = $polylang->model->get_translations('term' ,$term->term_id);
		$ids = array();

		// Query for featured posts in all languages
		// one query per language to get the correct number of posts per language
		foreach ($tags as $tag) {
			$_ids = get_posts(array(
				'lang'        => 0, // avoid language filters
				'fields'      => 'ids',
				'numberposts' => Featured_Content::$max_posts,
				'tax_query'   => array(array(
					'taxonomy' => 'post_tag',
					'terms'    => (int) $tag,
				)),
			));

			$ids = array_merge($ids, $_ids);
		}

		$ids = array_map( 'absint', $ids );
		set_transient( 'featured_content_ids', $ids );

		return $ids;
	}

	/*
	 * translates the featured tag id in featured content settings
	 * mainly to allow hiding it when requested in featured content options
	 * acts only on frontend
	 *
	 * @since 1.4
	 *
	 * @param array $settings featured content settings
	 * @return array modified $settings
	 */
	public function twenty_fourteen_option_featured_content($settings) {
		if (!PLL_ADMIN && $settings['tag-id'] && $tr = pll_get_term($settings['tag-id']))
			$settings['tag-id'] = $tr;

		return $settings;
	}

	/*
	 * adapted from the same function in jetpack-3.0.2/3rd-party/wpml.php
	 *
	 * @since 1.5.4
	 */
	public function jetpack_widget_get_top_posts( $posts, $post_ids, $count ) {
		foreach ( $posts as $k => $post ) {
			if (pll_current_language() !== pll_get_post_language($post['post_id']))
				unset( $posts[ $k ] );
		}

		return $posts;
	}

	/*
	 * adapted from the same function in jetpack-3.0.2/3rd-party/wpml.php
	 * keeps using 'icl_translate' as the function registers the string
	 *
	 * @since 1.5.4
	 */
	public function grunion_contact_form_field_html_filter( $r, $field_label, $id ){
		if ( function_exists( 'icl_translate' ) ) {
			if ( pll_current_language() !== pll_default_language() ) {
				$label_translation = icl_translate( 'jetpack ', $field_label . '_label', $field_label );
				$r = str_replace( $field_label, $label_translation, $r );
			}
		}

		return $r;
	}

	/*
	 * adds opengraph support for locale and translations
	 *
	 * @since 1.6
	 *
	 * @param array $tags opengraph tags to output
	 * @return array
	 */
	public function jetpack_ogp($tags) {
		global $polylang;

		if (isset($polylang)) {
			foreach ($polylang->model->get_languages_list() as $language) {
				if ($language->slug != $polylang->curlang->slug && $polylang->links->get_translation_url($language) && $fb_locale = self::get_fb_locale($language))
					$tags['og:locale:alternate'][] = $fb_locale;
				if ($language->slug == $polylang->curlang->slug && $fb_locale = self::get_fb_locale($language))
					$tags['og:locale'] = $fb_locale;
			}
		}
		return $tags;
	}

	/*
	 * correspondance between WordPress locales and Facebook locales
	 * @see http://wpcentral.io/internationalization/
	 * @see https://www.facebook.com/translations/FacebookLocales.xml
	 *
	 * @since 1.6
	 *
	 * @param object $language
	 * @return bool|string
	 */
	static public function get_fb_locale($language) {
		static $facebook_locales = array(
			'af' => 'af_ZA', 'ar' => 'ar_AR', 'az' => 'az_AZ', 'bel' => 'be_BY', 'bg_BG' => 'bg_BG', 'bn_BD' => 'bn_IN',
			'bs_BA' => 'bs_BA', 'ca' => 'ca_ES', 'ckb' => 'ku_TR', 'cs_CZ' => 'cs_CZ', 'cy' => 'cy_GB', 'da_DK' => 'da_DK',
			'de_DE' => 'de_DE', 'el' => 'el_GR', 'en_US' => 'en_US', 'en_GB' => 'en_GB', 'eo' => 'eo_EO', 'es_CL' => 'es_LA',
			'es_CO' => 'es_LA', 'es_MX' => 'es_LA', 'es_PE' => 'es_LA', 'es_PR' => 'es_LA', 'es_VE' => 'es_LA', 'es_ES' => 'es_ES',
			'et' => 'et_EE', 'eu' => 'eu_ES', 'fa_IR' => 'fa_IR', 'fi' => 'fi_FI', 'fo' => 'fo_FO', 'fr_CA' => 'fr_CA',
			'fr_FR' => 'fr_FR', 'fy' => 'fy_NL', 'ga' => 'ga_IE', 'gl_ES' => 'gl_ES', 'gn' => 'gn_PY', 'gu_IN' => 'gu_IN',
			'he_IL' => 'he_IL', 'hi_IN' => 'hi_IN', 'hr' => 'hr_HR', 'hu_HU' => 'hu_HU', 'hy' => 'hy_AM', 'id_ID' => 'id_ID',
			'is_IS' => 'is_IS', 'it_IT' => 'it_IT', 'ja' => 'ja_JP', 'jv_ID' => 'jv_ID', 'ka_GE' => 'ka_GE', 'kk' => 'kk_KZ',
			'km' => 'km_kH', 'kn' => 'kn_IN', 'ko_KR' => 'ko_KR', 'lt_LT' => 'lt_LT', 'lv' => 'lv_LV', 'mk_MK' => 'mk_MK',
			'ml_IN' => 'ml_IN', 'mn' => 'mn_MN', 'mr' => 'mr_IN', 'ms_MY' => 'ms_MY', 'ne_NP' => 'ne_NP', 'nb_NO' => 'nb_NO',
			'nl_NL' => 'nl_NL', 'nn_NO' => 'nn_NO', 'pa_IN' => 'pa_IN', 'pl_PL' => 'pl_PL', 'ps' => 'ps_AF', 'pt_BR' => 'pt_BR',
			'pt_PT' => 'pt_PT', 'ps' => 'ps_AF', 'ro_RO' => 'ro_RO', 'ru_RU' => 'ru_RU', 'si_LK' => 'si_LK', 'sk_SK' => 'sk_SK',
			'sl_SI' => 'sl_SI', 'sq' => 'sq_AL', 'sr_RS' => 'sr_RS', 'sv_SE' => 'sv_SE', 'sw' => 'sw_KE', 'ta_IN' => 'ta_IN',
			'te' => 'te_IN', 'tg' => 'tg_TJ', 'th' => 'th_TH', 'ph' => 'tl_PH', 'tr_TR' => 'tr_TR', 'uk' => 'uk_UA',
			'ur' => 'ur_PK', 'uz_UZ' => 'uz_UZ', 'vi' => 'vi_VN', 'zh_CN' => 'zh_CN', 'zh_HK' => 'zh_HK', 'zh_TW' => 'zh_TW'
		);

		return isset($facebook_locales[$language->locale]) ? $facebook_locales[$language->locale] : false;
	}
}

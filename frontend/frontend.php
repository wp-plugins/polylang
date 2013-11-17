<?php

/*
 * frontend controller
 *
 * @since 1.2
 */
class PLL_Frontend extends PLL_Base{
	public $curlang;
	public $links, $choose_lang, $filters, $filters_search, $auto_translate;

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $links_model
	 */
	public function __construct(&$links_model) {
		parent::__construct($links_model);

		add_action('pll_language_defined', array(&$this, 'pll_language_defined'), 1, 2);

		// filters posts by language
		add_filter('parse_query', array(&$this, 'parse_query'), 6); // after PLL_Frontend_Filters::parse_query

		// not before 'check_language_code_in_url'
		if (!defined('PLL_AUTO_TRANSLATE') || PLL_AUTO_TRANSLATE)
			add_action('wp', array(&$this, 'auto_translate'), 20);
	}

	/*
	 * setups url modifications based on the links mode
	 * setups the language chooser based on options
	 *
	 * @since 1.2
	 *
	 * @param object $links_model
	 */
	public function init() {
		$this->links = new PLL_Frontend_Links($this->links_model);

		$this->choose_lang = $this->options['force_lang'] && get_option('permalink_structure') ?
			new PLL_Choose_Lang_Url($this->links) :
			new PLL_Choose_Lang_Content($this->links);
	}

	/*
	 * setups filters and nav menus once the language has been defined
	 *
	 * @since 1.2
	 *
	 * @param string $slug current language slug
	 * @param object $curlang current language object
	 */
	public function pll_language_defined($slug, $curlang) {
		$this->curlang = $curlang;

		// filters
		$this->filters = new PLL_Frontend_Filters($this->links_model, $curlang);
		$this->filters_search = new PLL_Frontend_Filters_Search($this->links);

		// nav menu
		$this->nav_menu = new PLL_Frontend_Nav_Menu($this->options, $curlang);
	}

	/*
	 * modifies some query vars to "hide" that the language is a taxonomy and avoid conflicts
	 *
	 * @since 1.2
	 *
	 * @param object $query WP_Query object
	 */
	public function parse_query($query) {
		$qv = $query->query_vars;

		// allow filtering recent posts and secondary queries by the current language
		// take care not to break queries for non visible post types such as nav_menu_items
		// do not filter if lang is set to an empty value
		if (/*$query->is_home &&*/ !empty($this->curlang) && !isset($qv['lang']) && (empty($qv['post_type']) || $this->model->is_translated_post_type($qv['post_type'])))
			$query->set('lang', $this->curlang->slug);

		// modifies query vars when the language is queried
		if (!empty($qv['lang'])) {
			// remove pages query when the language is set unless we do a search
			if (empty($qv['post_type']) && !$query->is_search)
				$query->set('post_type', 'post');

			// unset the is_archive flag for language pages to prevent loading the archive template
			// keep archive flag for comment feed otherwise the language filter does not work
			if (!$query->is_comment_feed && !$query->is_post_type_archive && !$query->is_date && !$query->is_author && !$query->is_category && !$query->is_tag && !$query->is_tax('post_format'))
				$query->is_archive = false;

			// unset the is_tax flag for authors pages and post types archives
			// FIXME Should I do this for other cases?
			if ($query->is_author || $query->is_post_type_archive || $query->is_date || $query->is_search) {
				$query->is_tax = false;
				unset($query->queried_object);
			}
		}

		// to avoid conflict beetwen taxonomies
		if (isset($query->tax_query->queries))
			foreach ($query->tax_query->queries as $tax)
				if (pll_is_translated_taxonomy($tax['taxonomy']))
					unset($query->query_vars['lang']);
	}

	/*
	 * auto translate posts and terms ids
	 *
	 * @since 1.2
	 */
	public function auto_translate() {
		$this->auto_translate = new PLL_Frontend_Auto_Translate($this->model);
	}
}


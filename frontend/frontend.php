<?php

/*
 * frontend controller
 * accessible as $polylang global object
 *
 * properties:
 * options          => inherited, reference to Polylang options array
 * model            => inherited, reference to PLL_Model object
 * links_model      => inherited, reference to PLL_Links_Model object
 * links            => reference to PLL_Links object
 * choose_lang      => reference to PLL_Choose_lang object
 * curlang          => current language
 * filters          => reference to PLL_Filters object
 * filters_search   => reference to PLL_Frontend_Filters_Search object
 * nav_menu         => reference to PLL_Frontend_Nav_Menu object
 * auto_translate   => optional, reference to PLL_Auto_Translate object
 *
 * @since 1.2
 */
class PLL_Frontend extends PLL_Base {
	public $curlang;
	public $links, $choose_lang, $filters, $filters_search, $nav_menu, $auto_translate;

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $links_model
	 */
	public function __construct(&$links_model) {
		parent::__construct($links_model);

		add_action('pll_language_defined', array(&$this, 'pll_language_defined'), 1);

		// filters posts by language
		add_action('parse_query', array(&$this, 'parse_query'), 6);

		// not before 'check_language_code_in_url'
		if (!defined('PLL_AUTO_TRANSLATE') || PLL_AUTO_TRANSLATE)
			add_action('wp', array(&$this, 'auto_translate'), 20);
	}

	/*
	 * setups the language chooser based on options
	 *
	 * @since 1.2
	 */
	public function init() {
		$this->links = new PLL_Frontend_Links($this);

		$c = array('Content', 'Url', 'Url', 'Domain');
		$class = 'PLL_Choose_Lang_' . $c[$this->options['force_lang']];
		$this->choose_lang = new $class($this);
	}

	/*
	 * setups filters and nav menus once the language has been defined
	 *
	 * @since 1.2
	 */
	public function pll_language_defined() {
		// filters
		$this->filters = new PLL_Frontend_Filters($this);
		$this->filters_search = new PLL_Frontend_Filters_Search($this);

		// nav menu
		$this->nav_menu = new PLL_Frontend_Nav_Menu($this);
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

		// to avoid returning an empty result if the query includes a translated taxonomy in a different language
		$has_tax = isset($query->tax_query->queries) && $this->have_translated_taxonomy($query->tax_query->queries);

		// allow filtering recent posts and secondary queries by the current language
		// take care not to break queries for non visible post types such as nav_menu_items
		// do not filter if lang is set to an empty value
		// do not filter single page and translated taxonomies to avoid conflicts
		if (!empty($this->curlang) && !isset($qv['lang']) && !$has_tax && empty($qv['page_id']) && empty($qv['pagename']) && (empty($qv['post_type']) || $this->model->is_translated_post_type($qv['post_type']))) {
			$this->choose_lang->set_lang_query_var($query, $this->curlang);
		}

		// modifies query vars when the language is queried
		if (!empty($qv['lang'])) {
			// remove pages query when the language is set unless we do a search
			// take care not to break the single page query!
			if (empty($qv['post_type']) && !$query->is_search && !$query->is_page)
				$query->set('post_type', 'post');

			// unset the is_tax flag for authors pages and post types archives
			// FIXME Should I do this for other cases?
			if ($query->is_author || $query->is_post_type_archive || $query->is_date || $query->is_search) {
				$query->is_tax = false;
				unset($query->queried_object);
			}
		}
	}

	/*
	 * auto translate posts and terms ids
	 *
	 * @since 1.2
	 */
	public function auto_translate() {
		$this->auto_translate = new PLL_Frontend_Auto_Translate($this);
	}

	/*
	 * resets some variables when switching blog
	 * overrides parent method
	 *
	 * @since 1.5.1
	 */
	public function switch_blog($new_blog, $old_blog) {
		// need to check that some languages are defined when user is logged in, has several blogs, some without any languages
		if (parent::switch_blog($new_blog, $old_blog) && did_action('pll_language_defined') && $this->model->get_languages_list()) {
			static $restore_curlang;
			if (empty($restore_curlang))
				$restore_curlang = $this->curlang->slug; // to always remember the current language through blogs

			$lang = $this->model->get_language($restore_curlang);
			$this->curlang = $lang ? $lang : $this->model->get_language($this->options['default_lang']);
			$this->links->init_page_on_front_cache();
			$this->load_strings_translations();
		}
	}

	/*
	 * check if translated taxonomy is queried
	 * compatible with nested queries introduced in WP 4.1
	 *
	 * @since 1.7
	 *
	 * @param array $tax_queries
	 * @return bool
	 */
	protected function have_translated_taxonomy($tax_queries) {
		foreach ($tax_queries as $tax_query) {
			if (isset($tax_query['taxonomy']) && $this->model->is_translated_taxonomy($tax_query['taxonomy']))
				return true;

			// nested queries
			elseif (is_array($tax_query) && $this->have_translated_taxonomy($tax_query))
				return true;
		}

		return false;
	}
}


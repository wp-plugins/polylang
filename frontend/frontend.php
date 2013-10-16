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
		$this->nav_menu = new PLL_Frontend_Nav_Menu();
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


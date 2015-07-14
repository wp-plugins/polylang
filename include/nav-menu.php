<?php

/*
 * manages custom menus translations
 * common to admin and frontend for the customizer
 *
 * @since 1.7.7
 */
class PLL_Nav_Menu {
	public $model, $options;

	/*
	 * constructor: setups filters and actions
	 *
	 * @since 1.7.7
	 *
	 * @param object $polylang
	 */
	public function __construct(&$polylang) {
		$this->model = &$polylang->model;
		$this->options = &$polylang->options;

		// integration with WP customizer
		add_action('customize_register', array(&$this, 'create_nav_menu_locations'), 5);
	}

	/*
	 * create temporary nav menu locations (one per location and per language) for all non-default language
	 * to do only one time
	 *
	 * @since 1.2
	 */
	public function create_nav_menu_locations() {
		static $once;
		global $_wp_registered_nav_menus;

		if (isset($_wp_registered_nav_menus) && !$once) {
			foreach ($_wp_registered_nav_menus as $loc => $name)
				foreach ($this->model->get_languages_list() as $lang)
					$arr[$loc . (pll_default_language() == $lang->slug ? '' : '___' . $lang->slug)] = $name . ' ' . $lang->name;

			$_wp_registered_nav_menus = $arr;
			$once = true;
		}
	}
}

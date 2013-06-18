<?php
/*
Plugin Name: Polylang
Plugin URI: http://polylang.wordpress.com/
Version: 1.1.2
Author: Frédéric Demarle
Description: Adds multilingual capability to WordPress
Text Domain: polylang
Domain Path: /languages
*/

/*
 * Copyright 2011-2013 F. Demarle
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 */

define('POLYLANG_VERSION', '1.1.2');
define('PLL_MIN_WP_VERSION', '3.1');

define('POLYLANG_DIR', dirname(__FILE__)); // our directory
define('PLL_INC', POLYLANG_DIR.'/include');

if (!defined('PLL_LOCAL_DIR'))
	define('PLL_LOCAL_DIR', WP_CONTENT_DIR.'/polylang'); // default directory to store user data such as custom flags

if (file_exists(PLL_LOCAL_DIR.'/pll-config.php'))
	include_once(PLL_LOCAL_DIR.'/pll-config.php'); // includes local config file if exists

define('POLYLANG_URL', plugins_url('/'.basename(POLYLANG_DIR))); // our url. Don't use WP_PLUGIN_URL http://wordpress.org/support/topic/ssl-doesnt-work-properly

if (!defined('PLL_LOCAL_URL'))
	define('PLL_LOCAL_URL', content_url('/polylang')); // default url to access user data such as custom flags

if (!defined('PLL_COOKIE'))
	define('PLL_COOKIE', 'pll_language'); // cookie name. no cookie will be used if set to false

if (!defined('PLL_SEARCH_FORM_JS') && !version_compare($GLOBALS['wp_version'], '3.6', '<'))
	define('PLL_SEARCH_FORM_JS', false); // the search form js is no more needed in WP 3.6+ except if the search form is hardcoded elsewhere than in searchform.php

require_once(PLL_INC.'/base.php');

// controls the plugin, deals with activation, deactivation, upgrades, initialization as well as rewrite rules
class Polylang extends Polylang_Base {

	function __construct() {
		parent::__construct();
		global $polylang; // globalize the variable to access it in the API

		// manages plugin activation and deactivation
		register_activation_hook( __FILE__, array(&$this, 'activate'));
		register_deactivation_hook( __FILE__, array(&$this, 'deactivate'));

		// stopping here if we upgraded from a too old version
		if ($this->options && version_compare($this->options['version'], '0.8', '<')) {
			add_action('all_admin_notices', array(&$this, 'admin_notices'));
			return;
		}

		// stopping here if we are going to deactivate the plugin (avoids breaking rewrite rules)
		if (isset($_GET['action'], $_GET['plugin']) && $_GET['action'] == 'deactivate' && $_GET['plugin'] == 'polylang/polylang.php')
			return;

		// blog creation on multisite
		add_action('wpmu_new_blog', array(&$this, 'wpmu_new_blog'));

		// manages plugin upgrade
		add_action('admin_init',  array(&$this, 'admin_init'));

		// plugin and widget initialization
		add_action('setup_theme', array(&$this, 'init'), 1);
		add_action('widgets_init', array(&$this, 'widgets_init'));
		add_action('wp_loaded', array(&$this, 'prepare_rewrite_rules'), 5); // after Polylang_base::add_post_types_taxonomies

		// separate admin and frontend
		if (is_admin() && isset($_GET['page']) && $_GET['page'] == 'mlang') {
			require_once(PLL_INC.'/admin-base.php');
			require_once(PLL_INC.'/admin.php');
			$polylang = new Polylang_Admin();
		}

		elseif ($this->is_admin) {
			require_once(PLL_INC.'/admin-base.php');
			require_once(PLL_INC.'/admin-filters.php');
			$polylang = new Polylang_Admin_Filters();
		}

		else {
			require_once(PLL_INC.'/core.php');
			$polylang = new Polylang_Core();

			// auto translate posts and terms ids in term
			if (!defined('PLL_AUTO_TRANSLATE') || PLL_AUTO_TRANSLATE)
				require_once(PLL_INC.'/auto-translate.php');
		}

		// loads the API
		require_once(PLL_INC.'/api.php');

		// nav menus
		require_once(PLL_INC.'/nav-menu.php');

		// WPML API + wpml-config.xml
		if (!defined('PLL_WPML_COMPAT') || PLL_WPML_COMPAT)
			require_once(PLL_INC.'/wpml-compat.php');

		// extra code for compatibility with some plugins
		if (!defined('PLL_PLUGINS_COMPAT') || PLL_PLUGINS_COMPAT)
			require_once(PLL_INC.'/plugins-compat.php');
	}

	// plugin activation for multisite
	function activate() {
		global $wp_version, $wpdb;
		load_plugin_textdomain('polylang', false, basename(POLYLANG_DIR).'/languages'); // plugin i18n

		if (version_compare($wp_version, PLL_MIN_WP_VERSION , '<'))
			die (sprintf('<p style = "font-family: sans-serif; font-size: 12px; color: #333; margin: -5px">%s</p>',
				sprintf(__('You are using WordPress %s. Polylang requires at least WordPress %s.', 'polylang'),
					esc_html($wp_version),
					PLL_MIN_WP_VERSION
				)
			));

		// check if it is a network activation - if so, run the activation function for each blog
		if (is_multisite() && isset($_GET['networkwide']) && ($_GET['networkwide'] == 1)) {
			foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
				switch_to_blog($blog_id);
				$this->_activate();
			}
			restore_current_blog();
		}
		else
			$this->_activate();
	}

	// plugin activation
	function _activate() {
		// create the termmeta table - not provided by WP by default - if it does not already exists
		// uses exactly the same model as other meta tables to be able to use access functions provided by WP
		global $wpdb;
		$charset_collate = empty($wpdb->charset) ? '' : "DEFAULT CHARACTER SET $wpdb->charset";
		$charset_collate .= empty($wpdb->collate) ? '' : " COLLATE $wpdb->collate";
		$table = $wpdb->prefix . 'termmeta';

		$r = $wpdb->query("
			CREATE TABLE IF NOT EXISTS $table (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				term_id bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY term_id (term_id),
				KEY meta_key (meta_key)
			) $charset_collate;");

		if ($r === false)
			die (sprintf(
				'<p style = "font-family: sans-serif; font-size: 12px; color: #333; margin: -5px">%s</p>',
				__('For some reasons, Polylang could not create a table in your database.', 'polylang')
			));

		// codex tells to use the init action to call register_taxonomy but I need it now for my rewrite rules
		register_taxonomy('language', null , array('label' => false, 'query_var'=>'lang'));

		// defines default values for options in case this is the first installation
		if (!get_option('polylang')) {
			$options['browser'] = 1; // default language for the front page is set by browser preference
			$options['rewrite'] = 1; // remove /language/ in permalinks (was the opposite before 0.7.2)
			$options['hide_default'] = 0; // do not remove URL language information for default language
			$options['force_lang'] = 0; // do not add URL language information when useless
			$options['redirect_lang'] = 0; // do not redirect the language page to the homepage
			$options['media_support'] = 1; // support languages and translation for media by default
			$options['sync'] = array_keys($this->list_metas_to_sync()); // synchronisation is enabled by default
			$options['post_types'] = array_values(get_post_types(array('_builtin' => false, 'show_ui => true')));
			$options['taxonomies'] = array_values(get_taxonomies(array('_builtin' => false, 'show_ui => true')));
			$options['version'] = POLYLANG_VERSION;

			update_option('polylang', $options);
		}

		// add our rewrite rules
		$this->add_post_types_taxonomies();
		$this->prepare_rewrite_rules();
		flush_rewrite_rules();
	}

	// plugin deactivation for multisite
	function deactivate() {
		global $wpdb;

		// check if it is a network deactivation - if so, run the deactivation function for each blog
		if (is_multisite() && isset($_GET['networkwide']) && ($_GET['networkwide'] == 1)) {
			foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
				switch_to_blog($blog_id);
				$this->_deactivate();
			}
			restore_current_blog();
		}
		else
			$this->_deactivate();
	}

	// plugin deactivation
	function _deactivate() {
		flush_rewrite_rules();
	}

	// blog creation on multisite
	function wpmu_new_blog($blog_id) {
		switch_to_blog($blog_id);
		$r = $this->_activate();
		restore_current_blog();
	}

	// displays a notice when ugrading from a too old version
	function admin_notices() {
		load_plugin_textdomain('polylang', false, basename(POLYLANG_DIR).'/languages');
		printf(
			'<div class="error"><p>%s</p><p>%s</p></div>',
			__('Polylang has been deactivated because you upgraded from a too old version.', 'polylang'),
			sprintf(
				__('Please upgrade first to %s before ugrading to %s.', 'polylang'),
				'<strong>0.9.8</strong>',
				POLYLANG_VERSION
			)
		);
	}

	// manage upgrade even when it is done manually
	function admin_init() {

		if (version_compare($this->options['version'], POLYLANG_VERSION, '<')) {

			if (version_compare($this->options['version'], '0.9', '<'))
				$this->options['sync'] = defined('PLL_SYNC') && !PLL_SYNC ? 0 : 1; // the option replaces PLL_SYNC in 0.9

			if (version_compare($this->options['version'], '1.0', '<')) {
				// the option replaces PLL_MEDIA_SUPPORT in 1.0
				$this->options['media_support'] = defined('PLL_MEDIA_SUPPORT') && !PLL_MEDIA_SUPPORT ? 0 : 1;

				// split the synchronization options in 1.0
				$this->options['sync'] = empty($this->options['sync']) ? array() : array_keys($this->list_metas_to_sync());

				// set default values for post types and taxonomies to translate
				$this->options['post_types'] = array_values(get_post_types(array('_builtin' => false, 'show_ui => true')));
				$this->options['taxonomies'] = array_values(get_taxonomies(array('_builtin' => false, 'show_ui => true')));

				flush_rewrite_rules(); // rewrite rules have been modified in 1.0
			}

			if (version_compare($this->options['version'], '1.0.2', '<'))
				// set the option again in case it was not in 1.0
				if (!isset($this->options['media_support']))
					$this->options['media_support'] = defined('PLL_MEDIA_SUPPORT') && !PLL_MEDIA_SUPPORT ? 0 : 1;

			if (version_compare($this->options['version'], '1.1', '<')) {
				// update strings register with icl_register_string
				$strings = get_option('polylang_wpml_strings');
				if ($strings) {
					foreach ($strings as $key => $string)
						$strings[$key]['icl'] = 1;
					update_option('polylang_wpml_strings', $strings);
				}

				// move polylang_widgets options
				if ($widgets = get_option('polylang_widgets')) {
					$this->options['widgets'] = $widgets;
					delete_option('polylang_widgets');
				}

				// update nav menus
				if ($menu_lang = get_option('polylang_nav_menus')) {

					foreach ($menu_lang as $location => $arr) {
						if (!in_array($location, array_keys(get_registered_nav_menus())))
							continue;

						$switch_options = array_slice($arr, -5, 5);
						$translations = array_diff_key($arr, $switch_options);
						$has_switcher = array_shift($switch_options);

						foreach ($this->get_languages_list() as $lang) {
							$menu_locations[$location.(pll_default_language() == $lang->slug ? '' : '#' . $lang->slug)] = empty($translations[$lang->slug]) ? 0 : $translations[$lang->slug];

							// create the menu items
							if (!empty($has_switcher)) {
								$menu_item_db_id = wp_update_nav_menu_item($translations[$lang->slug], 0, array(
									'menu-item-title' => __('Language switcher', 'polylang'),
									'menu-item-url' => '#pll_switcher',
									'menu-item-status' => 'publish'
								));

								update_post_meta($menu_item_db_id, '_pll_menu_item', $switch_options);
							}
						}
					}

					if (!empty($menu_locations))
						set_theme_mod('nav_menu_locations', $menu_locations);

					delete_option('polylang_nav_menus');
				}
			}

			$this->options['version'] = POLYLANG_VERSION;
			update_option('polylang', $this->options);
		}
	}

	// some initialization
	function init() {
		global $wpdb;
		$wpdb->termmeta = $wpdb->prefix . 'termmeta'; // registers the termmeta table in wpdb

		if ($this->is_admin)
			load_plugin_textdomain('polylang', false, basename(POLYLANG_DIR).'/languages'); // plugin i18n, only needed for backend

		// registers the language taxonomy
		// codex: use the init action to call this function
		// object types will be set later once all custom post types are registered
		register_taxonomy('language', null, array(
			'labels' => array(
				'name' => __('Languages', 'polylang'),
				'singular_name' => __('Language', 'polylang'),
				'all_items' => __('All languages', 'polylang'),
			),
			'public' => false, // avoid displaying the 'like post tags text box' in the quick edit
			'query_var' => 'lang',
			'update_count_callback' => '_update_post_term_count'
		));

		// optionaly removes 'language' in permalinks so that we get http://www.myblog/en/ instead of http://www.myblog/language/en/
		// language information always in front of the uri ('with_front' => false)
		// the 3rd parameter structure has been modified in WP 3.4
		add_permastruct('language', $this->options['rewrite'] ? '%language%' : 'language/%language%',
			version_compare($GLOBALS['wp_version'], '3.4' , '<') ? false : array('with_front' => false));
	}

	// registers our widgets
	function widgets_init() {
		require_once(PLL_INC.'/widget.php');
		register_widget('Polylang_Widget');

		// overwrites the calendar widget to filter posts by language
		if (!defined('PLL_WIDGET_CALENDAR') || PLL_WIDGET_CALENDAR) {
			require_once(PLL_INC.'/calendar.php'); // loads this only now otherwise it breaks widgets not registered in a widgets_init hook
			unregister_widget('WP_Widget_Calendar');
			register_widget('Polylang_Widget_Calendar');
		}
	}

	// complete our taxonomy and add rewrite rules filters once custom post types and taxonomies are registered (normally in init)
	function prepare_rewrite_rules() {
		foreach ($this->post_types as $post_type)
			register_taxonomy_for_object_type('language', $post_type);

		// don't modify the rules if there is no languages created yet
		if (!$this->get_languages_list())
			return;

		$types = array_values(array_merge($this->post_types, $this->taxonomies)); // supported post types and taxonomies
		$types = array_merge(array('date', 'root', 'comments', 'search', 'author', 'language', 'post_format'), $types);
		$types = apply_filters('pll_rewrite_rules', $types); // allow plugins to add rewrite rules to the language filter

		foreach ($types as $type)
			add_filter($type . '_rewrite_rules', array(&$this, 'rewrite_rules'));

		add_filter('rewrite_rules_array', array(&$this, 'rewrite_rules')); // needed for post type archives
	}

	// the rewrite rules !
	// always make sure the default language is at the end in case the language information is hidden for default language
	// thanks to brbrbr http://wordpress.org/support/topic/plugin-polylang-rewrite-rules-not-correct
	function rewrite_rules($rules) {
		$filter = str_replace('_rewrite_rules', '', current_filter());

		// suppress the rules created by WordPress for our taxonomy
		if ($filter == 'language')
			return array();

		global $wp_rewrite;
		$always_rewrite = in_array($filter, array('date', 'root', 'comments', 'author', 'post_format'));
		$newrules = array();

		foreach ($this->get_languages_list() as $language)
			if (!$this->options['hide_default'] || $this->options['default_lang'] != $language->slug)
				$languages[] = $language->slug;

		if (isset($languages))
			$slug = $wp_rewrite->root . ($this->options['rewrite'] ? '' : 'language/') . '('.implode('|', $languages).')/';

		foreach ($rules as $key => $rule) {
			// we don't need the lang parameter for post types and taxonomies
			// moreover adding it would create issues for pages and taxonomies
			if ($this->options['force_lang'] && in_array($filter, array_merge($this->post_types, $this->taxonomies))) {
				if (isset($slug))
					$newrules[$slug.str_replace($wp_rewrite->root, '', $key)] = str_replace(
						array('[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '[1]'),
						array('[9]', '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]'),
						$rule
					); // hopefully it is sufficient!

				if ($this->options['hide_default']) {
					$newrules[$key] = $rules[$key];
					// unset only if we hide the code for the default language as check_language_code_in_url will do its job in other cases
					unset($rules[$key]);
				}
			}

			// rewrite rules filtered by language
			elseif ($always_rewrite || (strpos($rule, 'post_type=') && !strpos($rule, 'name=')) || ($filter != 'rewrite_rules_array' && $this->options['force_lang'])) {
				if (isset($slug))
					$newrules[$slug.str_replace($wp_rewrite->root, '', $key)] = str_replace(
						array('[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '[1]', '?'),
						array('[9]', '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '?lang=$matches[1]&'),
						$rule
					); // should be enough!

				if ($this->options['hide_default'])
					$newrules[$key] = str_replace('?', '?lang='.$this->options['default_lang'].'&', $rule);

				unset($rules[$key]); // now useless
			}
		}

		// the home rewrite rule
		if ($filter == 'root' && isset($slug))
			$newrules[$slug.'?$'] = $wp_rewrite->index.'?lang=$matches[1]';

		return $newrules + $rules;
	}

} // class Polylang

new Polylang();

<?php
/*
Plugin Name: Polylang
Plugin URI: http://polylang.wordpress.com/
Version: 1.7.5
Author: Frédéric Demarle
Description: Adds multilingual capability to WordPress
Text Domain: polylang
Domain Path: /languages
*/

/*
 * Copyright 2011-2015 Frédéric Demarle
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

// don't access directly
if (!function_exists('add_action'))
	exit();

define('POLYLANG_VERSION', '1.7.5');
define('PLL_MIN_WP_VERSION', '3.8');

define('POLYLANG_BASENAME', plugin_basename(__FILE__)); // plugin name as known by WP

define('POLYLANG_DIR', dirname(__FILE__)); // our directory
define('PLL_INC', POLYLANG_DIR . '/include');
define('PLL_FRONT_INC', POLYLANG_DIR . '/frontend');
define('PLL_ADMIN_INC', POLYLANG_DIR . '/admin');
define('PLL_INSTALL_INC', POLYLANG_DIR . '/install');

// default directory to store user data such as custom flags
if (!defined('PLL_LOCAL_DIR'))
	define('PLL_LOCAL_DIR', WP_CONTENT_DIR . '/polylang');

// includes local config file if exists
if (file_exists(PLL_LOCAL_DIR . '/pll-config.php'))
	include_once(PLL_LOCAL_DIR . '/pll-config.php');

/*
 * controls the plugin, as well as activation, and deactivation
 *
 * @since 0.1
 */
class Polylang {

	/*
	 * constructor
	 *
	 * @since 0.1
	 */
	public function __construct() {
		// FIXME maybe not available on every installations but widely used by WP plugins
		spl_autoload_register(array(&$this, 'autoload')); // autoload classes

		$install = new PLL_Install(POLYLANG_BASENAME);

		// stopping here if we are going to deactivate the plugin (avoids breaking rewrite rules)
		if ($install->is_deactivation())
			return;

		// plugin initialization
		// take no action before all plugins are loaded
		add_action('plugins_loaded', array(&$this, 'init'), 1);

		// override load text domain waiting for the language to be defined
		// here for plugins which load text domain as soon as loaded :(
		if (!defined('PLL_OLT') || PLL_OLT)
			PLL_OLT_Manager::instance();

		// loads the API
		require_once(PLL_INC.'/api.php');

		// WPML API
		if (!defined('PLL_WPML_COMPAT') || PLL_WPML_COMPAT)
			PLL_WPML_Compat::instance();

		// extra code for compatibility with some plugins
		if (!defined('PLL_PLUGINS_COMPAT') || PLL_PLUGINS_COMPAT)
			PLL_Plugins_Compat::instance();
	}

	/*
	 * autoload classes
	 *
	 * @since 1.2
	 *
	 * @param string $class
	 */
	public function autoload($class) {
		// not a Polylang class
		if (0 !== strncmp('PLL_', $class, 4))
			return;

		$class = str_replace('_', '-', strtolower(substr($class, 4)));

		if ((0 === strpos($class, 'choose') || 0 === strpos($class, 'frontend')) && file_exists($file = PLL_FRONT_INC . "/$class.php"))
			require_once($file);

		elseif ((0 === strpos($class, 'install') || 'upgrade' === $class) && file_exists($file = PLL_INSTALL_INC . "/$class.php"))
			require_once($file);

		elseif ((0 === strpos($class, 'admin') || 0 === strpos($class, 'table') || 'settings' === $class || 'wp-import' === $class) && file_exists($file = PLL_ADMIN_INC . "/$class.php"))
			require_once($file);

		elseif (file_exists($file = PLL_INC . "/$class.php"))
			require_once($file);
	}

	/*
	 * defines constants
	 * may be overriden by a plugin if set before plugins_loaded, 1
	 *
	 * @since 1.6
	 */
	static public function define_constants() {
		// our url. Don't use WP_PLUGIN_URL http://wordpress.org/support/topic/ssl-doesnt-work-properly
		if (!defined('POLYLANG_URL'))
			define('POLYLANG_URL', plugins_url('', __FILE__));

		// default url to access user data such as custom flags
		if (!defined('PLL_LOCAL_URL'))
			define('PLL_LOCAL_URL', content_url('/polylang'));
			
		// cookie name. no cookie will be used if set to false
		if (!defined('PLL_COOKIE'))
			define('PLL_COOKIE', 'pll_language');

		// avoid loading polylang admin for frontend ajax requests
		// special test for plupload which does not use jquery ajax and thus does not pass our ajax prefilter
		// special test for customize_save done in frontend but for which we want to load the admin
		if (!defined('PLL_AJAX_ON_FRONT')) {
			$in = isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('upload-attachment', 'customize_save'));
			define('PLL_AJAX_ON_FRONT', defined('DOING_AJAX') && DOING_AJAX && empty($_REQUEST['pll_ajax_backend']) && !$in);
		}

		// admin
		if (!defined('PLL_ADMIN'))
			define('PLL_ADMIN', defined('DOING_CRON') || (is_admin() && !PLL_AJAX_ON_FRONT));

		// settings page whatever the tab
		if (!defined('PLL_SETTINGS'))
			define('PLL_SETTINGS', is_admin() && isset($_GET['page']) && $_GET['page'] == 'mlang');
	}

	/*
	 * Polylang initialization
	 * setups models and separate admin and frontend
	 *
	 * @since 1.2
	 */
	public function init() {
		global $polylang;

		self::define_constants();
		$options = get_option('polylang');

		// plugin upgrade
		if ($options && version_compare($options['version'], POLYLANG_VERSION, '<')) {
			$upgrade = new PLL_Upgrade($options);
			if (!$upgrade->upgrade()) // if the version is too old
				return;
		}

		$class = apply_filters('pll_model', PLL_SETTINGS ? 'PLL_Admin_Model' : 'PLL_Model');
		$model = new $class($options);
		$links_model = $model->get_links_model();

		if (PLL_ADMIN) {
			$polylang = new PLL_Admin($links_model);
			$polylang->init();
		}
		// do nothing on frontend if no language is defined
		elseif ($model->get_languages_list()) {
			$polylang = new PLL_Frontend($links_model);
			$polylang->init();
		}

		if (!$model->get_languages_list())
			do_action('pll_no_language_defined'); // to load overriden textdomains

		// load wpml-config.xml
		if (!defined('PLL_WPML_COMPAT') || PLL_WPML_COMPAT)
			PLL_WPML_Config::instance();
	
		do_action('pll_init');
	}
}

new Polylang();

<?php 
/*
Plugin Name: Polylang
Plugin URI: http://wordpress.org/extend/plugins/polylang/
Version: 0.8.7
Author: F. Demarle
Description: Adds multilingual capability to Wordpress
*/

/*  Copyright 2011-2012 F. Demarle

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, published by
    the Free Software Foundation, either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('POLYLANG_VERSION', '0.8.7');
define('PLL_MIN_WP_VERSION', '3.1');

define('POLYLANG_DIR', dirname(__FILE__)); // our directory
define('PLL_INC', POLYLANG_DIR.'/include');

define('POLYLANG_URL', WP_PLUGIN_URL.'/'.basename(POLYLANG_DIR)); // our url

if (!defined('PLL_LOCAL_DIR'))
	define('PLL_LOCAL_DIR', WP_CONTENT_DIR.'/polylang'); // default directory to store user data such as custom flags

if (!defined('PLL_LOCAL_URL'))
	define('PLL_LOCAL_URL', WP_CONTENT_URL.'/polylang'); // default url to access user data such as custom flags

if (file_exists(PLL_LOCAL_DIR.'/pll-config.php'))
	include_once(PLL_LOCAL_DIR.'/pll-config.php'); // includes local config file if exists

if (!defined('PLL_DISPLAY_ABOUT'))
	define('PLL_DISPLAY_ABOUT', true); // displays the "About Polylang" metabox by default

if (!defined('PLL_DISPLAY_ALL'))
	define('PLL_DISPLAY_ALL', false); // diplaying posts & terms with undefined language is disabled by default (unsupported since 0.7)

if (!defined('PLL_FILTER_HOME_URL'))
	define('PLL_FILTER_HOME_URL', true); // filters the home url (to return the homepage in the right langage) by default

if (!defined('PLL_SYNC'))
	define('PLL_SYNC', true); // synchronisation is enabled by default

require_once(PLL_INC.'/base.php');
require_once(PLL_INC.'/widget.php');
require_once(PLL_INC.'/calendar.php');

// controls the plugin, deals with activation, deactivation, upgrades, initialization as well as rewrite rules
class Polylang extends Polylang_Base {

	function __construct() {
		parent::__construct();
		global $polylang; // globalize the variable to access it in the API

		// manages plugin activation and deactivation
		register_activation_hook( __FILE__, array(&$this, 'activate') );
		register_deactivation_hook( __FILE__, array(&$this, 'deactivate') );

		// stopping here if we are going to deactivate the plugin avoids breaking rewrite rules
		if (isset($_GET['action']) && $_GET['action'] == 'deactivate' && isset($_GET['plugin']) && $_GET['plugin'] == 'polylang/polylang.php')
			return;

		// manages plugin upgrade
		add_filter('upgrader_post_install', array(&$this, 'post_upgrade'));
		add_action('admin_init',  array(&$this, 'admin_init'));

		// plugin and widget initialization
		add_action('init', array(&$this, 'init'));
		add_action('widgets_init', array(&$this, 'widgets_init'));
		add_action('wp_loaded', array(&$this, 'prepare_rewrite_rules'), 20); // after Polylang_base::add_post_types_taxonomies

		// separate admin and frontend
		if (is_admin() && isset($_GET['page']) && $_GET['page'] == 'mlang') {
			require_once(PLL_INC.'/admin-base.php');
			require_once(PLL_INC.'/admin.php');
			$polylang = new Polylang_Admin();
		}
		// avoid loading polylang admin filters for frontend ajax requests if 'pll_load_front' is set (thanks to g100g)
		elseif (is_admin() && !(defined('DOING_AJAX') && isset($_REQUEST['pll_load_front']))) {
			require_once(PLL_INC.'/admin-base.php');
			require_once(PLL_INC.'/admin-filters.php');
			$polylang = new Polylang_Admin_Filters();
		}
		else {
			require_once(PLL_INC.'/core.php');
			$polylang = new Polylang_Core();
		}

		// loads the API
		require_once(PLL_INC.'/api.php');
	}

	// plugin activation for multisite
	function activate() {
		global $wp_version, $wpdb;
		$style = '<p style = "font-family: sans-serif; font-size: 12px; color: #333; margin: -5px">%s</p>';

		if (version_compare($wp_version, PLL_MIN_WP_VERSION , '<')) 
			die (sprintf($style, sprintf(__('You are using WordPress %s. Polylang requires at least WordPress %s.', 'polylang'), $wp_version, PLL_MIN_WP_VERSION)));

		// check if it is a network activation - if so, run the activation function for each blog
		if (is_multisite() && isset($_GET['networkwide']) && ($_GET['networkwide'] == 1)) {
			foreach ($wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs")) as $blog_id) {
				switch_to_blog($blog_id);
				$r = $this->_activate();
			}
			restore_current_blog();
		}
		else
			$r = $this->_activate();

		if (!$r)
			die (sprintf($style, __('For some reasons, Polylang could not create a table in your database.', 'polylang')));
	}

	// plugin activation
	function _activate() {
		// create the termmeta table - not provided by WP by default - if it does not already exists
		// uses exactly the same model as other meta tables to be able to use access functions provided by WP 
		global $wpdb;
		$charset_collate = empty($wpdb->charset) ? '' : "DEFAULT CHARACTER SET $wpdb->charset";  
		$charset_collate .= empty($wpdb->collate) ? '' : " COLLATE $wpdb->collate";
		$table = $wpdb->prefix . 'termmeta';
		
		$r = $wpdb->query("CREATE TABLE IF NOT EXISTS $table (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			term_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY term_id (term_id),
			KEY meta_key (meta_key)
			) $charset_collate;");

		if ($r === false)
			return false;

		// codex tells to use the init action to call register_taxonomy but I need it now for my rewrite rules
		register_taxonomy('language', null , array('label' => false, 'query_var'=>'lang')); 

		// defines default values for options in case this is the first installation
		$options = get_option('polylang');
		if (!$options) {
			$options['browser'] = 1; // default language for the front page is set by browser preference
			$options['rewrite'] = 1; // remove /language/ in permalinks (was the opposite before 0.7.2)
			$options['hide_default'] = 0; // do not remove URL language information for default language
			$options['force_lang'] = 0; // do not add URL language information when useless
			$options['redirect_lang'] = 0; // do not redirect the language page to the homepage
		}
		$options['version'] = POLYLANG_VERSION;
		update_option('polylang', $options);

		// add our rewrite rules
		$this->add_post_types_taxonomies();
		$this->prepare_rewrite_rules();
		flush_rewrite_rules();
		return true;
	}

	// plugin deactivation for multisite
	function deactivate() {
		global $wpdb;

		// check if it is a network deactivation - if so, run the deactivation function for each blog
		if (is_multisite() && isset($_GET['networkwide']) && ($_GET['networkwide'] == 1)) {
			foreach ($wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs")) as $blog_id) {
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

	// restores the local_flags directory after upgrade from version 0.5.1 or older
	function post_upgrade() {
		// nothing to restore
		if (!@is_dir($upgrade_dir = WP_CONTENT_DIR . '/upgrade/polylang/local_flags'))
			return true;

		// don't move if the directory is empty
		$contents = @scandir($upgrade_dir);
		if (is_array($contents) && ($files = array_diff($contents, array(".", "..", ".DS_Store", "_notes", "Thumbs.db"))) && empty($files))
			return true;

		// move the directory to wp-content
		if (!@rename($upgrade_dir, PLL_LOCAL_DIR))
			return new WP_Error('polylang_restore_error', sprintf('%s<br />%s',
				__('Error: Restore of local flags failed!', 'polylang'),
				sprintf(__('Please move your local flags from %s to %s', 'polylang'), esc_html($upgrade_dir), '<strong>'.esc_html(PLL_LOCAL_DIR).'</strong>')
			));

		@rmdir(WP_CONTENT_DIR . '/upgrade/polylang');
		return true;
	}

	// upgrades from old translation used up to V0.4.4 to new model used in V0.5+ 
	function upgrade_translations($type, $ids) {
		$listlanguages = $this->get_languages_list();
		foreach ($ids as $id) {
			$lang = call_user_func(array(&$this, 'get_'.$type.'_language'), $id);
			if (!$lang)
				continue;

			$tr = array();
			foreach ($listlanguages as $language) {
				if ($meta = get_metadata($type, $id, '_lang-'.$language->slug, true))
					$tr[$language->slug] = $meta;
			}

			if (!empty($tr)) {
				$tr = serialize(array_merge(array($lang->slug => $id), $tr));
				update_metadata($type, $id, '_translations', $tr);
			}
		}
	}

	// manage upgrade even when it is done manually
	function admin_init() {
		$options = get_option('polylang');
		if (version_compare($options['version'], POLYLANG_VERSION, '<')) {

			if (version_compare($options['version'], '0.4', '<'))
				$options['hide_default'] = 0; // option introduced in 0.4

			// translation model changed in V0.5
			if (version_compare($options['version'], '0.5', '<')) {
				$ids = get_posts(array('numberposts' => -1, 'fields' => 'ids', 'post_type' => 'any', 'post_status' => 'any'));
				$this->upgrade_translations('post', $ids);
				$ids = get_terms($this->taxonomies, array('get' => 'all', 'fields' => 'ids'));
				$this->upgrade_translations('term', $ids);
			}

			// translation model changed in V0.5
			// deleting the old one has been delayed in V0.6 (just in case...) 
			if (version_compare($options['version'], '0.6', '<')) {
				$listlanguages = $this->get_languages_list();

				$ids = get_posts(array('numberposts'=> -1, 'fields' => 'ids', 'post_type'=>'any', 'post_status'=>'any'));
				foreach ($ids as $id) {
					foreach ($listlanguages as $lang)
						delete_post_meta($id, '_lang-'.$lang->slug);
				}

				$ids = get_terms($this->taxonomies, array('get' => 'all', 'fields' => 'ids'));
				foreach ($ids as $id) {
					foreach ($listlanguages as $lang)
						delete_metadata('term', $id, '_lang-'.$lang->slug);
				}
			}

			if (version_compare($options['version'], '0.7', '<'))
				$options['force_lang'] = 0; // option introduced in 0.7

			// string translation storage model changed and option added in 0.8
			if (version_compare($options['version'], '0.8dev1', '<')) {
				if (function_exists('base64_decode')) {
					$mo = new MO();
					foreach ($this->get_languages_list() as $language) {
						$reader = new POMO_StringReader(base64_decode(get_option('polylang_mo'.$language->term_id)));
						$mo->import_from_reader($reader);
						$this->mo_export($mo, $language);
					}
				}
				$options['redirect_lang'] = 0; // option introduced in 0.8
			}

			if (version_compare($options['version'], '0.8.2', '<'))
				flush_rewrite_rules(); // rewrite rules have been modified in 0.7.1 & 0.7.2 & 0.8 & 0.8.1 & 0.8.2 

			$options['version'] = POLYLANG_VERSION;
			update_option('polylang', $options);
		}
	}

	// some initialization
	function init() {
		global $wpdb;
		$wpdb->termmeta = $wpdb->prefix . 'termmeta'; // registers the termmeta table in wpdb
		$options = get_option('polylang');

		// registers the language taxonomy
		// codex: use the init action to call this function
		// object types will be set later once all custom post types are registered
		register_taxonomy('language', null, array(
			'label' => false,
			'public' => false, // avoid displaying the 'like post tags text box' in the quick edit
			'query_var'=>'lang',
			'update_count_callback' => '_update_post_term_count'));

		// optionaly removes 'language' in permalinks so that we get http://www.myblog/en/ instead of http://www.myblog/language/en/
		// language information always in front of the uri ('with_front' => false)
		// the 3rd parameter structure has been modified in WP 3.4
		add_permastruct('language', $options['rewrite'] ? '%language%' : 'language/%language%', version_compare($GLOBALS['wp_version'], '3.4' , '<') ? false : array('with_front' => false));

		load_plugin_textdomain('polylang', false, basename(POLYLANG_DIR).'/languages'); // plugin i18n
	}

	// registers our widgets
	function widgets_init() {
		register_widget('Polylang_Widget');

		// overwrites the calendar widget to filter posts by language
		unregister_widget('WP_Widget_Calendar');
		register_widget('Polylang_Widget_Calendar');
	}

	// complete our taxonomy and add rewrite rules filters once custom post types and taxonomies are registered (normally in init)
	function prepare_rewrite_rules() {
		foreach ($this->post_types as $post_type)
			register_taxonomy_for_object_type('language', $post_type);

		// don't modify the rules if there is no languages created yet
		if (!$this->get_languages_list())
			return;

		$types = array_merge(array('post', 'date', 'root', 'comments', 'search', 'author', 'page'), array_keys($GLOBALS['wp_rewrite']->extra_permastructs));
		$types = apply_filters('pll_rewrite_rules', $types); // allow plugins to add rewrite rules to the language filter
		foreach ($types as $type)
			add_filter($type . '_rewrite_rules', array(&$this, 'rewrite_rules'));

		add_filter('rewrite_rules_array', array(&$this, 'rewrite_rules')); // needed for post type archives
	}

	// the rewrite rules !
	// always make sure the default language is at the end in case the language information is hidden for default language
	// thanks to brbrbr http://wordpress.org/support/topic/plugin-polylang-rewrite-rules-not-correct
	function rewrite_rules($rules) {
		// suppress the rules created by WordPress for our taxonomy
		if (($current_filter = current_filter()) == 'language_rewrite_rules')
			return array();

		global $wp_rewrite;
		$options = get_option('polylang');
		$always_rewrite = in_array(str_replace('_rewrite_rules', '', $current_filter), array('date', 'root', 'comments', 'author', 'post_format'));
		$newrules = array();

		foreach ($this->get_languages_list() as $language)
			if (!$options['hide_default'] || $options['default_lang'] != $language->slug)
				$languages[] = $language->slug;

		if (isset($languages))
			$slug = $wp_rewrite->root . ($options['rewrite'] ? '' : 'language/') . '('.implode('|', $languages).')/';

		foreach ($rules as $key => $rule) {
			// we don't need the lang parameter for post types and taxonomies
			// moreover adding it would create issues for pages and taxonomies
			if ($options['force_lang'] && in_array(str_replace('_rewrite_rules', '', $current_filter), array_merge($this->post_types, $this->taxonomies))) {
				if (isset($slug))
					$newrules[$slug.str_replace($wp_rewrite->root, '', $key)] = str_replace(array('[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '[1]'),
						array('[9]', '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]'), $rule); // hopefully it is sufficient !

				if ($options['hide_default'])
					$newrules[$key] = $rules[$key];

				unset($rules[$key]); // now useless
			}

			// rewrite rules filtered by language
			elseif ($always_rewrite || strpos($rule, 'post_type=') || ($current_filter != 'rewrite_rules_array' && $options['force_lang'])) {
				if (isset($slug))
					$newrules[$slug.str_replace($wp_rewrite->root, '', $key)] = str_replace(array('[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '[1]', '?'), 
						array('[9]', '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '?lang=$matches[1]&'), $rule); // hopefully it is sufficient !

				if ($options['hide_default'])
					$newrules[$key] = str_replace('?', '?lang='.$options['default_lang'].'&', $rule);

				unset($rules[$key]); // now useless
			}				
		}

		// the home rewrite rule
		if ($current_filter == 'root_rewrite_rules' && isset($slug))
			$newrules[$slug.'?$'] = 'index.php?lang=$matches[1]';

		return $newrules + $rules;
	}

} // class Polylang

if (class_exists("Polylang"))
	new Polylang();
?>

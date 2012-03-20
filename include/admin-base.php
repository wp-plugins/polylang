<?php

// functions common to all admin panels
class Polylang_Admin_Base extends Polylang_Base {
	function __construct() {
		parent::__construct();

		// filter admin language for users
		add_filter('locale', array(&$this, 'get_locale'));

		// additionnal filters and actions
		add_action('admin_init',  array(&$this, 'admin_init_base'));

		// adds the link to the languages panel in the wordpress admin menu
		add_action('admin_menu', array(&$this, 'add_menus'));

		// setup js scripts andd css styles
		add_action('admin_enqueue_scripts', array(&$this,'admin_enqueue_scripts'));
	}

	// returns the locale based on user preference
	function get_locale($locale) {
		// get_current_user_id uses wp_get_current_user which may not be available the first time(s) get_locale is called
		return function_exists('wp_get_current_user') && ($loc = get_user_meta(get_current_user_id(), 'user_lang', 'true')) ? $loc : $locale;
	}

	// set text direction if the user set its own language
	function admin_init_base() {
		global $wpdb, $wp_locale;
		$lang_id = $wpdb->get_var($wpdb->prepare("SELECT t.term_id FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = 'language' AND tt.description = %s LIMIT 1", get_locale())); // no function exists to get term by description
		if ($lang_id)
			$wp_locale->text_direction = get_metadata('term', $lang_id, '_rtl', true) ? 'rtl' : 'ltr';
	}

	// adds the link to the languages panel in the wordpress admin menu
	function add_menus() {
		add_submenu_page('options-general.php', __('Languages', 'polylang'), __('Languages', 'polylang'), 'manage_options', 'mlang',  array(&$this, 'languages_page'));

		// adds the about box the languages admin panel
		// test of $_GET['tab'] avoids displaying the automatically generated screen options on other tabs
		if (PLL_DISPLAY_ABOUT && isset($_GET['page']) && $_GET['page'] == 'mlang' && (!isset($_GET['tab']) || $_GET['tab'] == 'lang'))
			add_meta_box('pll_about_box', __('About Polylang', 'polylang'), array(&$this,'about'), 'settings_page_mlang', 'normal', 'high');
	}

	// setup js scripts & css styles (only on the relevant pages)
	function admin_enqueue_scripts() {
		$screen = get_current_screen();

		// FIXME keep the script in header to be sure it is loaded before post.js otherwise a non filtered tag cloud appears in tag cloud metabox
		if ($screen->base == 'settings_page_mlang' || $screen->base == 'post' || $screen->base == 'edit-tags')
			wp_enqueue_script('polylang_admin', POLYLANG_URL .'/js/admin.js', array('jquery', 'wp-ajax-response'), POLYLANG_VERSION);

		if ($screen->base == 'settings_page_mlang' || $screen->base == 'post' || $screen->base == 'edit-tags' || $screen->base == 'edit')
			wp_enqueue_style('polylang_admin', POLYLANG_URL .'/css/admin.css', array(), POLYLANG_VERSION);

		if ($screen->base == 'settings_page_mlang') {
			wp_enqueue_script('postbox');
		}
	}

	// downloads mofiles
	function download_mo($locale, $upgrade = false) {
		global $wp_version;
		$mofile = WP_LANG_DIR."/$locale.mo";

		// does file exists ?
		if ((file_exists($mofile) && !$upgrade) || $locale == 'en_US')
			return true;

		// does language directory exists ?
		if (!is_dir(WP_LANG_DIR)) {
			if (!@mkdir(WP_LANG_DIR))
				return false;
		}

		// will first look in tags/ (most languages) then in branches/ (only Greek ?)
		$base = 'http://svn.automattic.com/wordpress-i18n/'.$locale;
		$bases = array($base.'/tags/', $base.'/branches/');

		foreach ($bases as $base) {
			// get all the versions available in the subdirectory
			$resp = wp_remote_get($base);
			if (is_wp_error($resp) || 200 != $resp['response']['code'])
				continue;

			preg_match_all('#>([0-9\.]+)\/#', $resp['body'], $matches);
			if (empty($matches[1]))
				continue;

			rsort($matches[1]); // sort from newest to oldest
			$versions = $matches[1];

			$newest = $upgrade ? $upgrade : $wp_version;
			foreach ($versions as $key=>$version) {
				// will not try to download a too recent mofile
				if (version_compare($version, $newest, '>'))
					unset($versions[$key]);
				// will not download an older version if we are upgrading
				if ($upgrade && version_compare($version, $wp_version, '<='))
					unset($versions[$key]);
			}

			$versions = array_splice($versions, 0, 5); // reduce the number of versions to test to 5
			$args = array('timeout' => 30, 'stream' => true);

			// try to download the file
			foreach ($versions as $version) {
				$resp = wp_remote_get($base."$version/messages/$locale.mo", $args + array('filename' => $mofile));
				if (is_wp_error($resp) || 200 != $resp['response']['code'])
					continue;

				// try to download ms and continents-cities files if exist (will not return false if failed)
				// with new files introduced in WP 3.4
				foreach (array("ms", "continent-cities", "admin", "admin-network") as $file)
					wp_remote_get($base."$version/messages/$file-$locale.mo", $args + array('filename' => WP_LANG_DIR."/$file-$locale.mo"));

				// try to download theme files if exist (will not return false if failed)
				// FIXME not updated when the theme is updated outside a core update
				foreach (array("twentyten", "twentyeleven", "twentytwelve") as $theme)
					wp_remote_get($base."$version/messages/$theme/$locale.mo", $args + array('filename' => get_theme_root()."/$theme/languages/$locale.mo"));

				return true;
			}
		}
		// we did not succeeded to download a file :(
		return false;
	}

	// returns options available for the language switcher (menu or widget)
	// FIXME do not include the dropdown in menu yet since I need to work on js
	function get_switcher_options($type = 'widget', $key ='string') {
		$options = array (
			'show_names' => array('string' => __('Displays language names', 'polylang'), 'default' => 1),
			'show_flags' => array('string' => __('Displays flags', 'polylang'), 'default' => 0),
			'force_home' => array('string' => __('Forces link to front page', 'polylang'), 'default' => 0),
			'hide_current' => array('string' => __('Hides the current language', 'polylang'), 'default' => 0),
		);
		$menu_options = array('switcher' => array('string' => __('Displays a language switcher at the end of the menu', 'polylang'), 'default' => 0));
		$widget_options = array('dropdown' => array('string' => __('Displays as dropdown', 'polylang'), 'default' => 0));
		$options = ($type == 'menu') ? array_merge($menu_options, $options) : array_merge($options, $widget_options);
		return array_map(create_function('$v', "return \$v['$key'];"), $options);
	}

	// register strings for translation making sure it is not duplicate
	function register_string($name, $string) {
		$to_register = array('name'=> $name, 'string' => $string);
		if (!in_array($to_register, $this->strings))
			$this->strings[] = $to_register;
	}
} // class Polylang_Admin_Base

?>

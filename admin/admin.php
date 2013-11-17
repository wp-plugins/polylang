<?php

/*
 * admin side controller
 *
 * @since 1.2
 */
class PLL_Admin extends PLL_Base {
	public $pref_lang;
	public $settings_page, $filters, $filters_columns, $filters_post, $filters_term, $nav_menu, $sync, $filters_media;

	/*
	 * loads the polylang text domain
	 * setups filters and action needed on all admin pages and on plugins page
	 * loads the settings pages or the filters base on the request
	 * manages the admin language filter and the "admin preferred language"
	 *
	 * @since 1.2
	 *
	 * @param object $links_model
	 */
	public function __construct(&$links_model) {
		parent::__construct($links_model);

		// plugin i18n, only needed for backend
		load_plugin_textdomain('polylang', false, basename(POLYLANG_DIR).'/languages');

		// adds the link to the languages panel in the wordpress admin menu
		add_action('admin_menu', array(&$this, 'add_menus'));

		// setup js scripts and css styles
		add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));

		// adds a 'settings' link in the plugins table
		add_filter('plugin_action_links_' . POLYLANG_BASENAME, array(&$this, 'plugin_action_links'));
		add_action('in_plugin_update_message-' . POLYLANG_BASENAME, array(&$this, 'plugin_update_message'), 10, 2);
	}

	/*
	 * loads the polylang text domain
	 * setups filters and action needed on all admin pages and on plugins page
	 * loads the settings pages or the filters base on the request
	 * manages the admin language filter and the "admin preferred language"
	 *
	 * @since 1.2
	 *
	 * @param object $links_model
	 */
	public function init() {
		if (PLL_SETTINGS)
			$this->settings_page = new PLL_Settings($this->model);

		if (!$this->model->get_languages_list())
			return;

		// filter admin language for users
		// we must not call user info before WordPress defines user roles in wp-settings.php
		add_filter('setup_theme', array(&$this, 'init_user'));
		add_filter('locale', array(&$this, 'get_locale'));

		// adds the languages in admin bar
		// FIXME: OK for WP 3.2 and newer (the admin bar is not displayed on admin side for WP 3.1)
		add_action('admin_bar_menu', array(&$this, 'admin_bar_menu'), 100); // 100 determines the position

		// setup filters for admin pages
		if (!PLL_SETTINGS)
			add_action('wp_loaded', array(&$this, 'add_filters'));
	}

	/*
	 * adds the link to the languages panel in the wordpress admin menu
	 *
	 * @since 0.1
	 */
	public function add_menus() {
		add_submenu_page('options-general.php', $title = __('Languages', 'polylang'), $title, 'manage_options', 'mlang',
			PLL_SETTINGS ? array($this->settings_page, 'languages_page') : create_function('', ''));
	}

	/*
	 * setup js scripts & css styles (only on the relevant pages)
	 *
	 * @since 0.6
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		// for each script:
		// 0 => the pages on which to load the script
		// 1 => the scripts it needs to work
		// 2 => 1 if loaded even if languages have not been defined yet, 0 otherwise
		$scripts = array(
			'admin' => array( array('settings_page_mlang'), array('jquery', 'wp-ajax-response', 'postbox'), 1 ),
			'post'  => array( array('post', 'media', 'async-upload', 'edit'),  array('jquery', 'wp-ajax-response'), 0 ),
			'term'  => array( array('edit-tags'), array('jquery', 'wp-ajax-response'), 0 ),
			'user'  => array( array('profile', 'user-edit'), array('jquery'), 0 ),
		);

		foreach ($scripts as $script => $v)
			if (in_array($screen->base, $v[0]) && ($v[2] || $this->model->get_languages_list()))
				wp_enqueue_script('pll_'.$script, POLYLANG_URL .'/js/'.$script.$suffix.'.js', $v[1], POLYLANG_VERSION);

		wp_enqueue_style('polylang_admin', POLYLANG_URL .'/css/admin'.$suffix.'.css', array(), POLYLANG_VERSION);
	}

	/*
	 * adds a 'settings' link in the plugins table
	 *
	 * @since 0.1
	 *
	 * @param array $links list of links associated to the plugin
	 * @return array modified list of links
	 */
	public function plugin_action_links($links) {
		array_unshift($links, '<a href="admin.php?page=mlang">' . __('Settings', 'polylang') . '</a>');
		return $links;
	}

	/*
	 * adds the upgrade notice in plugins table
	 *
	 * @since 1.1.6
	 *
	 * @param array $plugin_data not used
	 * @param object $r plugin update data
	 */
	function plugin_update_message($plugin_data, $r) {
		if (isset($r->upgrade_notice))
			printf('<p style="margin: 3px 0 0 0; border-top: 1px solid #ddd; padding-top: 3px">%s</p>', $r->upgrade_notice);
	}

	/*
	 * defines the backend language and the admin language filter based on user preferences
	 *
	 * @since 1.2.3
	 */
	public function init_user() {
		// admin language filter
		if (!defined('DOING_AJAX') && !empty($_GET['lang']) && !is_numeric($_GET['lang']))
			update_user_meta(get_current_user_id(), 'pll_filter_content', ($lang = $this->model->get_language($_GET['lang'])) ? $lang->slug : '');

		// set preferred language for use in filters
		$this->pref_lang = $this->model->get_language(($lg = get_user_meta(get_current_user_id(), 'pll_filter_content', true)) ? $lg : $this->options['default_lang']);
		$this->pref_lang = apply_filters('pll_admin_preferred_language', $this->pref_lang);

		// inform that the admin language has been set
		$curlang = $this->model->get_language(get_locale());
		$GLOBALS['text_direction'] = $curlang->is_rtl ? 'rtl' : 'ltr'; // force text direction according to language setting
		do_action('pll_language_defined', $curlang->slug, $curlang);
	}

	/*
	 * get the locale based on user preference
	 *
	 * @since 0.4
	 *
	 * @param string $locale
	 * @return string modified locale
	 */
	public function get_locale($locale) {
		return ($loc = get_user_meta(get_current_user_id(), 'user_lang', 'true')) ? $loc : $locale;
	}

	/*
	 * setup filters for admin pages
	 *
	 * @since 1.2
	 */
	public function add_filters() {
		$this->links = new PLL_Links($this->links_model);
		$this->filters = new PLL_Admin_Filters($this->links_model, $this->pref_lang);
		$this->filters_columns = new PLL_Admin_Filters_Columns($this->model);
		$this->filters_post = new PLL_Admin_Filters_Post($this->model, $this->pref_lang);
		$this->filters_term = new PLL_Admin_Filters_Term($this->model, $this->pref_lang);
		$this->nav_menu = new PLL_Admin_Nav_Menu($this->model);
		$this->sync = new PLL_Admin_Sync($this->model);

		if ($this->options['media_support'])
			$this->filters_media = new PLL_Admin_Filters_Media($this->model, $this->pref_lang);
	}

	/*
	 * adds the languages list in admin bar for the admin languages filter
	 *
	 * @since 0.9
	 *
	 * @param object $wp_admin_bar
	 */
	public function admin_bar_menu($wp_admin_bar) {
		$url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$all_item = (object) array(
			'slug' => 'all',
			'name' => __('Show all languages', 'polylang'),
			'flag' => sprintf('<img src="%s" style="margin-bottom: -3px"/>', POLYLANG_URL .'/flags/all.png')
		);

		// $_GET['lang'] is numeric when editing a language, not when selecting a new language in the filter
		$selected = !empty($_GET['lang']) && !is_numeric($_GET['lang']) && ($lang = $this->model->get_language($_GET['lang'])) ? $lang->slug :
			(($lg = get_user_meta(get_current_user_id(), 'pll_filter_content', true)) ? $lg : 'all');

		$selected = ('all' == $selected) ? $all_item : $this->model->get_language($selected);

		$wp_admin_bar->add_menu(array(
			'id'     => 'languages',
			'title'  =>  empty($selected->flag) ? esc_html($selected->name) : $selected->flag .'&nbsp;'. esc_html($selected->name),
			'meta'  => array('title' => __('Filters content by language', 'polylang')),
		));

		foreach (array_merge(array($all_item), $this->model->get_languages_list()) as $lang) {
			if ($selected == $lang->slug)
				continue;

			$wp_admin_bar->add_menu(array(
				'parent' => 'languages',
				'id'     => $lang->slug,
				'title'  => empty($lang->flag) ? esc_html($lang->name) : $lang->flag .'&nbsp;'. esc_html($lang->name),
				'href'   => esc_url(add_query_arg('lang', $lang->slug, $url)),
			));
		}
	}

	/*
	 * downloads mofiles from http://svn.automattic.com/wordpress-i18n/
	 * FIXME is it the best class for this?
	 * FIXME use language packs API coming with WP 3.7 instead
	 *
	 * @since 0.6
	 *
	 * @param string $locale locale to download
	 * @param bool $upgrade optional true if this is an upgrade, false if this is the first download, defaults to false
	 * @return bool true on success, false otherwise
	 */
	static public function download_mo($locale, $upgrade = false) {
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
				if (is_wp_error($resp) || 200 != $resp['response']['code']) {
					unlink($mofile); // otherwise we download a gzipped 404 page
					continue;
				}
				// try to download ms and continents-cities files if exist (will not return false if failed)
				// with new files introduced in WP 3.4
				foreach (array('ms', 'continents-cities', 'admin', 'admin-network') as $file) {
					$resp = wp_remote_get($base."$version/messages/$file-$locale.mo", $args + array('filename' => WP_LANG_DIR."/$file-$locale.mo"));
					if (is_wp_error($resp) || 200 != $resp['response']['code'])
						unlink(WP_LANG_DIR."/$file-$locale.mo");
				}
				// try to download theme files if exist (will not return false if failed)
				// FIXME not updated when the theme is updated outside a core update
				foreach (array('twentyten', 'twentyeleven', 'twentytwelve', 'twentythirteen') as $theme) {
					if (!is_dir($theme_dir = get_theme_root()."/$theme/languages"))
						continue; // the theme is not present

					$resp = wp_remote_get($base."$version/messages/$theme/$locale.mo", $args + array('filename' => "$theme_dir/$locale.mo"));
					if (is_wp_error($resp) || 200 != $resp['response']['code'])
						unlink("$theme_dir/$locale.mo");
				}
				return true;
			}
		}
		// we did not succeeded to download a file :(
		return false;
	}

}

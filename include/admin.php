<?php

require_once(PLL_INC.'/admin-filters.php');
require_once(PLL_INC.'/list-table.php');

// setups the Polylang admin panel and calls for other admin related classes
class Polylang_Admin extends Polylang_Base {
	function __construct() {
		new Polylang_Admin_Filters();

		// adds a 'settings' link in the plugins table
		$plugin_file = basename(POLYLANG_DIR).'/polylang.php';
		add_filter('plugin_action_links_'.$plugin_file, array(&$this, 'plugin_action_links'));

		// adds the link to the languages panel in the wordpress admin menu
		add_action('admin_menu', array(&$this, 'add_menus'));

		// ugrades languages files after a core upgrade (timing is important)
		// FIXME private action ? is there a better way to do this ?
		add_action( '_core_updated_successfully', array(&$this, 'upgrade_languages'), 1); // since WP 3.3
	}

	// adds a 'settings' link in the plugins table
	function plugin_action_links($links) {
		$settings_link = '<a href="admin.php?page=mlang">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	// adds the link to the languages panel in the wordpress admin menu
	function add_menus() {
		add_submenu_page('options-general.php', __('Languages', 'polylang'), __('Languages', 'polylang'), 'manage_options', 'mlang',  array(&$this, 'languages_page'));
	}

	// used to update the translation when a language slug has been modified
	function update_translations($type, $ids, $old_slug) {
		foreach ($ids as $id) {
			$tr = get_metadata($type, $id, '_translations', true);
			if ($tr) {
				$tr = unserialize($tr);
				$tr[$_POST['slug']] = $tr[$old_slug];
				unset($tr[$old_slug]);
				update_metadata($type, $id, '_translations', serialize($tr));
			}
		}
	}

	// used to delete the translation when a language is deleted
	function delete_translations($type, $ids, $old_slug) {
		foreach ($ids as $id) {
			$tr = get_metadata($type, $id, '_translations', true);
			if ($tr) {
				$tr = unserialize($tr);
				unset($tr[$old_slug]);
				update_metadata($type, $id, '_translations', serialize($tr));
			}
		}
	}

	// the languages panel
	function languages_page() {
		global $wp_rewrite;
		$options = get_option('polylang');

		// for nav menus form
		$locations = get_registered_nav_menus();
		$menus = wp_get_nav_menus();
		$menu_lang = get_option('polylang_nav_menus');

		// for widgets
		$widget_lang = get_option('polylang_widgets');

		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

		switch ($action) {
			case 'add':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );
				$error = $this->validate_lang();

				if ($error == 0) {
					$r = wp_insert_term($_POST['name'],'language',array('slug'=>$_POST['slug'], 'description'=>$_POST['description'], 'term_group'=>$_POST['term_group']));
					update_metadata('term', $r['term_id'], '_rtl', $_POST['rtl']);

					if (!isset($options['default_lang'])) { // if this is the first language created, set it as default language
						$options['default_lang'] = $_POST['slug'];
						update_option('polylang', $options);
					}

					$wp_rewrite->flush_rules(); // refresh rewrite rules

					if (!$this->download_mo($_POST['description']))
						$error = 5;
				}

				wp_redirect('admin.php?page=mlang'. ($error ? '&error='.$error : '') ); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'delete':
				check_admin_referer('delete-lang');

				if (isset($_GET['lang']) && $_GET['lang']) {
					$lang_id = (int) $_GET['lang'];
					$lang = $this->get_language($lang_id);
					$lang_slug = $lang->slug;

					// update the language slug in posts meta
					$posts = get_posts(array('numberposts'=>-1, 'fields' => 'ids', 'meta_key'=>'_translations', 'post_type'=>'any', 'post_status'=>'any'));
					$this->delete_translations('post', $posts, $lang_slug);

					// update the language slug in categories & post tags meta
					$terms= get_terms(get_taxonomies(array('show_ui'=>true)), array('get'=>'all', 'fields'=>'ids'));
					$this->delete_translations('term', $terms, $lang_slug);

					// FIXME should find something more efficient (with a sql query ?)
					foreach ($terms as $id) {
						if ($this->get_term_language($id)->term_id == $lang_id)
							$this->delete_term_language($id); // delete language of this term
					}

					// delete menus locations
					foreach ($locations as $location => $description)
						unset($menu_lang[$location][$lang_slug]);
					update_option('polylang_nav_menus', $menu_lang);

					// delete language option in widgets
					foreach ($widget_lang as $key=>$lang) {
						if ($lang == $lang_slug)
							unset ($widget_lang[$key]);
					}
					update_option('polylang_widgets', $widget_lang);

					// delete the string translations
					delete_option('polylang_mo'.$lang_id);

					// delete the language itself
					delete_metadata('term', $lang_id, '_rtl');
					wp_delete_term($lang_id, 'language');

					// oops ! we deleted the default language...
					if ($options['default_lang'] == $lang_slug)	{
						if ($listlanguages = $this->get_languages_list())
							$options['default_lang'] = $listlanguages[0]->slug; // arbitrary choice...
						else
							unset($options['default_lang']);
						update_option('polylang', $options);

					$wp_rewrite->flush_rules(); // refresh rewrite rules
					}
				}
				wp_redirect('admin.php?page=mlang'); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'edit':
				if (isset($_GET['lang']) && $_GET['lang']) {
					$edit_lang = $this->get_language((int) $_GET['lang']);
					$rtl = get_metadata('term', $edit_lang->term_id, '_rtl', true);
				}
				break;

			case 'update':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );
				$lang_id = (int) $_POST['lang_id'];
				$lang = $this->get_language($lang_id);
				$error = $this->validate_lang($lang);

				if ($error == 0) {
					// Update links to this language in posts and terms in case the slug has been modified
					$old_slug = $lang->slug;

					if ($old_slug != $_POST['slug']) {
						// update the language slug in posts meta
						$posts = get_posts(array('numberposts'=>-1, 'fields' => 'ids', 'meta_key'=>'_translations', 'post_type'=>'any', 'post_status'=>'any'));
						$this->update_translations('post', $posts, $old_slug);

						// update the language slug in categories & post tags meta
						$terms = get_terms(get_taxonomies(array('show_ui'=>true)), array('get'=>'all', 'fields'=>'ids'));
						$this->update_translations('term', $terms, $old_slug);

						// update menus locations
						foreach ($locations as $location => $description) {
							if (isset($menu_lang[$location][$old_slug])) {
								$menu_lang[$location][$_POST['slug']] = $menu_lang[$location][$old_slug];
								unset($menu_lang[$location][$old_slug]);
							}
						}
						update_option('polylang_nav_menus', $menu_lang);

						// update language option in widgets
						foreach ($widget_lang as $key=>$lang) {
							if ($lang == $old_slug)
								$widget_lang[$key] = $_POST['slug'];
						}
						update_option('polylang_widgets', $widget_lang);

						// update the default language option if necessary
						if ($options['default_lang'] == $old_slug) {
							$options['default_lang'] = $_POST['slug'];
							update_option('polylang', $options);
						}
					}

					// and finally update the language itself
					$args = array('name'=>$_POST['name'], 'slug'=>$_POST['slug'], 'description'=>$_POST['description'], 'term_group'=>$_POST['term_group']);
					wp_update_term($lang_id, 'language', $args);
					update_metadata('term', $lang_id, '_rtl', $_POST['rtl']);

					$wp_rewrite->flush_rules(); // refresh rewrite rules
				}

				wp_redirect('admin.php?page=mlang'. ($error ? '&error='.$error : '') ); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'nav-menus':
				check_admin_referer( 'nav-menus-lang', '_wpnonce_nav-menus-lang' );

				$menu_lang = $_POST['menu-lang'];
				foreach ($locations as $location => $description)
					foreach ($this->get_switcher_options('menu') as $key => $str)
						$menu_lang[$location][$key] = isset($menu_lang[$location][$key]) ? 1 : 0;

				update_option('polylang_nav_menus', $menu_lang);
				break;

			case 'string-translation':
				check_admin_referer( 'string-translation', '_wpnonce_string-translation' );

				$strings = $this->get_strings();
				$mo = new MO();

				foreach ($this->get_languages_list() as $language) {
					$reader = new POMO_StringReader(base64_decode(get_option('polylang_mo'.$language->term_id)));
					$mo->import_from_reader($reader);

					foreach ($_POST['translation'][$language->name] as $key=>$translation) {
						$mo->add_entry($mo->make_entry($strings[$key]['string'], stripslashes($translation)));
					}
					// FIXME should I clean the mo object to remove unused strings ?
					// use base64_encode to store binary mo data in database text field
					global $wp_version;
					if (version_compare($wp_version, '3.3', '<')) {
						// backward compatibility for WP < 3.3
						require_once(PLL_INC . '/mo.php');
						update_option('polylang_mo'.$language->term_id, base64_encode(pll_mo_export($mo)));
					}
					else
						update_option('polylang_mo'.$language->term_id, base64_encode($mo->export())); // MO::export since WP 3.3
				}

				$paged = isset($_GET['paged']) ? '&paged='.$_GET['paged'] : '';
				wp_redirect('admin.php?page=mlang&tab=strings'.$paged); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'options':
				check_admin_referer( 'options-lang', '_wpnonce_options-lang' );

				$options['default_lang'] = $_POST['default_lang'];
				$options['browser'] = isset($_POST['browser']) ? 1 : 0;
				$options['rewrite'] = $_POST['rewrite'];
				$options['hide_default'] = isset($_POST['hide_default']) ? 1 : 0;
				$options['force_lang'] = isset($_POST['force_lang']) ? 1 : 0;
				update_option('polylang', $options);

				// refresh refresh permalink structure and rewrite rules in case rewrite or hide_default options have been modified
				$wp_rewrite->extra_permastructs['language'][0] = $options['rewrite'] ? '%language%' : '/language/%language%';
				$wp_rewrite->flush_rules();

				// fills existing posts & terms with default language
				if (isset($_POST['fill_languages'])) {
					if (isset($_POST['posts'])) {
						foreach(explode(',', $_POST['posts']) as $post_id) {
							$this->set_post_language($post_id, $options['default_lang']);
						}
					}
					if (isset($_POST['terms'])) {
						foreach(explode(',', $_POST['terms']) as $term_id) {
							$this->set_term_language($term_id, $options['default_lang']);
						}
					}
				}
				break;

			default:
				break;
		}

		// prepare the list of tabs
		$tabs = array('lang' => __('Languages','polylang'));

		// only if at least one language has been created
		if ($listlanguages = $this->get_languages_list()) {
			if (current_theme_supports('menus'))
				$tabs['menus'] = __('Menus','polylang'); // don't display the menu tab if the active theme does not support nav menus

			if (function_exists('base64_decode'))
				$tabs['strings'] = __('Strings translation','polylang'); // some hosts disable the function for security reasons

			$tabs['settings'] = __('Settings', 'polylang');
		}

		$active_tab = isset($_GET['tab']) && $_GET['tab'] ? $_GET['tab'] : 'lang';

		switch($active_tab) {
			case 'lang':
				// prepare the list table of languages
				$data = array();
				foreach ($listlanguages as $lang)
					$data[] = array_merge( (array) $lang, array('flag' => $this->get_flag($lang)) ) ;

				$list_table = new Polylang_List_Table();
				$list_table->prepare_items($data);

				$rtl = 0;

				// error messages for data validation
				$errors[1] = __('Enter a valid WorPress locale', 'polylang');
				$errors[2] = __('The language code must be 2 characters long', 'polylang');
				$errors[3] = __('The language code must be unique', 'polylang');
				$errors[4] = __('The language must have a name', 'polylang');
				$errors[5] = __('The language was created, but the WordPress language file was not downloaded. Please install it manually.', 'polylang');
				break;

			case 'menus':
				// default values
				foreach ($locations as $key=>$location) {
					if (isset($menu_lang[$key]))
						$menu_lang[$key] = wp_parse_args($menu_lang[$key], $this->get_switcher_options('menu', 'default'));
				}
				break;

			case 'strings':
				// get the strings to translate
				$data = $this->get_strings();

				// load translations
				$mo = new MO();
				foreach ($listlanguages as $language) {
					$reader = new POMO_StringReader(base64_decode(get_option('polylang_mo'.$language->term_id)));
					$mo->import_from_reader($reader);

					foreach ($data as $key=>$row) {
						$data[$key]['translations'][$language->name] = $mo->translate($data[$key]['string']);
						$data[$key]['row'] = $key; // store the row number for convenience
					}
				}

				$string_table = new Polylang_String_Table();
				$string_table->prepare_items($data);
				break;

			case 'settings':
				//FIXME rework this as it would not be efficient in case of thousands posts or terms !
				// detects posts & pages without language set
				$q = array(
					'numberposts'=>-1,
					'post_type' => 'any',
					'post_status'=>'any',
					'fields' => 'ids',
					'tax_query' => array(array(
						'taxonomy'=> 'language',
						'terms'=> get_terms('language', array('fields'=>'ids')),
						'operator'=>'NOT IN'
					))
				);
				$posts = implode(',', get_posts($q));

				// detects categories & post tags without language set
				$terms = get_terms(get_taxonomies(array('show_ui'=>true)), array('get'=>'all', 'fields'=>'ids'));

		 		foreach ($terms as $key => $term_id) {
					if ($this->get_term_language($term_id))
						unset($terms[$key]);
				}
				$terms = implode(',', $terms);
				break;

			default:
				break;
		}
		// displays the page
		include(PLL_INC.'/languages-form.php');
	}

	// validates data entered when creating or updating a language
	function validate_lang($lang = null) {
		// validate locale
		$loc = $_POST['description'];
		if ( !preg_match('#^[a-z]{2,3}$#', $loc) && !preg_match('#^[a-z]{2,3}_[A-Z]{2,3}$#', $loc) )
			$error = 1;

		// validate slug length
		if (!preg_match('#^[a-z]{2,3}$#', $_POST['slug']))
			$error = 2;

		// validate slug is unique
		if ($this->get_language($_POST['slug']) != null && ( $lang === null || (isset($lang) && $lang->slug != $_POST['slug'])))
			$error = 3;

		// validate name
		if ($_POST['name'] == '')
			$error = 4;

		return isset($error) ? $error : 0;
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
				// with new files introduced in 3.4
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

	// ugrades languages files after a core upgrade
	function upgrade_languages($version) {
		apply_filters('update_feedback', __('Upgrading language files&#8230;', 'polylang'));
		foreach ($this->get_languages_list() as $language) {
			if ($language->description != $_POST['locale']) // do not (re)update the language files of a localized WordPress
				$this->download_mo($language->description, $version);
		}
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

	function &get_strings() {
		global $wp_registered_widgets;

		// WP strings
		$this->register_string(__('Site Title'), get_option('blogname'));
		$this->register_string(__('Tagline'), get_option('blogdescription'));

		// widgets titles
		$sidebars = wp_get_sidebars_widgets();
		foreach ($sidebars as $sidebar => $widgets) {
			if ($sidebar == 'wp_inactive_widgets' || !isset($widgets))
				continue;

			foreach ($widgets as $widget) {
				// nothing can be done if the widget is created using pre WP2.8 API :(
				// there is no object, so we can't access it to get the widget options
				// the second part of the test is probably useless
				if (!isset($wp_registered_widgets[$widget]['callback'][0]) || !is_object($wp_registered_widgets[$widget]['callback'][0]))
					continue;

				$widget_settings = $wp_registered_widgets[$widget]['callback'][0]->get_settings();
				$number = $wp_registered_widgets[$widget]['params'][0]['number'];
				$title = $widget_settings[$number]['title'];
				if (isset($title) && $title)
					$this->register_string(__('Widget title', 'polylang'), $title);
			}
		}
		return $this->strings;
	}

} // class Polylang_Admin

?>

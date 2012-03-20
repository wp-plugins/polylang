<?php

require_once(PLL_INC.'/list-table.php');

// setups the Polylang admin panel
class Polylang_Admin extends Polylang_Admin_Base {

	function __construct() {
		parent::__construct();
	}

	// displays the about metabox
	function about() {
		include(PLL_INC.'/about.php');
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
					$r = wp_insert_term($_POST['name'],'language', array('slug'=>$_POST['slug'], 'description'=>$_POST['description']));
					wp_update_term($r['term_id'], 'language', array('term_group'=>$_POST['term_group'])); // can't set the term group directly in wp_insert_term
					update_metadata('term', $r['term_id'], '_rtl', $_POST['rtl']);

					if (!isset($options['default_lang'])) { // if this is the first language created, set it as default language
						$options['default_lang'] = $_POST['slug'];
						update_option('polylang', $options);
					}

					flush_rewrite_rules(); // refresh rewrite rules

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
					$terms= get_terms($this->taxonomies, array('get'=>'all', 'fields'=>'ids'));
					$this->delete_translations('term', $terms, $lang_slug);

					// FIXME should find something more efficient (with a sql query ?)
					foreach ($terms as $id) {
						if (($lg = $this->get_term_language($id)) && $lg->term_id == $lang_id)
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

						flush_rewrite_rules(); // refresh rewrite rules
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
						$terms = get_terms($this->taxonomies, array('get'=>'all', 'fields'=>'ids'));
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

					flush_rewrite_rules(); // refresh rewrite rules
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

				foreach ($this->get_languages_list() as $language) {
					$mo = $this->mo_import($language);

					foreach ($_POST['translation'][$language->name] as $key=>$translation) {
						$mo->add_entry($mo->make_entry($strings[$key]['string'], stripslashes($translation)));
					}
					// FIXME should I clean the mo object to remove unused strings ?
					$this->mo_export($mo, $language);
				}

				$paged = isset($_GET['paged']) ? '&paged='.$_GET['paged'] : '';
				wp_redirect('admin.php?page=mlang&tab=strings'.$paged); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'options':
				check_admin_referer( 'options-lang', '_wpnonce_options-lang' );

				$options['default_lang'] = $_POST['default_lang'];
				$options['rewrite'] = $_POST['rewrite'];
				foreach (array('browser', 'hide_default', 'force_lang', 'redirect_lang') as $key)
					$options[$key] = isset($_POST[$key]) ? 1 : 0;

				update_option('polylang', $options);

				// refresh rewrite rules in case rewrite or hide_default options have been modified
				// it seems useless to refresh permastruct here
				flush_rewrite_rules();

				// fills existing posts & terms with default language
				if (isset($_POST['fill_languages'])) {
					global $wpdb;
					$untranslated = $this->get_untranslated();
					$lang = $this->get_language($options['default_lang']);

					$values = array();
					foreach ($untranslated['posts'] as $post_id)
						$values[] = $wpdb->prepare("(%d, %d)", $post_id, $lang->term_taxonomy_id);

					if ($values) {
						$wpdb->query("INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES " . implode(',', $values));
						wp_update_term_count($lang->term_taxonomy_id, 'language'); // updating term count is mandatory (thanks to AndyDeGroo)
					}

					$values = array();
					foreach ($untranslated['terms'] as $term_id)
						$values[] = $wpdb->prepare("(%d, %s, %d)", $term_id, '_language', $lang->term_id);

					if ($values)
						$wpdb->query("INSERT INTO $wpdb->termmeta (term_id, meta_key, meta_value) VALUES " . implode(',', $values));
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

			$tabs['strings'] = __('Strings translation','polylang');
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
				foreach ($listlanguages as $language) {
					$mo = $this->mo_import($language);
					foreach ($data as $key=>$row) {
						$data[$key]['translations'][$language->name] = $mo->translate($data[$key]['string']);
						$data[$key]['row'] = $key; // store the row number for convenience
					}
				}

				$string_table = new Polylang_String_Table();
				$string_table->prepare_items($data);
				break;

			case 'settings':
				// detects posts & pages without language set
				$untranslated = $this->get_untranslated();
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

	// returns unstranslated posts and terms ids
	function get_untranslated() {
		$posts = get_posts(array(
			'numberposts'=> -1,
			'post_type' => $this->post_types,
			'post_status'=> 'any',
			'fields' => 'ids',
			'tax_query' => array(array(
				'taxonomy'=> 'language',
				'terms'=> get_terms('language', array('fields'=>'ids')),
				'operator'=> 'NOT IN'
			))
		));

		global $wpdb;
		$terms = get_terms($this->taxonomies, array('get'=>'all', 'fields'=>'ids'));
		$tr_terms = $wpdb->get_col("SELECT t.term_id FROM $wpdb->terms AS t
			LEFT JOIN $wpdb->termmeta AS tm ON t.term_id = tm.term_id
			WHERE tm.meta_key = '_language'");
		$terms = array_diff($terms, $tr_terms);

		return empty($posts) && empty($terms) ? false : array('posts' => $posts, 'terms' => $terms);
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
				if (isset($widget_settings[$number]['title']) && $title = $widget_settings[$number]['title'])
					$this->register_string(__('Widget title', 'polylang'), $title);
			}
		}
		return $this->strings;
	}

} // class Polylang_Admin

?>

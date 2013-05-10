<?php

require_once(PLL_INC.'/list-table.php');

// setups the Polylang admin panel
class Polylang_Admin extends Polylang_Admin_Base {

	function __construct() {
		parent::__construct();

		// adds screen options and the about box in the languages admin panel
		add_action('load-settings_page_mlang',  array(&$this, 'load_page'));

		// saves per-page value in screen option
		add_filter('set-screen-option', create_function('$s, $o, $v', 'return $v;'), 10, 3);
	}

	// adds screen options and the about box in the languages admin panel
	function load_page() {
		// test of $_GET['tab'] avoids displaying the automatically generated screen options on other tabs
		if ((!defined('PLL_DISPLAY_ABOUT') || PLL_DISPLAY_ABOUT) && (!isset($_GET['tab']) || $_GET['tab'] == 'lang')) {
			add_meta_box('pll_about_box', __('About Polylang', 'polylang'), create_function('', "include(PLL_INC.'/about.php');"), 'settings_page_mlang', 'normal');
			add_screen_option('per_page', array('label' => __('Languages', 'polylang'), 'default' => 10, 'option' => 'pll_lang_per_page'));
		}

		if (isset($_GET['tab']) && $_GET['tab'] == 'strings')
			add_screen_option('per_page', array('label' => __('Strings translations', 'polylang'), 'default' => 10, 'option' => 'pll_strings_per_page'));
	}

	// used to update the translation when a language slug has been modified
	function update_translations($type, $ids, $old_slug) {
		foreach ($ids as $id) {
			if ($tr = $this->get_translations($type, $id)) {
				$tr[$_POST['slug']] = $tr[$old_slug];
				unset($tr[$old_slug]);
				update_metadata($type, (int) $id, '_translations', $tr);
			}
		}
	}

	// used to delete the translation when a language is deleted
	function delete_translations($type, $ids, $old_slug) {
		foreach ($ids as $id) {
			if ($tr = $this->get_translations($type, $id)) {
				unset($tr[$old_slug]);
				update_metadata($type, (int) $id, '_translations', $tr);
			}
		}
	}

	// the languages panel
	function languages_page() {
		$action = isset($_REQUEST['pll_action']) ? $_REQUEST['pll_action'] : '';

		switch ($action) {
			case 'add':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );
				$error = $this->validate_lang();

				if ($error == 0) {
					$r = wp_insert_term($_POST['name'], 'language', array('slug' => $_POST['slug'], 'description' => $_POST['description']));
					wp_update_term($r['term_id'], 'language', array('term_group' => $_POST['term_group'])); // can't set the term group directly in wp_insert_term
					update_metadata('term', $r['term_id'], '_rtl', $_POST['rtl']);

					if (!isset($this->options['default_lang'])) { // if this is the first language created, set it as default language
						$this->options['default_lang'] = $_POST['slug'];
						update_option('polylang', $this->options);
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

				if (!empty($_GET['lang'])) {
					$lang_id = (int) $_GET['lang'];
					$lang = $this->get_language($lang_id);
					$lang_slug = $lang->slug;

					// update the language slug in posts meta
					$posts = get_posts(array(
						'numberposts' => -1,
						'nopaging'    => true,
						'fields'      => 'ids',
						'meta_key'    => '_translations',
						'post_type'   => 'any',
						'post_status' => 'any'
					));
					$this->delete_translations('post', $posts, $lang_slug);

					// update the language slug in categories & post tags meta
					$terms = get_terms($this->taxonomies, array('get'=>'all', 'fields'=>'ids'));
					$this->delete_translations('term', $terms, $lang_slug);

					// FIXME should find something more efficient (with a sql query ?)
					foreach ($terms as $id) {
						if (($lg = $this->get_term_language($id)) && $lg->term_id == $lang_id)
							$this->delete_term_language($id); // delete language of this term
					}

					// delete language option in widgets
					if (!empty($this->options['widgets'])) {
						foreach ($this->options['widgets'] as $key=>$slug) {
							if ($slug == $lang_slug)
								unset($this->options['widgets'][$key]);
						}
					}

					// delete users options
					foreach (get_users(array('fields' => 'ID')) as $user_id) {
						delete_user_meta($user_id, 'user_lang', $lang->description);
						delete_user_meta($user_id, 'pll_filter_content', $lang_slug);
						delete_user_meta($user_id, 'description_'.$lang_slug);
					}

					// delete the string translations
					delete_option('polylang_mo'.$lang_id);

					// delete the language itself
					delete_metadata('term', $lang_id, '_rtl');
					wp_delete_term($lang_id, 'language');

					// oops ! we deleted the default language...
					if ($this->options['default_lang'] == $lang_slug)	{
						if ($listlanguages = $this->get_languages_list())
							$this->options['default_lang'] = $listlanguages[0]->slug; // arbitrary choice...
						else
							unset($this->options['default_lang']);
					}

					update_option('polylang', $this->options);
					flush_rewrite_rules(); // refresh rewrite rules
				}
				wp_redirect('admin.php?page=mlang'); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'edit':
				if (!empty($_GET['lang'])) {
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
						$posts = get_posts(array(
							'numberposts' => -1,
							'nopaging'    => true,
							'fields'      => 'ids',
							'meta_key'    => '_translations',
							'post_type'   => 'any',
							'post_status' => 'any'
						));
						$this->update_translations('post', $posts, $old_slug);

						// update the language slug in categories & post tags meta
						$terms = get_terms($this->taxonomies, array('get'=>'all', 'fields'=>'ids'));
						$this->update_translations('term', $terms, $old_slug);

						// update language option in widgets
						foreach ($this->options['widgets'] as $key=>$lang) {
							if ($lang == $old_slug)
								$this->options['widgets'][$key] = $_POST['slug'];
						}

						// update the default language option if necessary
						if ($this->options['default_lang'] == $old_slug)
							$this->options['default_lang'] = $_POST['slug'];
					}

					update_option('polylang', $this->options);

					// and finally update the language itself
					$args = array(
						'name'        => $_POST['name'],
						'slug'        => $_POST['slug'],
						'description' => $_POST['description'],
						'term_group'  => $_POST['term_group']
					);
					wp_update_term($lang_id, 'language', $args);
					update_metadata('term', $lang_id, '_rtl', $_POST['rtl']);

					flush_rewrite_rules(); // refresh rewrite rules
				}

				wp_redirect('admin.php?page=mlang'. ($error ? '&error='.$error : '') ); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'string-translation':
				if (!empty($_REQUEST['submit'])) {
					check_admin_referer( 'string-translation', '_wpnonce_string-translation' );
					$strings = $this->get_strings();

					foreach ($this->get_languages_list() as $language) {
						if(empty($_POST['translation'][$language->name])) // in case the language filter is active (thanks to John P. Bloch)
							continue;

						$mo = $this->mo_import($language);

						foreach ($_POST['translation'][$language->name] as $key=>$translation) {
							$mo->add_entry($mo->make_entry($strings[$key]['string'], stripslashes($translation)));
						}
						$mo->add_entry($mo->make_entry('', '')); // empty string translation, just in case

						// clean database
						if (!empty($_POST['clean'])) {
							$new_mo = new MO();
							foreach ($strings as $string)
								$new_mo->add_entry($mo->make_entry($string['string'], $mo->translate($string['string'])));
						}
						$this->mo_export(isset($new_mo) ? $new_mo : $mo, $language);
					}
				}

				if (WP_List_Table::current_action() == 'delete' && !empty($_REQUEST['strings']) && function_exists('icl_unregister_string')) {
					check_admin_referer( 'string-translation', '_wpnonce_string-translation' );
					$strings = $this->get_strings();

					foreach ($_REQUEST['strings'] as $key)
						icl_unregister_string($strings[$key]['context'], $strings[$key]['name']);
				}

				// to refresh the page (possible thanks to the $_GET['noheader']=true)
				$url = 'admin.php?page=mlang&tab=strings';
				foreach(array('s', 'paged', 'group') as $qv)
					$url = empty($_REQUEST[$qv]) ? $url : $url . '&' . $qv . '=' . $_REQUEST[$qv];
				wp_redirect($url);
				exit;
				break;

			case 'options':
				check_admin_referer( 'options-lang', '_wpnonce_options-lang' );

				foreach(array('default_lang', 'force_lang', 'rewrite') as $key)
					if (isset($_POST[$key]))
						$this->options[$key] = $_POST[$key];

				foreach (array('browser', 'hide_default', 'redirect_lang', 'media_support') as $key)
					$this->options[$key] = isset($_POST[$key]) ? 1 : 0;

				foreach (array('sync', 'post_types', 'taxonomies') as $key)
					$this->options[$key] = empty($_POST[$key]) ? array() : array_keys($_POST[$key], 1);

				update_option('polylang', $this->options);

				// refresh rewrite rules in case rewrite,  hide_default, post types or taxonomies options have been modified
				// it seems useless to refresh permastruct here
				flush_rewrite_rules();

				// fills existing posts & terms with default language
				if (isset($_POST['fill_languages']) && $nolang = $this->get_objects_with_no_lang()) {
					global $wpdb;
					$lang = $this->get_language($this->options['default_lang']);

					$values = array();
					foreach ($nolang['posts'] as $post_id)
						$values[] = $wpdb->prepare("(%d, %d)", $post_id, $lang->term_taxonomy_id);

					if ($values) {
						$wpdb->query("INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES " . implode(',', $values));
						wp_update_term_count($lang->term_taxonomy_id, 'language'); // updating term count is mandatory (thanks to AndyDeGroo)
					}

					$values = array();
					foreach ($nolang['terms'] as $term_id)
						$values[] = $wpdb->prepare("(%d, %s, %d)", $term_id, '_language', $lang->term_id);

					if ($values)
						$wpdb->query("INSERT INTO $wpdb->termmeta (term_id, meta_key, meta_value) VALUES " . implode(',', $values));

				}
				wp_redirect('admin.php?page=mlang&tab=settings&updated=true'); // updated=true interpreted by WP
				exit;
				break;

			default:
				break;
		}

		// prepare the list of tabs
		$tabs = array('lang' => __('Languages','polylang'));

		// only if at least one language has been created
		if ($listlanguages = $this->get_languages_list()) {
			$tabs['strings'] = __('Strings translation','polylang');
			$tabs['settings'] = __('Settings', 'polylang');
		}

		$active_tab = !empty($_GET['tab']) ? $_GET['tab'] : 'lang';

		switch($active_tab) {
			case 'lang':
				// prepare the list table of languages
				$data = array();
				foreach ($listlanguages as $lang)
					$data[] = array_merge( (array) $lang, array('flag' => $this->get_flag($lang)) ) ;

				$list_table = new Polylang_Languages_Table();
				$list_table->prepare_items($data);

				if (!$action)
					$rtl = 0;

				// error messages for data validation
				// FIXME no validation for WordPress locale
				//$errors[1] = __('Enter a valid WorPress locale', 'polylang');
				$errors[2] = __('The language code must be 2 characters long', 'polylang');
				$errors[3] = __('The language code must be unique', 'polylang');
				$errors[4] = __('The language must have a name', 'polylang');
				$errors[5] = __('The language was created, but the WordPress language file was not downloaded. Please install it manually.', 'polylang');
				break;

			case 'strings':
				// get the strings to translate
				$data = $this->get_strings();

				$selected = empty($_REQUEST['group']) ? -1 : $_REQUEST['group'];
				foreach ($data as $key=>$row) {
					$groups[] = $row['context']; // get the groups

					// filter for search string
					if (($selected !=-1 && $row['context'] != $selected) || (!empty($_REQUEST['s']) && stripos($row['name'], $_REQUEST['s']) === false && stripos($row['string'], $_REQUEST['s']) === false))
						unset ($data[$key]);
				}

				$groups = array_unique($groups);

				// load translations
				foreach ($listlanguages as $language) {
					// filters by language if requested
					if (($lg = get_user_meta(get_current_user_id(), 'pll_filter_content', true)) && $language->slug != $lg)
						continue;

					$mo = $this->mo_import($language);
					foreach ($data as $key=>$row) {
						$data[$key]['translations'][$language->name] = $mo->translate($row['string']);
						$data[$key]['row'] = $key; // store the row number for convenience
					}
				}

				$string_table = new Polylang_String_Table($groups, $selected);
				$string_table->prepare_items($data);
				break;

			case 'settings':
				$post_types = array_unique(apply_filters('pll_get_post_types', get_post_types(array('_builtin' => false)), true));
				$taxonomies = array_unique(apply_filters('pll_get_taxonomies', array_diff(get_taxonomies(array('_builtin' => false)), array('language')), true));
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
		// FIXME no validation for WordPress locale as it breaks de_DE_Sie
		/*
		$loc = $_POST['description'];
		if ( !preg_match('#^[a-z]{2,3}$#', $loc) && !preg_match('#^[a-z]{2,3}_[A-Z]{2,3}$#', $loc) )
			$error = 1;
		*/

		// validate slug length
		if (!preg_match('#^[a-z]{2,3}$#', $_POST['slug']))
			$error = 2;

		// validate slug is unique
		if ($this->get_language($_POST['slug']) && ( $lang === null || (isset($lang) && $lang->slug != $_POST['slug'])))
			$error = 3;

		// validate name
		if ($_POST['name'] == '')
			$error = 4;

		return isset($error) ? $error : 0;
	}

	// returns unstranslated posts and terms ids
	function get_objects_with_no_lang() {
		$posts = get_posts(array(
			'numberposts' => -1,
			'nopaging'    => true,
			'post_type'   => $this->post_types,
			'post_status' => 'any',
			'fields'      => 'ids',
			'tax_query'   => array(array(
				'taxonomy' => 'language',
				'terms'    => get_terms('language', array('fields'=>'ids')),
				'operator' => 'NOT IN'
			))
		));

		global $wpdb;
		$terms = get_terms($this->taxonomies, array('get'=>'all', 'fields'=>'ids'));
		$tr_terms = $wpdb->get_col("SELECT term_id FROM $wpdb->termmeta WHERE meta_key = '_language'");
		$terms = array_unique(array_diff($terms, $tr_terms)); // array_unique to avoid duplicates if a term is in more than one taxonomy

		return apply_filters('pll_get_objects_with_no_lang', empty($posts) && empty($terms) ? false : array('posts' => $posts, 'terms' => $terms));
	}

	function &get_strings() {
		// WP strings
		$this->register_string(__('Site Title'), get_option('blogname'), 'WordPress');
		$this->register_string(__('Tagline'), get_option('blogdescription'), 'WordPress');
		$this->register_string(__('Date Format'), get_option('date_format'), 'WordPress');
		$this->register_string(__('Time Format'), get_option('time_format'), 'WordPress');

		// widgets titles
		global $wp_registered_widgets;
		$sidebars = wp_get_sidebars_widgets();
		foreach ($sidebars as $sidebar => $widgets) {
			if ($sidebar == 'wp_inactive_widgets' || !isset($widgets))
				continue;

			foreach ($widgets as $widget) {
				// nothing can be done if the widget is created using pre WP2.8 API :(
				// there is no object, so we can't access it to get the widget options
				if (!isset($wp_registered_widgets[$widget]['callback'][0]) || !is_object($wp_registered_widgets[$widget]['callback'][0]) || !method_exists($wp_registered_widgets[$widget]['callback'][0], 'get_settings'))
					continue;

				$widget_settings = $wp_registered_widgets[$widget]['callback'][0]->get_settings();
				$number = $wp_registered_widgets[$widget]['params'][0]['number'];
				// don't enable widget title translation if the widget is visible in only one language or if there is no title
				if (empty($this->options['widgets'][$widget]) && isset($widget_settings[$number]['title']) && $title = $widget_settings[$number]['title'])
					$this->register_string(__('Widget title', 'polylang'), $title, 'Widget');
			}
		}

		// allow plugins to modify our list of strings, mainly for use by our Polylang_WPML_Compat class
		$this->strings = apply_filters('pll_get_strings', $this->strings);
		return $this->strings;
	}

} // class Polylang_Admin

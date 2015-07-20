<?php

/*
 * a class for the Polylang settings pages
 *
 * @since 1.2
 */
class PLL_Settings {
	public $links_model, $model, $options;
	protected $active_tab;

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $polylang
	 */
	public function __construct(&$polylang) {
		$this->links_model = &$polylang->links_model;
		$this->model = &$polylang->model;
		$this->options = &$polylang->options;

		$this->active_tab = !empty($_GET['tab']) ? $_GET['tab'] : 'lang';

		PLL_Admin_Strings::init();

		// adds screen options and the about box in the languages admin panel
		add_action('load-settings_page_mlang',  array(&$this, 'load_page'));

		// saves per-page value in screen option
		add_filter('set-screen-option', create_function('$s, $o, $v', 'return $v;'), 10, 3);
	}

	/*
	 * adds screen options and the about box in the languages admin panel
	 *
	 * @since 0.9.5
	 */
	public function load_page() {
		// test of $this->active_tab avoids displaying the automatically generated screen options on other tabs
		switch ($this->active_tab) {
			case 'lang':
				ob_start();
				include(PLL_ADMIN_INC.'/view-recommended.php');
				$content = trim(ob_get_contents());
				ob_end_clean();

				if (strlen($content) > 0) {
					add_meta_box(
						'pll_recommended',
						__('Recommended plugins', 'polylang'),
						create_function('', "echo '$content';"),
						'settings_page_mlang',
						'normal'
					);
				}

				if (!defined('PLL_DISPLAY_ABOUT') || PLL_DISPLAY_ABOUT) {
					add_meta_box(
						'pll_about_box',
						__('About Polylang', 'polylang'),
						create_function('', "include(PLL_ADMIN_INC.'/view-about.php');"),
						'settings_page_mlang',
						'normal'
					);
				}

				add_screen_option('per_page', array(
					'label'   => __('Languages', 'polylang'),
					'default' => 10,
					'option'  => 'pll_lang_per_page'
				));
				break;

			case 'strings':
				add_screen_option('per_page', array(
					'label'   => __('Strings translations', 'polylang'),
					'default' => 10,
					'option'  => 'pll_strings_per_page'
				));
				break;

			default:
				break;
		}
	}

	/*
	 * diplays the 3 tabs pages: languages, strings translations, settings
	 * also manages user input for these pages
	 *
	 * @since 0.1
	 */
	public function languages_page() {
		// prepare the list of tabs
		$tabs = array('lang' => __('Languages','polylang'));

		// only if at least one language has been created
		if ($listlanguages = $this->model->get_languages_list()) {
			$tabs['strings'] = __('Strings translation','polylang');
			$tabs['settings'] = __('Settings', 'polylang');
		}

		$tabs = apply_filters('pll_settings_tabs', $tabs);

		switch($this->active_tab) {
			case 'lang':
				// prepare the list table of languages
				$list_table = new PLL_Table_Languages();
				$list_table->prepare_items($listlanguages);
				break;

			case 'strings':
				// get the strings to translate
				$data = PLL_Admin_Strings::get_strings();

				// get the groups
				foreach ($data as $key => $row)
					$groups[] = $row['context']; 

				$groups = array_unique($groups);				
				$selected = empty($_REQUEST['group']) || !in_array($_REQUEST['group'], $groups) ? -1 : $_REQUEST['group'];
				$s = empty($_REQUEST['s']) ? '' : wp_unslash($_REQUEST['s']);
				
				// filter for search string
				foreach ($data as $key => $row) {
					if (($selected !=-1 && $row['context'] != $selected) || (!empty($s) && stripos($row['name'], $s) === false && stripos($row['string'], $s) === false))
						unset ($data[$key]);
				}

				// load translations
				foreach ($listlanguages as $language) {
					// filters by language if requested
					if (($lg = get_user_meta(get_current_user_id(), 'pll_filter_content', true)) && $language->slug != $lg)
						continue;

					$mo = new PLL_MO();
					$mo->import_from_db($language);
					foreach ($data as $key=>$row) {
						$data[$key]['translations'][$language->slug] = $mo->translate($row['string']);
						$data[$key]['row'] = $key; // store the row number for convenience
					}
				}

				// get an array with language slugs as keys, names as values
				$languages = array_combine(wp_list_pluck($listlanguages, 'slug'), wp_list_pluck($listlanguages, 'name'));
				
				$string_table = new PLL_Table_String(compact('languages', 'groups', 'selected'));
				$string_table->prepare_items($data);
				break;

			case 'settings':
				$post_types = get_post_types(array('public' => true, '_builtin' => false));
				$post_types = array_diff($post_types, get_post_types(array('_pll' => true)));
				$post_types = array_unique(apply_filters('pll_get_post_types', $post_types, true));

				$taxonomies = get_taxonomies(array('public' => true, '_builtin' => false));
				$taxonomies = array_diff($taxonomies, get_taxonomies(array('_pll' => true)));
				$taxonomies = array_unique(apply_filters('pll_get_taxonomies', $taxonomies , true));
				break;

			default:
				break;
		}

		$action = isset($_REQUEST['pll_action']) ? $_REQUEST['pll_action'] : '';

		switch ($action) {
			case 'add':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );

				if ($this->model->add_language($_POST)) {
					// backward compatibility WP < 4.0
					if (version_compare($GLOBALS['wp_version'], '4.0', '<')) {
						PLL_Admin::download_mo($_POST['locale']);
					}

					elseif ('en_US' != $_POST['locale']) {
						// attempts to install the language pack
						require_once(ABSPATH . 'wp-admin/includes/translation-install.php');
						if (!wp_download_language_pack($_POST['locale']))
							add_settings_error('general', 'pll_download_mo', __('The language was created, but the WordPress language file was not downloaded. Please install it manually.', 'polylang'));

						// force checking for themes and plugins translations updates
						wp_update_themes();
						wp_update_plugins();
					}
				}
				$this->redirect(); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				break;

			case 'delete':
				check_admin_referer('delete-lang');

				if (!empty($_GET['lang']))
					$this->model->delete_language((int) $_GET['lang']);

				$this->redirect(); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				break;

			case 'edit':
				if (!empty($_GET['lang']))
					$edit_lang = $this->model->get_language((int) $_GET['lang']);
				break;

			case 'update':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );

				$error = $this->model->update_language($_POST);

				$this->redirect(); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				break;

			case 'string-translation':
				if (!empty($_REQUEST['submit'])) {
					check_admin_referer( 'string-translation', '_wpnonce_string-translation' );
					$strings = PLL_Admin_Strings::get_strings();

					foreach ($this->model->get_languages_list() as $language) {
						if (empty($_POST['translation'][$language->slug])) // in case the language filter is active (thanks to John P. Bloch)
							continue;

						$mo = new PLL_MO();
						$mo->import_from_db($language);

						foreach ($_POST['translation'][$language->slug] as $key => $translation) {
							$translation = apply_filters('pll_sanitize_string_translation', $translation, $strings[$key]['name'], $strings[$key]['context']);
							$mo->add_entry($mo->make_entry($strings[$key]['string'], $translation));
						}

						// clean database (removes all strings which were registered some day but are no more)
						if (!empty($_POST['clean'])) {
							$new_mo = new PLL_MO();

							foreach ($strings as $string)
								$new_mo->add_entry($mo->make_entry($string['string'], $mo->translate($string['string'])));
						}

						isset($new_mo) ? $new_mo->export_to_db($language) : $mo->export_to_db($language);
					}
					add_settings_error('general', 'pll_strings_translations_updated', __('Translations updated.', 'polylang'), 'updated');
				}

				do_action('pll_save_strings_translations');

				// unregisters strings registered through WPML API
				if ($string_table->current_action() == 'delete' && !empty($_REQUEST['strings']) && function_exists('icl_unregister_string')) {
					check_admin_referer( 'string-translation', '_wpnonce_string-translation' );
					$strings = PLL_Admin_Strings::get_strings();

					foreach ($_REQUEST['strings'] as $key)
						icl_unregister_string($strings[$key]['context'], $strings[$key]['name']);
				}

				// to refresh the page (possible thanks to the $_GET['noheader']=true)
				$this->redirect(array_intersect_key($_REQUEST, array_flip(array('s', 'paged', 'group'))));
				break;

			case 'options':
				check_admin_referer( 'options-lang', '_wpnonce_options-lang' );

				$this->options['default_lang'] = sanitize_title($_POST['default_lang']); // we have slug as value

				foreach(array('force_lang', 'rewrite') as $key)
					$this->options[$key] = isset($_POST[$key]) ? (int) $_POST[$key] : 0;

				if (3 == $this->options['force_lang'] && isset($_POST['domains']) && is_array($_POST['domains'])) {
					foreach ($_POST['domains'] as $key => $domain) {
						$this->options['domains'][$key] = esc_url_raw(trim($domain));
					}
				}

				foreach (array('browser', 'hide_default', 'redirect_lang', 'media_support') as $key)
					$this->options[$key] = isset($_POST[$key]) ? 1 : 0;

				if (3 == $this->options['force_lang'])
					$this->options['browser'] = $this->options['hide_default'] = 0;

				foreach (array('sync', 'post_types', 'taxonomies') as $key)
					$this->options[$key] = empty($_POST[$key]) ? array() : array_keys($_POST[$key], 1);

				update_option('polylang', $this->options);

				// refresh rewrite rules in case rewrite,  hide_default, post types or taxonomies options have been modified
				// it seems useless to refresh permastruct here
				flush_rewrite_rules();

				// refresh language cache in case home urls have been modified
				$this->model->clean_languages_cache();

				// fills existing posts & terms with default language
				if (isset($_POST['fill_languages']) && $nolang = $this->model->get_objects_with_no_lang()) {
					if (!empty($nolang['posts']))
						$this->model->set_language_in_mass('post', $nolang['posts'], $this->options['default_lang']);
					if (!empty($nolang['terms']))
						$this->model->set_language_in_mass('term', $nolang['terms'], $this->options['default_lang']);
				}

				add_settings_error('general', 'settings_updated', __('Settings saved.'), 'updated');
				$this->redirect();
				break;

			default:
				break;
		}

		// displays the page
		include(PLL_ADMIN_INC.'/view-languages.php');
	}

	/*
	 * list the post metas to synchronize
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	static public function list_metas_to_sync() {
		return array(
			'taxonomies'        => __('Taxonomies', 'polylang'),
			'post_meta'         => __('Custom fields', 'polylang'),
			'comment_status'    => __('Comment status', 'polylang'),
			'ping_status'       => __('Ping status', 'polylang'),
			'sticky_posts'      => __('Sticky posts', 'polylang'),
			'post_date'         => __('Published date', 'polylang'),
			'post_format'       => __('Post format', 'polylang'),
			'post_parent'       => __('Page parent', 'polylang'),
			'_wp_page_template' => __('Page template', 'polylang'),
			'menu_order'        => __('Page order', 'polylang'),
			'_thumbnail_id'     => __('Featured image', 'polylang'),
		);
	}

	/*
	 * redirects to language page (current active tab)
	 * saves error messages in a transient for reuse in redirected page
	 *
	 * @since 1.5
	 *
	 * @param array $args query arguments to add to the url
	 */
	protected function redirect($args = array()) {
		if ($errors = get_settings_errors()) {
			set_transient('settings_errors', $errors, 30);
			$args['settings-updated'] = 1;
		}
		// remove possible 'pll_action' and 'lang' query args from the referer before redirecting
		wp_redirect(add_query_arg($args,  remove_query_arg( array('pll_action', 'lang'), wp_get_referer() )));
		exit;
	}
}

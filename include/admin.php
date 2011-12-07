<?php

require_once(PLL_INC.'/list-table.php');
require_once(PLL_INC.'/admin-filters.php');

// setups the Polylang admin panel and calls for other admin related classes
class Polylang_Admin extends Polylang_Base {
	function __construct() {
		new Polylang_Admin_Filters();

		// adds a 'settings' link in the plugins table
		$plugin_file = basename(POLYLANG_DIR).'/polylang.php';
		add_filter('plugin_action_links_'.$plugin_file, array(&$this, 'plugin_action_links'));

		// adds the link to the languages panel in the wordpress admin menu
		add_action('admin_menu', array(&$this, 'add_menus'));
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
			if($tr) {
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
			if($tr) {
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

		$listlanguages = $this->get_languages_list();
		$list_table = new Polylang_List_Table();

		// for nav menus form
		$locations = get_registered_nav_menus();
		$menus = wp_get_nav_menus();
		$menu_lang = get_option('polylang_nav_menus');

		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

		switch ($action) {
			case 'add':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );
				$error = $this->validate_lang();

				if ($error == 0) {
					wp_insert_term($_POST['name'],'language',array('slug'=>$_POST['slug'], 'description'=>$_POST['description']));
					$wp_rewrite->flush_rules(); // refresh rewrite rules

					if (!isset($options['default_lang'])) { // if this is the first language created, set it as default language
						$options['default_lang'] = $_POST['slug'];
						update_option('polylang', $options);
					}
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
					
					foreach ($terms as $id)
						$this->delete_term_language($id); // delete language of this term

					// delete menus locations
					foreach ($locations as $location => $description) {
						unset($menu_lang[$location][$lang_slug]);
					}
					update_option('polylang_nav_menus', $menu_lang);

					// delete the language itself
					wp_delete_term($lang_id, 'language');
					$wp_rewrite->flush_rules(); // refresh rewrite rules

					// oops ! we deleted the default language...
					if ($options['default_lang'] == $lang_slug)	{
						if (!empty($listlanguages))
							$options['default_lang'] = reset($this->get_languages_list())->slug; // arbitrary choice...
						else
							unset($options['default_lang']);
						update_option('polylang', $options);
					}
				}
				wp_redirect('admin.php?page=mlang'); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'edit':
				if (isset($_GET['lang']) && $_GET['lang'])
					$edit_lang = $this->get_language((int) $_GET['lang']);
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

						// update the default language option if necessary
						if ($options['default_lang'] == $old_slug) {
							$options['default_lang'] = $_POST['slug'];
							update_option('polylang', $options);
						}						
					}

					// and finally update the language itself
					wp_update_term($lang_id, 'language', array('name'=>$_POST['name'], 'slug'=>$_POST['slug'], 'description'=>$_POST['description']));
					$wp_rewrite->flush_rules(); // refresh rewrite rules
				}

				wp_redirect('admin.php?page=mlang'. ($error ? '&error='.$error : '') ); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'nav-menus':
				check_admin_referer( 'nav-menus-lang', '_wpnonce_nav-menus-lang' );

				$menu_lang = $_POST['menu-lang'];
				foreach ($locations as $location => $description) 
					foreach (array('switcher', 'show_names', 'show_flags', 'force_home') as $key)
						$menu_lang[$location][$key] = isset($menu_lang[$location][$key]) ? 1 : 0;

				update_option('polylang_nav_menus', $menu_lang);
				break;

			case 'options':
				check_admin_referer( 'options-lang', '_wpnonce_options-lang' );

				$options['default_lang'] = $_POST['default_lang'];
				$options['browser'] = isset($_POST['browser']) ? 1 : 0;
				$options['rewrite'] = $_POST['rewrite'];
				$options['hide_default'] = isset($_POST['hide_default']) ? 1 : 0;
				update_option('polylang', $options);

				// refresh refresh permalink structure and rewrite rules in case rewrite or hide_default options have been modified
				$wp_rewrite->extra_permastructs['language'][0] = $options['rewrite'] ? '%language%' : '/language/%language%';
				$wp_rewrite->flush_rules();

				// fills existing posts & terms with default language
				if (isset($_POST['fill_languages'])) {
					if(isset($_POST['posts'])) {
						foreach(explode(',', $_POST['posts']) as $post_id) {
							wp_set_post_terms($post_id, $options['default_lang'], 'language' );
						}
					}
					if(isset($_POST['terms'])) {
						foreach(explode(',', $_POST['terms']) as $term_id) {
							$this->update_term_language($term_id, $this->get_language($options['default_lang']));
						}
					}
				}
				break;

			default:
				break;
		}

		// prepare the list of tabs
		$tabs = array(
			'lang' => __('Languages','polylang'),
			'menus' => __('Menus','polylang'),
			'settings' => __('Settings', 'polylang')
		);
		if (!current_theme_supports( 'menus' ))
			unset($tabs['menus']); // don't display the menu tab if the active them does not support nav menus

		$active_tab = isset($_GET['tab']) && $_GET['tab'] ? $_GET['tab'] : 'lang';

		switch($active_tab) {
			case 'lang':
				// prepare the list table of languages
				$data = array();
				foreach ($listlanguages as $lang)
					$data[] = array_merge( (array) $lang, array('flag' => $this->get_flag($lang)) ) ;

				$list_table->prepare_items($data);

				$errors[1] = __('Enter a valid WorPress locale', 'polylang');
				$errors[2] = __('The language code must be 2 characters long', 'polylang');
				$errors[3] = __('The language code must be unique', 'polylang');
				$errors[4] = __('The language must have a name', 'polylang');
				break;

			case 'menus':
				// prepare the list of options for the language switcher
				// FIXME do not include the dropdown yet as I need to create a better script (only available for the widget now)
				$menu_options = array(
					'switcher' => __('Displays a language switcher at the end of the menu', 'polylang'),
					'show_names' => __('Displays language names', 'polylang'),
					'show_flags' => __('Displays flags', 'polylang'),
					'force_home' => __('Forces link to front page', 'polylang')
				);

				// default values
				foreach ($locations as $key=>$location)				
					$menu_lang[$key] = wp_parse_args($menu_lang[$key], array('switcher'=> 0, 'show_names'=>1, 'show_flags'=>0, 'force_home'=>0));

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
		if ( !preg_match('#^[a-z]{2}$#', $loc) && !preg_match('#^[a-z]{2}_[A-Z]{2}$#', $loc) )
			$error = 1;

		// validate slug length
		if (strlen($_POST['slug']) != 2)
			$error = 2;

		// validate slug is unique
		if ($this->get_language($_POST['slug']) != null && isset($lang) && $lang->slug != $_POST['slug'])
			$error = 3;

		// validate name
		if ($_POST['name'] == '')
			$error = 4;
		
		return isset($error) ? $error : 0;			
	}

} // class Polylang_Admin

?>

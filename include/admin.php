<?php

require_once(INC_DIR.'/list-table.php');

class Polylang_Admin extends Polylang_Base {
	function __construct() {
		add_action('admin_init',  array(&$this, 'admin_init'));

		// adds a 'settings' link in the plugins table
		$plugin_file = basename(POLYLANG_DIR).'/polylang.php';
		add_filter('plugin_action_links_'.$plugin_file, array(&$this, 'plugin_action_links'));

		// adds the link to the languages panel in the wordpress admin menu
		add_action('admin_menu', array(&$this, 'add_menus'));

		// setup js scripts andd css styles
		add_action('admin_print_scripts', array($this,'admin_js'));
		add_action('admin_print_styles', array($this,'admin_css'));

		// add the language column (as well as a filter by language) in 'All Posts' an 'All Pages' panels
		add_filter('manage_posts_columns',  array(&$this, 'add_post_column'), 10, 2);
		add_filter('manage_pages_columns',  array(&$this, 'add_post_column'));
    add_action('manage_posts_custom_column', array(&$this, 'post_column'), 10, 2);
    add_action('manage_pages_custom_column', array(&$this, 'post_column'), 10, 2);
		add_filter('parse_query',array(&$this,'parse_query'));
		add_action('restrict_manage_posts', array(&$this, 'restrict_manage_posts'));

		// adds the Languages box in the 'Edit Post' and 'Edit Page' panels
		add_action('add_meta_boxes', array(&$this, 'add_meta_boxes'));

		// ajax response for post metabox
		add_action('wp_ajax_post_lang_choice', array($this,'post_lang_choice'));

		// adds actions related to languages when saving or deleting posts and pages
		add_action('save_post', array(&$this, 'save_post'));
		add_action('before_delete_post', array(&$this, 'delete_post'));

		// ajax response for edit term form
		add_action('wp_ajax_term_lang_choice', array($this,'term_lang_choice'));

		// modifies the theme location nav menu metabox
		add_filter('admin_head-nav-menus.php', array(&$this, 'nav_menu_theme_locations'));

		// widgets languages filter
		add_action('in_widget_form', array(&$this, 'in_widget_form'));
		add_filter('widget_update_callback', array(&$this, 'widget_update_callback'), 10, 4);

		// language management for users
		add_filter('locale', array(&$this, 'get_locale'));
		add_action('personal_options_update', array(&$this, 'personal_options_update'));
		add_action('personal_options', array(&$this, 'personal_options'));

		// refresh rewrite rules if the 'page_on_front' option is modified
		add_action('update_option', array(&$this, 'update_option'));
	}

	function admin_init() {
		// add these actions and filters here and not in the constructor to be sure all taxonomies are registered
		foreach (get_taxonomies(array('show_ui'=>true)) as $tax) {
			// adds the language field in the 'Categories' and 'Post Tags' panels
			add_action($tax.'_add_form_fields',  array(&$this, 'add_term_form'));

			// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels
			add_action($tax.'_edit_form_fields',  array(&$this, 'edit_term_form'));

			// adds the language column in the 'Categories' and 'Post Tags' tables
			add_filter('manage_edit-'.$tax.'_columns', array(&$this, 'add_term_column'));
		  add_action('manage_'.$tax.'_custom_column', array(&$this, 'term_column'), 10, 3);

			// adds actions related to languages when saving or deleting categories and post tags
			add_action('created_'.$tax, array(&$this, 'save_term'));
			add_action('edited_'.$tax, array(&$this, 'save_term'));
			add_action('delete_'.$tax, array(&$this, 'delete_term'));
		}

		// manage versions
		$options = get_option('polylang');
		if ($options['version'] < POLYLANG_VERSION) {

			if($options['version'] < '0.4')
				$options['hide_default'] = 0; // option introduced in 0.4

			$options['version'] = POLYLANG_VERSION;
			update_option('polylang', $options);
		}
	}

	// adds a 'settings' link in the plugins table
	function plugin_action_links($links) {
		$settings_link = '<a href="admin.php?page=mlang">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	// adds the link to the languages panel in the wordpress admin menu
	function add_menus() {
		add_submenu_page('options-general.php', __('Languages', 'polylang'), __('Languages', 'polylang'), 'manage_options', 'mlang',  array($this, 'languages_page'));
	}

	// setup js scripts
	function admin_js() {
		wp_enqueue_script('polylang_admin', WP_PLUGIN_URL .'/polylang/js/admin.js');
	}

	// setup css styles
	function admin_css() {
		wp_enqueue_style('polylang_admin', WP_PLUGIN_URL .'/polylang/css/admin.css');
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
				check_admin_referer( 'delete-lang');

				if (isset($_GET['lang']) && $_GET['lang']) {
					$lang_id = (int) $_GET['lang'];
					$lang = $this->get_language($lang_id);
					$lang_slug = $lang->slug;

					// delete all translations in posts in this language
					$args = array('numberposts'=>-1, 'taxonomy' => 'language', 'term' => $lang_slug, 'post_type'=>'any', 'post_status'=>'any');
					$posts = get_posts($args);
					foreach ($posts as $post) {
						foreach ($listlanguages as $language) {
							delete_post_meta($post->ID, '_lang-'.$language->slug);
						}
					}

					// delete references to this language in all posts
					$args = array('numberposts'=>-1, 'meta_key'=>'_lang-'.$lang_slug, 'post_type'=>'any', 'post_status'=>'any');
					$posts = get_posts($args);
					foreach ($posts as $post) {
						delete_post_meta($post->ID, '_lang-'.$lang_slug);
					}

					// delete references to this language in categories & post tags
					$terms = get_terms(get_taxonomies(array('show_ui'=>true)), 'get=all');
 					foreach ($terms as $term) {
						if ($this->get_term_language($term->term_id) == $lang) {
							foreach ($listlanguages as $language) {
								delete_metadata('term', $term->term_id, '_lang-'.$language->slug); // deletes translations of this term
							}
							delete_metadata('term', $term->term_id, '_language', $lang_id); // delete language of this term
						}
						delete_metadata('term', $term->term_id, '_lang-'.$lang_slug); // deletes references to this term in translated term
					}

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
				$lang_id = (int) $_POST['lang'];
				$lang = $this->get_language($lang_id);
				$error = $this->validate_lang($lang);

				if ($error == 0) {
					// Update links to this language in posts and terms in case the slug has been modified
					$old_slug = $lang->slug;

					if ($old_slug != $_POST['slug']) {
						// update the language slug in posts meta
						$args = array('numberposts'=>-1, 'meta_key'=>'_lang-'.$old_slug, 'post_type'=>'any', 'post_status'=>'any');
						$posts = get_posts($args);
						foreach ($posts as $post) {
							$post_id = get_post_meta($post->ID, '_lang-'.$old_slug, true);
							delete_post_meta($post->ID, '_lang-'.$old_slug);
							update_post_meta($post->ID, '_lang-'.$_POST['slug'], $post_id);
						}

						// update the language slug in categories & post tags meta
						$terms = get_terms(get_taxonomies(array('show_ui'=>true)), 'get=all');
 						foreach ($terms as $term) {
							if ($term_id = get_metadata('term', $term->term_id, '_lang-'.$old_slug, true)) {
								delete_metadata('term', $term->term_id, '_lang-'.$old_slug);
								update_metadata('term', $term->term_id, '_lang-'.$_POST['slug'], $term_id);
							}
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
							update_metadata('term', $term_id, '_language', $options['default_lang'] );
						}
					}
				}
				break;

			default:
		}

		// prepare the list table of languages
		$data = array();
		foreach ($listlanguages as $lang)
			$data[] = (array) $lang;

		$list_table->prepare_items($data);


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
			if (get_metadata('term', $term_id, '_language'))
				unset($terms[$key]);
		}
		$terms = implode(',', $terms);

		$errors[1] = __('Enter a valid WorPress locale', 'polylang');
		$errors[2] = __('The language code must be 2 characters long', 'polylang');
		$errors[3] = __('The language code must be unique', 'polylang');
		$errors[4] = __('The language must have a name', 'polylang');

		// displays the page
		include(INC_DIR.'/languages-form.php');
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

	// adds the language column (before the date column) in the posts and pages list table
	function add_post_column($columns, $post_type ='') {
		if ($post_type == '' || get_post_type_object($post_type)->show_ui) {
			foreach (array( 'date', 'comments' ) as $k) {
				if (array_key_exists($k, $columns))
					$end[$k] = array_pop($columns);
			}

			$columns['language'] = __('Language', 'polylang');

			if (isset($end))
				$columns = array_merge($columns, $end);
		}
    return $columns;
	}

	// fills the language column in the posts table
	function post_column($column, $post_id) {
		if ($column == 'language' && $lang = $this->get_post_language($post_id))
			echo esc_attr($lang->name);
	}

	// converts language term_id to slug in $query
	// needed to filter the posts by language with wp_dropdown_categories in restrict_manage_posts
	function parse_query($query) {
    global $pagenow;
    $qvars = &$query->query_vars;
    if ($pagenow=='edit.php' && isset($qvars['lang']) && $qvars['lang'] && is_numeric($qvars['lang']) && $lang = $this->get_language($qvars['lang']))
			$qvars['lang'] = $lang->slug;
	}

	// adds a filter for languages in the Posts and Pages panels
	function restrict_manage_posts() {
		global $wp_query;
		$screen = get_current_screen(); // since WP 3.1
		$languages = $this->get_languages_list();

		if (!empty($languages) && $screen->base == 'edit') {
			$qvars = $wp_query->query;
			wp_dropdown_categories(array(
				'show_option_all' => __('Show all languages', 'polylang'),
				'name' => 'lang',
				'selected' => isset($qvars['lang']) ? $qvars['lang'] : 0,
				'taxonomy' => 'language',
				'hide_empty' => 0
			));
		}
	}

	// adds the Languages box in the 'Edit Post' and 'Edit Page' panels (as well as in custom post types panels
	function add_meta_boxes() {
		foreach(get_post_types( array( 'show_ui' => true ) ) as $ptype)
			add_meta_box('ml_box', __('Languages','polylang'), array(&$this,'post_language'), $ptype, 'side','high');
	}

	// the Languages metabox in the 'Edit Post' and 'Edit Page' panels
	function post_language() {
		global $post_ID;
		$post_type = get_post_type($post_ID);

		$lang = $this->get_post_language($post_ID);
		if (isset($_GET['new_lang']))
			$lang = $this->get_language($_GET['new_lang']);

		$listlanguages = $this->get_languages_list();

		include(INC_DIR.'/post-metabox.php');
	}

	// ajax response for post metabox
	function post_lang_choice() {
		$listlanguages = $this->get_languages_list();

		$lang = $this->get_language($_POST['lang']);
		$post_ID = $_POST['post_id'];
		$post_type = get_post_type($post_ID);

		if ($lang && !is_wp_error($lang))
			include(INC_DIR.'/post-translations.php');

		die();
	}

	// called when a post (or page) is saved, published or updated
	// the underscore in '_lang' hides the post meta in the Custom Fields metabox in the Edit Post screen
	function save_post($post_id) {
		if ($id = wp_is_post_revision($post_id))
			$post_id = $id;

		if (isset($_POST['post_lang_choice']))
			wp_set_post_terms($post_id, $_POST['post_lang_choice'], 'language' );

		$lang = $this->get_post_language($post_id);
		$listlanguages = $this->get_languages_list();

		foreach ($listlanguages as $language) {

			if (isset($_POST[$language->slug]) && $_POST[$language->slug]) {
				// FIXME I don't check that the translated post is in the right language !
				update_post_meta($post_id, '_lang-'.$language->slug, $_POST[$language->slug]); // saves the links to translated posts
				if ($lang)
					update_post_meta($this->get_translated_post($post_id, $language), '_lang-'.$lang->slug, $post_id); // tells the translated to link to this post
			}
			else {
				// deletes the translation in case there was previously one
				if ($lang)
					delete_post_meta($this->get_translated_post($post_id, $language), '_lang-'.$lang->slug); // in the translated post

				delete_post_meta($post_id, '_lang-'.$language->slug); // in this post
			}
		}

		// propagates translations between them
		foreach ($listlanguages as $language) {
			foreach ($listlanguages as $lg) {
				if ($lg != $lang && $lg != $language && $id = $this->get_translated_post($post_id, $lg))
					update_post_meta($this->get_translated_post($post_id, $language), '_lang-'.$lg->slug, $id);
			}
		}
	}

	// called when a post (or page) is deleted
	function delete_post($post_id) {
		if ($id = wp_is_post_revision($post_id))
			$post_id = $id;

		if($lang = $this->get_post_language($post_id)) {
			foreach ($this->get_languages_list() as $language) {
				delete_post_meta($this->get_translated_post($post_id, $language), '_lang-'.$lang->slug); // delete links to this post
			// WP deletes post metas linked to this post so it is useless to do it before
			}
		}
	}

	// adds the language field in the 'Categories' and 'Post Tags' panels
	function add_term_form() {
		$taxonomy = $_GET['taxonomy'];
		$lang = null;
		if (isset($_GET['new_lang']))
			$lang = $this->get_language($_GET['new_lang']);

		$listlanguages = $this->get_languages_list();

		// displays the language field
		include(INC_DIR.'/add-term-form.php');
	}

	// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels
	function edit_term_form($tag) {
		$term_id = $tag->term_id;
		$lang = $this->get_term_language($term_id);
		$taxonomy = $tag->taxonomy;
		$listlanguages = $this->get_languages_list();

		include(INC_DIR.'/edit-term-form.php');
	}

	// adds the language column (before the posts column) in the 'Categories' or Post Tags table
	function add_term_column($columns) {
		if (array_key_exists('posts', $columns))
			$end = array_pop($columns);

		$columns['language'] = __('Language', 'polylang');

		if (isset($end))
			$columns['posts'] = $end;

    return $columns;
	}

	// fills the language column in the 'Categories' or Post Tags table
	function term_column($empty, $column, $term_id) {
		if ($column == 'language' && $lang = $this->get_term_language($term_id))
			echo esc_attr($lang->name);
	}


	// called when a category or post tag is created or edited
	function save_term($term_id) {

		if (isset($_POST['term_lang_choice']) && $_POST['term_lang_choice'])
			update_metadata('term', $term_id, '_language', $_POST['term_lang_choice'] );
		else
			delete_metadata('term', $term_id, '_language');


		$lang = $this->get_term_language($term_id);
		$listlanguages = $this->get_languages_list();

		foreach ($listlanguages as $language)	{
			$slug = '_lang-'.$language->slug;
			if (isset($_POST[$slug]) && $_POST[$slug]) {
				// FIXME I don't check that the translated term is in the right language !
				update_metadata('term', $term_id, $slug, $_POST[$slug] );
				if ($lang)
					update_metadata('term', $_POST[$slug], '_lang-'.$lang->slug, $term_id );
			}
			else {
				// deletes the translation in case there was previously one
				if ($lang)
					delete_metadata('term', $this->get_translated_term($term_id, $language), '_lang-'.$lang->slug); // in the translated term
				delete_metadata('term', $term_id, '_lang-'.$language->slug); // in this term
			}

		}

		// propagates translations between them
		if (isset($lang)) {
			foreach ($listlanguages as $language) {
				foreach ($listlanguages as $lg) {
					if ($lg != $lang && $lg != $language && $id = $this->get_translated_term($term_id, $lg))
						update_metadata('term',$this->get_translated_term($term_id, $language), '_lang-'.$lg->slug, $id);
				}
			}
		}

	}

	// called when a category or post tag is deleted
	function delete_term($term_id) {
		if($lang = $this->get_term_language($term_id)) {
			delete_metadata('term', $term_id, '_language' );
			$listlanguages = $this->get_languages_list();
			foreach ($listlanguages as $language)	{
				delete_metadata('term', $this->get_translated_term($term_id, $language), '_lang-'.$lang->slug); // delete links to this term
			}
			foreach ($listlanguages as $language)	{
				delete_metadata('term', $term_id, '_lang-'.$language->slug);
			}
		}
	}

	// returns all terms in the $taxonomy in the $term_language which have no translation in the $translation_language
	function get_terms_not_translated($taxonomy, $term_language, $translation_language) {
		$new_terms = array();
		foreach (get_terms($taxonomy, 'hide_empty=0') as $term) {
			$lang = $this->get_term_language($term->term_id);
			if ($lang && $lang->name == $term_language->name && !$this->get_translated_term($term->term_id, $translation_language))
				$new_terms[] = $term;
		}
		return $new_terms;
	}

	// ajax response for edit term form
	function term_lang_choice()
	{
		if ($_POST['lang']) {
			$lang = $this->get_language($_POST['lang']);
			$term_id = isset($_POST['term_id']) ? $_POST['term_id'] : null;
			$taxonomy = $_POST['taxonomy'];

			$listlanguages = $this->get_languages_list();

			include(INC_DIR.'/term-translations.php');
		}
		die();
	}

	// modifies the theme location nav menu metabox
	function nav_menu_theme_locations() {
		// only if the theme supports nav menus and a nav menu exists
		if ( ! current_theme_supports( 'menus' ) || !isset($_REQUEST['menu']) )
			return;

		// thanks to: http://wordpress.stackexchange.com/questions/2770/how-to-add-a-custom-metabox-to-the-menu-management-admin-screen
		$metabox = &$GLOBALS['wp_meta_boxes']['nav-menus']['side']['default']['nav-menu-theme-locations'];
		$metabox['callback'] = array(&$this,'nav_menu_language');
		$metabox['title'] = __('Theme locations and languages', 'polylang');
	}

	// displays a message to redirect to the languages options page
	function nav_menu_language() {
		echo '<p class="howto">' . sprintf (__('Please go to the %slanguages page%s to set theme locations and languages', 'polylang'),
			'<a href="' . admin_url('options-general.php?page=mlang#menus') . '">', '</a>') . '</p>';
	}

	// modifies the widgets forms to add our language dropdwown list
	function in_widget_form($widget) {
		$id = esc_attr($widget->id.'_lang_choice');
		$widget_lang = get_option('polylang_widgets');

		printf(
			'<p><label for="%s">%s<select name="%s" id="%s" class="tags-input"><option value="0">%s</option>',
			$id,
			__('The widget is displayed for:', 'polylang'),
			$id,
			$id,
			__('All languages', 'polylang')
		);
		foreach ($this->get_languages_list() as $language) {
			printf(
				"<option value='%s'%s>%s</option>\n",
				esc_attr($language->slug),
				isset($widget_lang[$widget->id]) && $language->slug == $widget_lang[$widget->id] ? ' selected="selected"' : '',
				esc_attr($language->name)
			);
		}
		echo '</select></label></p>';
	}

	// called when widget options are saved
	function widget_update_callback($instance, $new_instance, $old_instance, $widget) {
		$widget_lang = get_option('polylang_widgets');
		$widget_lang[$widget->id] = $_POST[$widget->id.'_lang_choice'];
		update_option('polylang_widgets', $widget_lang);
		return $instance;
	}

	// returns the locale based on user preference
	function get_locale($locale) {
		return get_user_meta(get_current_user_id(), 'user_lang', 'true');
	}

	// updates language user preference
	function personal_options_update($user_id) {
		update_user_meta($user_id, 'user_lang', $_POST['user_lang']);
	}

	// form for language user preference
	function personal_options($profileuser) {
		include(INC_DIR.'/personal-options.php');
	}

	// refresh rewrite rules if the 'page_on_front' option is modified
	function update_option($option) {
		global $wp_rewrite;
		if ($option == 'page_on_front')
			$wp_rewrite->flush_rules();
	}
} // class Polylang_Admin

?>

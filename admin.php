<?php

require_once(POLYLANG_DIR.'/list-table.php');
define('POLYLANG_URL', WP_PLUGIN_URL .'/polylang');

class Polylang_Admin extends Polylang_Base {
	function __construct() {
		add_action('admin_init',  array(&$this, 'admin_init'));

		// adds a 'settings' link in the plugins table
		$plugin_file = basename( dirname( __FILE__ ) ).'/polylang.php';
		add_filter('plugin_action_links_'.$plugin_file, array(&$this, 'plugin_action_links'));

		// adds the link to the languages panel in the wordpress admin menu 
		add_action('admin_menu', array(&$this, 'add_menus'));

		// setup js scripts
		add_action('admin_print_scripts', array($this,'admin_js'));

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

		// adds actions related to languages when saving aor deleting posts and pages
		add_action('save_post', array(&$this, 'save_post'));
		add_action('before_delete_post', array(&$this, 'delete_post'));

		// adds the language field in the 'Categories' and 'Post Tags' panels
		add_action('category_add_form_fields',  array(&$this, 'add_term_form'));
		add_action('post_tag_add_form_fields',  array(&$this, 'add_term_form'));

		// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels 
		add_action('category_edit_form_fields',  array(&$this, 'edit_term_form'));
		add_action('post_tag_edit_form_fields',  array(&$this, 'edit_term_form'));

		// ajax response for edit term form
		add_action('wp_ajax_term_lang_choice', array($this,'term_lang_choice'));

		// adds the language column in the 'Categories' and 'Post Tags' tables 
		add_filter('manage_edit-category_columns',  array(&$this, 'add_term_column'));
		add_filter('manage_edit-post_tag_columns',  array(&$this, 'add_term_column'));
    add_action('manage_category_custom_column', array(&$this, 'term_column'), 10, 3);
    add_action('manage_post_tag_custom_column', array(&$this, 'term_column'), 10, 3);		

		// adds actions related to languages when saving or deleting categories and post tags
		add_action('created_category', array(&$this, 'save_term'));	
		add_action('created_post_tag', array(&$this, 'save_term'));	
		add_action('edited_category', array(&$this, 'save_term'));	
		add_action('edited_post_tag', array(&$this, 'save_term'));	
		add_action('delete_category', array(&$this, 'delete_term'));	
		add_action('delete_post_tag', array(&$this, 'delete_term'));

		// modifies the theme location nav menu metabox
		add_filter('admin_head-nav-menus.php', array(&$this, 'nav_menu_theme_locations'));

		// widgets languages filter
		add_action('in_widget_form', array(&$this, 'in_widget_form'));
		add_filter('widget_update_callback', array(&$this, 'widget_update_callback'), 10, 4);

		// refresh rewrite rules if the 'page_on_front' option is modified
		add_action('update_option', array(&$this, 'update_option'));
	}

	// manage versions
	// not very useful now but we don't know the future
	function admin_init() {
		$options = get_option('polylang');
		if ($options['version'] < POLYLANG_VERSION) {
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
		add_submenu_page('options-general.php', __('Languages','polylang'), __('Languages','polylang'), 'manage_options', 'mlang',  array($this, 'languages_page'));
	}

	// setup js scripts
	function admin_js() {
		wp_enqueue_script('polylang_admin', POLYLANG_URL.'/admin.js');
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

		$action = '';
		if (isset($_REQUEST['action']))		
			$action = $_REQUEST['action'];

		switch ($action) {
			case 'add':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );

				// FIXME improve data validation
				// FIXME add an error message if conditions are not fulfilled
				if ($_POST['name'] && $_POST['description'] && $_POST['slug']) {
					wp_insert_term($_POST['name'],'language',array('slug'=>$_POST['slug'], 'description'=>$_POST['description']));
					$wp_rewrite->flush_rules(); // refresh rewrite rules

					if (!isset($options['default_lang'])) { // if this is the first language created, set it as default language
						$options['default_lang'] = $_POST['slug'];
						update_option('polylang', $options);
					}
				}
				wp_redirect('admin.php?page=mlang'); // to refresh the page (possible thanks to the $_GET['noheader']=true)
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
						foreach ($languages as $language) {
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
					$terms = get_terms(array('category', 'post_tag'), 'get=all');
 					foreach ($terms as $term) {
						if ($this->get_term_language($term->term_id) == $lang) {
							foreach ($languages as $language) {
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
						if (!empty($languages))
							$options['default_lang'] = $languages[0]->slug; // arbitrary choice...
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

				// FIXME add an error message if conditions are not fulfilled
				if ($_POST['lang'] && $_POST['name'] && $_POST['description'] && $_POST['slug']) {
					// Update links to this language in posts and terms in case the slug has been modified
					$lang_id = (int) $_POST['lang'];					
					$lang = $this->get_language($lang_id);
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
						$terms = get_terms(array('category', 'post_tag'), 'get=all');
 						foreach ($terms as $term) {
							$term_id = get_metadata('term', $term->term_id, '_lang-'.$old_slug, true);
							if ($term_id) {
								delete_metadata('term', $term->term_id, '_lang-'.$old_slug);
								update_metadata('term', $term->term_id, '_lang-'.$_POST['slug'], $term_id);
							}
						}				
					}

					// and finally update the language itself 
					wp_update_term($lang_id, 'language', array('name'=>$_POST['name'], 'slug'=>$_POST['slug'], 'description'=>$_POST['description']));
					$wp_rewrite->flush_rules(); // refresh rewrite rules
				}
				wp_redirect('admin.php?page=mlang'); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'nav-menus':
				check_admin_referer( 'nav-menus-lang', '_wpnonce_nav-menus-lang' );

				$menu_lang = $_POST['menu-lang'];
				update_option('polylang_nav_menus', $menu_lang);
				break;

			case 'options':
				check_admin_referer( 'options-lang', '_wpnonce_options-lang' );

				$options['rewrite'] = $_POST['rewrite'];
				isset($_POST['browser']) ? $options['browser'] = 1 : $options['browser'] = 0;
				$options['default_lang'] = $_POST['default_lang'];
				update_option('polylang', $options);

				// refresh refresh permalink structure and rewrite rules in case rewrite option has been modified
				$wp_rewrite->extra_permastructs['language'][0] = $options['rewrite'] ? '%language%' : '/language/%language%'; 
				$wp_rewrite->flush_rules(); 
				break;			

			default:
		}

		// prepare the list table of languages
		$data = array();
		foreach ($listlanguages as $lang) 			
			$data[] = (array) $lang;

		$list_table->prepare_items($data);

		// displays the page 
		include(POLYLANG_DIR.'/languages-form.php');
	}

	// adds the language column (before the date column) in the posts and pages list table
	function add_post_column($columns, $post_type ='') {
		if (in_array($post_type, array('', 'post', 'page'))) {
			$keys = array( 'date', 'comments' );
			foreach ($keys as $k) {
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
		$lang = $this->get_post_language($post_id);
		if ($lang && $column == 'language')
			echo esc_attr($lang->name);
	}

	// converts language term_id to slug in $query
	// needed to filter the posts by language with wp_dropdown_categories in restrict_manage_posts
	function parse_query($query) {
    global $pagenow;
    $qvars = &$query->query_vars;
    if ($pagenow=='edit.php' && isset($qvars['lang']) && $qvars['lang'] && is_numeric($qvars['lang'])) {
			$lang = $this->get_language($qvars['lang']);
			if ($lang)
				$qvars['lang'] = $lang->slug;
    }
	}

	// adds a filter for languages in the Posts and Pages panels
	function restrict_manage_posts() {
		global $wp_query;
		$screen = get_current_screen();
		$languages = $this->get_languages_list();

		if (!empty($languages) && $screen->base == 'edit') {
			$qvars = $wp_query->query;
			wp_dropdown_categories(array(
				'show_option_all' => __('Show all languages', 'polylang'),
				'name' => 'lang', 
				'selected' => isset($qvars['lang']) ? $qvars['lang'] : 0,
				'taxonomy' => 'language'));
		}
	}

	// adds the Languages box in the 'Edit Post' and 'Edit Page' panels
	function add_meta_boxes() {
		add_meta_box('ml_box', __('Languages','polylang'), array(&$this,'post_language'), 'post', 'side','high');
		add_meta_box('ml_box', __('Languages','polylang'), array(&$this,'post_language'), 'page', 'side','high');
	}

	// the Languages metabox in the 'Edit Post' and 'Edit Page' panels  
	function post_language() {
		global $post_ID;
		$post_type = get_post_type($post_ID);

		$lang = $this->get_post_language($post_ID);
		if (isset($_GET['new_lang']))
			$lang = $this->get_language($_GET['new_lang']);

		$listlanguages = $this->get_languages_list();
 
		include(POLYLANG_DIR.'/post-metabox.php');
	}

	// ajax response for post metabox
	function post_lang_choice() {
		$listlanguages = $this->get_languages_list();

		$lang = $this->get_language($_POST['lang']);
		$post_ID = $_POST['post_id'];

		if ($lang)
			include(POLYLANG_DIR.'/post-translations.php');

		die();
	}

	// called when a post (or page) is saved, published or updated
	// the underscore in '_lang' hides the post meta in the Custom Fields metabox in the Edit Post screen
	function save_post($post_id) {

		$id = wp_is_post_revision($post_id);
		if ($id)
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
				$id = $this->get_translated_post($post_id, $lg);
				if ($lg != $lang && $lg != $language && $id)
					update_post_meta($this->get_translated_post($post_id, $language), '_lang-'.$lg->slug, $id);
			}
		}
	}

	// called when a post (or page) is deleted
	function delete_post($post_id) {
		$id = wp_is_post_revision($post_id);
		if ($id)
			$post_id = $id;

		$lang = $this->get_post_language($post_id);
		if($lang) {		
			$listlanguages = $this->get_languages_list();
			foreach ($listlanguages as $language) {
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
		include(POLYLANG_DIR.'/add-term-form.php');
	}

	// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels
	function edit_term_form($tag) {
		$term_id = $tag->term_id;
		$lang = $this->get_term_language($term_id);
		$taxonomy = $tag->taxonomy;
		$listlanguages = $this->get_languages_list();

		include(POLYLANG_DIR.'/edit-term-form.php');
	}

	// ajax response for edit term form
	function term_lang_choice()
	{
		if ($_POST['lang']) {
			$lang = $this->get_language($_POST['lang']);
			$term_id = isset($_POST['term_id']) ? $_POST['term_id'] : null;
			$taxonomy = $_POST['taxonomy'];

			$listlanguages = $this->get_languages_list();

			include(POLYLANG_DIR.'/term-translations.php');
		}
		die();
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
		$lang = $this->get_term_language($term_id);
		if ($lang && $column == 'language')
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
					$id = $this->get_translated_term($term_id, $lg);
					if ($lg != $lang && $lg != $language && $id)
						update_metadata('term',$this->get_translated_term($term_id, $language), '_lang-'.$lg->slug, $id);
				}
			}
		}

	}

	// called when a category or post tag is deleted
	function delete_term($term_id) {
		$lang = $this->get_term_language($term_id);
		if($lang) {
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
		$terms = get_terms($taxonomy, 'hide_empty=0');
		foreach ($terms as $term) {
			$lang = $this->get_term_language($term->term_id);
			if ($lang && $lang->name == $term_language->name) {
				$translated_term = $this->get_translated_term($term->term_id, $translation_language);
				if (!$translated_term)
					$new_terms[] = $term;
			}
		}
		return $new_terms;
	}

	// modifies the theme location nav menu metabox
	function nav_menu_theme_locations() {
		// only if the theme supports nav menus
		if ( ! current_theme_supports( 'menus' ) )
			return;

		// thanks to : http://wordpress.stackexchange.com/questions/2770/how-to-add-a-custom-metabox-to-the-menu-management-admin-screen
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
		$listlanguages = $this->get_languages_list();
		$widget_lang = get_option('polylang_widgets');

		printf(
			'<p><label for="%s">%s<select name="%s" id="%s" class="tags-input"><option value="0">%s</option>', 
			$id,
			__('The widget is displayed for:', 'polylang'),
			$id,
			$id,
			__('All languages', 'polylang')
		);
		foreach ($listlanguages as $language) {
			printf(
				"<option value='%s'%s>%s</option>\n",
				esc_attr($language->slug),
				$language->slug == $widget_lang[$widget->id] ? ' selected="selected"' : '',
				esc_attr($language->name)
			);
		}
		echo '</select></label></p>';
	}

	// called when widget options are saved
	function widget_update_callback($instance, $new_instance, $old_instance, $widget) {
		$id = $widget->id.'_lang_choice';
		if (isset($_POST[$id]) && $_POST[$id]) {
			$widget_lang = get_option('polylang_widgets');
			$widget_lang[$widget->id] = $_POST[$id];
			update_option('polylang_widgets', $widget_lang); 
		}
	}

	// refresh rewrite rules if the 'page_on_front' option is modified
	function update_option($option) {
		global $wp_rewrite;
		if ($option == 'page_on_front')
			$wp_rewrite->flush_rules(); 
	}
} // class Polylang_Admin

?>

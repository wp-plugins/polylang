<?php

require_once(dirname(__FILE__).'/list-table.php');

class Polylang_Admin extends Polylang_Base {
	function __construct() {
		// adds a 'settings' link in the plugins table
		$plugin_file = basename( dirname( __FILE__ ) ).'/polylang.php';
		add_filter('plugin_action_links_'.$plugin_file, array(&$this, 'plugin_action_links'));

		// adds the link to the languages panel in the wordpress admin menu 
		add_action('admin_menu', array(&$this, 'add_menus'));

		// add the language column (as well as a filter by language) in 'All Posts' an 'All Pages' panels  
		add_filter('manage_posts_columns',  array(&$this, 'add_post_column'), 10, 2);
		add_filter('manage_pages_columns',  array(&$this, 'add_post_column'));
    add_action('manage_posts_custom_column', array(&$this, 'post_column'), 10, 2);
    add_action('manage_pages_custom_column', array(&$this, 'post_column'), 10, 2);
		add_filter('parse_query',array(&$this,'parse_query'));
		add_action('restrict_manage_posts', array(&$this, 'restrict_manage_posts'));				

		// adds the Languages box in the 'Edit Post' and 'Edit Page' panels
		add_action('add_meta_boxes', array(&$this, 'add_meta_boxes'));

		// adds actions related to languages when saving aor deleting posts and pages
		add_action('save_post', array(&$this, 'save_post'));
		add_action('before_delete_post', array(&$this, 'delete_post'));

		// adds the language field in the 'Categories' and 'Post Tags' panels
		add_action('category_add_form_fields',  array(&$this, 'add_term_form'));
		add_action('post_tag_add_form_fields',  array(&$this, 'add_term_form'));

		// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels 
		add_action('category_edit_form_fields',  array(&$this, 'edit_term_form'));
		add_action('post_tag_edit_form_fields',  array(&$this, 'edit_term_form'));

		// adds the language column in the 'Categories' and 'Post Tags' tables 
		add_filter('manage_edit-category_columns',  array(&$this, 'add_term_column'));
		add_filter('manage_edit-post_tag_columns',  array(&$this, 'add_term_column'));
    add_action('manage_category_custom_column', array(&$this, 'term_column'), 10, 3);
    add_action('manage_post_tag_custom_column', array(&$this, 'term_column'), 10, 3);		

		// adds actions related to languages when saving or deleting categories and post tags
		add_action('created_category', array(&$this,'save_term'));	
		add_action('created_post_tag', array(&$this,'save_term'));	
		add_action('edited_category', array(&$this,'save_term'));	
		add_action('edited_post_tag', array(&$this,'save_term'));	
		add_action('delete_category', array(&$this,'delete_term'));	
		add_action('delete_post_tag', array(&$this,'delete_term'));

		// refresh rewrite rules if the 'page_on_front' option is modified
		add_action('update_option', array(&$this,'update_option'));
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

	// the languages panel
	function languages_page() {
		global $wp_rewrite;
		$options = get_option('polylang');

		$listlanguages = $this->get_languages_list();
		$list_table = new Polylang_List_Table();

		$action = '';
		if (isset($_REQUEST['action']))		
			$action = $_REQUEST['action'];

		switch ($action) {
			case 'add':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );

				if (isset($_POST['name']) && isset($_POST['description'])) {
					wp_insert_term($_POST['name'],'language',array('slug'=>$_POST['slug'], 'description'=>$_POST['description']));
					$wp_rewrite->flush_rules(); // refresh rewrite rules
				}
				if (!isset($options['default_lang'])) { // if this is the first language created, set it as default language
					$options['default_lang'] = $_POST['slug'];
					update_option('polylang', $options);
				}
				wp_redirect('admin.php?page=mlang'); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
				break;

			case 'delete':
				check_admin_referer( 'delete-lang');

				if (isset($_GET['lang'])) {
					$lang = $this->get_language($_GET['lang']);
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
							delete_metadata('term', $term->term_id, '_language', $_GET['lang']); // delete language of this term
						}
						delete_metadata('term', $term->term_id, '_lang-'.$lang_slug); // deletes references to this term in translated term
					}

					// delete the language itself
					wp_delete_term($_GET['lang'], 'language');
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
				if (isset($_GET['lang']))
					$edit_lang = $this->get_language($_GET['lang']); 
				break;

			case 'update':
				check_admin_referer( 'add-lang', '_wpnonce_add-lang' );

				if (isset($_POST['lang'])) {
					// Update links to this language in posts and terms in case the slug has been modified
					$lang = $this->get_language($_POST['lang']);
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
					wp_update_term($_POST['lang'], 'language', array('name'=>$_POST['name'], 'slug'=>$_POST['slug'], 'description'=>$_POST['description']));
					$wp_rewrite->flush_rules(); // refresh rewrite rules
				}
				wp_redirect('admin.php?page=mlang'); // to refresh the page (possible thanks to the $_GET['noheader']=true)
				exit;
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
		include(dirname(__FILE__).'/languages-form.php');
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
			echo $lang->name;
	}

	// converts language term_id to slug in $query
	// needed to filter the posts by language with wp_dropdown_categories in restrict_manage_posts
	function parse_query($query) {
    global $pagenow;
    $qvars = &$query->query_vars;
    if ($pagenow=='edit.php' && isset($qvars['lang']) && is_numeric($qvars['lang'])) {
			$lang = $this->get_language($qvars['lang']);
			if ($lang)
				$qvars['lang'] = $lang->slug;
    }
	}

	// adds a filter for languages in the Posts and Pages panels
	function restrict_manage_posts() {
		global $typenow;
		global $wp_query;
		$languages = $this->get_languages_list();

		if (!empty($languages) && in_array($typenow, array('', 'post', 'page'))) {
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
		add_meta_box('ml_box', __('Languages','multiLang'), array(&$this,'post_language'), 'post', 'side','high');
		add_meta_box('ml_box', __('Languages','multiLang'), array(&$this,'post_language'), 'page', 'side','high');
	}

	// the Languages metabox in the 'Edit Post' and 'Edit Page' panels  
	function post_language() {
		global $post_ID;
		$post_type = get_post_type($post_ID);

		$lang = $this->get_post_language($post_ID);
		if (isset($_GET['new_lang']))
			$lang = $this->get_language($_GET['new_lang']);
 
		include(dirname(__FILE__).'/post-metabox.php');
	}

	// called when a post (or page) is saved, published or updated
	// the underscore in '_lang' hides the post meta in the Custom Fields metabox in the Edit Post screen
	function save_post($post_id) {
		$id = wp_is_post_revision($post_id);
		if ($id)
			$post_id = $id;

		if (isset($_POST['lang_choice']))
			wp_set_object_terms($post_id, $_POST['lang_choice'], 'language' );

		$lang = $this->get_post_language($post_id);		
		$listlanguages = $this->get_languages_list();
		foreach ($listlanguages as $language) {
			if (isset($_POST[$language->slug])) {
				$value = $_POST[$language->slug];
				if ($value)
					update_post_meta($post_id, '_lang-'.$language->slug, $value); // saves the links to translated posts 
			}
			if ($lang && $language != $lang){
				update_post_meta($this->get_translated_post($post_id, $language), '_lang-'.$lang->slug, $post_id); // tells the translated to link to this post
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
		$lang = NULL;
		if (isset($_GET['new_lang']))
			$lang = $this->get_language($_GET['new_lang']); 

		$listlanguages = $this->get_languages_list();

		// displays the language field
		include(dirname(__FILE__).'/add-term-form.php');
	}

	// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels
	function edit_term_form($tag) {
		$term_id = $tag->term_id;
		$lang = $this->get_term_language($term_id);

		$listlanguages = $this->get_languages_list();
		$taxonomy = $tag->taxonomy;

		include(dirname(__FILE__).'/edit-term-form.php');
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
			echo $lang->name;
	}

	// called when a category or post tag is created or edited
	function save_term($term_id) {
		if (isset($_POST['lang_choice']) && $_POST['lang_choice'] != -1 ) {
			update_metadata('term', $term_id, '_language', $_POST['lang_choice'] );
			$lang = $this->get_language($_POST['lang_choice']);

			$listlanguages = $this->get_languages_list();
			foreach ($listlanguages as $language)	{
				$slug = '_lang-'.$language->slug;
				if (isset($_POST[$slug]) && $_POST[$slug] != -1) {
					update_metadata('term', $term_id, $slug, $_POST[$slug] );
					update_metadata('term', $_POST[$slug], '_lang-'.$lang->slug, $term_id );
				}
			}
		}

		$qvars = $this->get_referer_vars();
		if (isset($qvars['from_tag']) && isset($qvars['from_lang']) && isset($qvars['new_lang'])) {
			$from_lang = $this->get_language($qvars['from_lang']);
			update_metadata('term', $term_id, '_lang-'.$from_lang->slug, $qvars['from_tag'] );
			$new_lang = $this->get_language($qvars['new_lang']);
			update_metadata('term', $qvars['from_tag'], '_lang-'.$new_lang->slug, $term_id );
		}

		// propagates translations between them
		foreach ($listlanguages as $language) {
			foreach ($listlanguages as $lg) {
				$id = $this->get_translated_term($term_id, $lg);
				if ($lg != $lang && $lg != $language && $id)
					update_metadata('term',$this->get_translated_term($term_id, $language), '_lang-'.$lg->slug, $id);
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

	// refresh rewrite rules if the 'page_on_front' option is modified
	function update_option($option) {
		global $wp_rewrite;
		if ($option == 'page_on_front')
			$wp_rewrite->flush_rules(); 
	}
} // class Polylang_Admin

?>

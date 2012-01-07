<?php

// all modifications of the WordPress admin ui
class Polylang_Admin_Filters extends Polylang_Base {
	function __construct() {
		// additionnal filters and actions
		add_action('admin_init',  array(&$this, 'admin_init'));

		// setup js scripts andd css styles
		add_action('admin_enqueue_scripts', array(&$this,'admin_enqueue_scripts'));

		// add the language and translations columns (as well as a filter by language) in 'All Posts' an 'All Pages' panels
		add_filter('manage_posts_columns',  array(&$this, 'add_post_column'), 10, 2);
		add_filter('manage_pages_columns',  array(&$this, 'add_post_column'));
    add_action('manage_posts_custom_column', array(&$this, 'post_column'), 10, 2);
    add_action('manage_pages_custom_column', array(&$this, 'post_column'), 10, 2);
		add_filter('parse_query',array(&$this,'parse_query'));
		add_action('restrict_manage_posts', array(&$this, 'restrict_manage_posts'));

		// adds the Languages box in the 'Edit Post' and 'Edit Page' panels
		add_action('add_meta_boxes', array(&$this, 'add_meta_boxes'));

		// ajax response for changing the language in the post metabox
		add_action('wp_ajax_post_lang_choice', array(&$this,'post_lang_choice'));

		// filters the pages by language in the parent dropdown list in the page attributes metabox
		add_filter('page_attributes_dropdown_pages_args', array(&$this, 'page_attributes_dropdown_pages_args'), 10, 2);

		// adds actions and filters related to languages when creating, saving or deleting posts and pages
		add_filter('wp_insert_post_parent', array(&$this, 'wp_insert_post_parent'), 10, 4);
		add_action('dbx_post_advanced', array(&$this, 'dbx_post_advanced'));
		add_action('save_post', array(&$this, 'save_post'));
		add_action('before_delete_post', array(&$this, 'delete_post'));

		// filters categories and post tags by language
		add_filter('terms_clauses', array(&$this, 'terms_clauses'), 10, 3);

		// adds actions related to languages when saving categories and post tags
		add_action('created_term', array(&$this, 'save_term'), 10, 3);
		add_action('edited_term', array(&$this, 'save_term'), 10, 3);

		// ajax response for edit term form
		add_action('wp_ajax_term_lang_choice', array(&$this,'term_lang_choice'));

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

	// add these actions and filters here and not in the constructor to be sure that all taxonomies are registered
	function admin_init() {
		foreach (get_taxonomies(array('show_ui'=>true)) as $tax) {
			// adds the language field in the 'Categories' and 'Post Tags' panels
			add_action($tax.'_add_form_fields',  array(&$this, 'add_term_form'));

			// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels
			add_action($tax.'_edit_form_fields',  array(&$this, 'edit_term_form'));

			// adds the language column in the 'Categories' and 'Post Tags' tables
			add_filter('manage_edit-'.$tax.'_columns', array(&$this, 'add_term_column'));
		  add_action('manage_'.$tax.'_custom_column', array(&$this, 'term_column'), 10, 3);

			// adds action related to languages when deleting categories and post tags
			add_action('delete_'.$tax, array(&$this, 'delete_term'));
		}
	}

	// setup js scripts & css styles
	function admin_enqueue_scripts() {
		wp_enqueue_script('polylang_admin', POLYLANG_URL .'/js/admin.js');
		wp_enqueue_style('polylang_admin', POLYLANG_URL .'/css/admin.css');
		
		// style languages columns in edit and edit-tags
		foreach ($this->get_languages_list() as $language)
			$classes[] = '.column-language_'.esc_attr($language->slug);

		if (isset($classes))
			echo '<style type="text/css">'.implode(',', $classes).' { width: 24px; }</style>';
	}

	// adds the language and translations columns (before the date column) in the posts and pages list table
	function add_post_column($columns, $post_type ='') {
		if ($post_type == '' || get_post_type_object($post_type)->show_ui) {
			foreach (array( 'date', 'comments' ) as $k) {
				if (array_key_exists($k, $columns))
					$end[$k] = array_pop($columns);
			}

			foreach ($this->get_languages_list() as $language)
				$columns['language_'.$language->slug] = ($flag = $this->get_flag($language)) ? $flag : esc_html($language->slug);

			if (isset($end))
				$columns = array_merge($columns, $end);
		}
    return $columns;
	}

	// fills the language and translations columns in the posts table
	function post_column($column, $post_id) {
		if (false === strpos($column, 'language_') || !$this->get_post_language($post_id))
			return;

		global $post_type;
		$language = $this->get_language(substr($column, 9));

		// link to edit post (or a translation)
		if ($id = $this->get_post($post_id, $language))
			printf('<a title="%1$s" href="%2$s"><img src="%3$s"></a>',
				esc_attr(get_post($id)->post_title),
				esc_url(get_edit_post_link($id, true )),
				esc_url(POLYLANG_URL.'/img/edit.png')
			);

		// link to add a new translation
		else
			printf('<a title="%1$s" href="%2$s"><img src="%3$s"></a>',
				__('Add new translation', 'polylang'),
 				esc_url(admin_url('post-new.php?post_type=' . $post_type . '&from_post=' . $post_id . '&new_lang=' . $language->slug)),
 				esc_url(POLYLANG_URL.'/img/add.png')
			);
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

		if (!isset($lang)) {
			$options = get_option('polylang');
			$lang = $this->get_language($options['default_lang']);
		}

		$listlanguages = $this->get_languages_list();

		include(PLL_INC.'/post-metabox.php');
	}

	// ajax response for changing the language in the post metabox
	function post_lang_choice() {
		global $post_ID; // obliged to use the global variable for wp_popular_terms_checklist
		$post_ID = $_POST['post_id'];
		$post_type = get_post_type($post_ID);
		$listlanguages = $this->get_languages_list();
		$lang = $this->get_language($_POST['lang']);

		ob_start();
		if ($lang && !is_wp_error($lang))
			include(PLL_INC.'/post-translations.php');
		$x = new WP_Ajax_Response(array('what' => 'translations', 'data' => ob_get_contents()));
		ob_end_clean();

		// categories
		if (isset($_POST['taxonomies'])) {
			// not set for pages
			foreach ($_POST['taxonomies'] as $taxname) {
				$taxonomy = get_taxonomy($taxname);
			
				ob_start();
				$popular_ids = wp_popular_terms_checklist($taxonomy->name);
				$supplemental['populars'] = ob_get_contents();
				ob_end_clean();

				ob_start();
				// use $post_ID to remember ckecked terms in case we come back to the original language
				wp_terms_checklist( $post_ID, array( 'taxonomy' => $taxonomy->name, 'popular_cats' => $popular_ids ));
				$supplemental['all'] = ob_get_contents();
				ob_end_clean();

				ob_start();
				wp_dropdown_categories( array(
					'taxonomy' => $taxonomy->name, 'hide_empty' => 0, 'name' => 'new'.$taxonomy->name.'_parent', 'orderby' => 'name',
					'hierarchical' => 1, 'show_option_none' => '&mdash; '.$taxonomy->labels->parent_item.' &mdash;'
				) );
				$supplemental['dropdown'] = ob_get_contents();
				ob_end_clean();

				$x->Add(array('what' => 'taxonomy', 'data' => $taxonomy->name, 'supplemental' => $supplemental));
			}
		}

		// parent dropdown list (only for hierarchical post types)
		// $dropdown_args copied from page_attributes_meta_box
		if (in_array($post_type, get_post_types(array('hierarchical' => true)))) {
			$post = get_post($post_ID);
			$dropdown_args = array(
				'post_type'        => $post->post_type,
				'exclude_tree'     => $post->ID,
				'selected'         => $post->post_parent,
				'name'             => 'parent_id',
				'show_option_none' => __('(no parent)'),
				'sort_column'      => 'menu_order, post_title',
				'echo'             => 0,
			);
			$dropdown_args = apply_filters('page_attributes_dropdown_pages_args', $dropdown_args, $post);
			$x->Add(array('what' => 'pages', 'data' => wp_dropdown_pages($dropdown_args)));
		}

		$x->send();
	}

	// filters the pages by language in the parent dropdown list in the page attributes metabox
	function page_attributes_dropdown_pages_args($dropdown_args, $post) {
		$lang = isset($_POST['lang']) ? $this->get_language($_POST['lang']) : $this->get_post_language($post->ID); // ajax or not ?
		$pages = implode(',', $this->exclude_pages($lang));
		$dropdown_args['exclude'] = isset($dropdown_args['exclude']) ? $dropdown_args['exclude'].','.$pages : $pages;
		return $dropdown_args;
	}
			
	// translate post parent if exists when using "Add new" (translation)
	function wp_insert_post_parent($post_parent, $post_ID, $keys, $postarr) {
		if (isset($_GET['from_post']) && isset($_GET['new_lang']) && $id = wp_get_post_parent_id($_GET['from_post']))
		 if ($post_parent = $this->get_translation('post', $id, $_GET['new_lang']))
				return $post_parent;

		return $post_parent;
	}

	// copy page template and menu order if exist when using "Add new" (translation)
	// the hook was probably not intended for that but did not find a better one
	// copy the meta '_wp_page_template' in save_post is not sufficient (the dropdown list in the metabox is not updated)
	// We need to set $post->page_template (ans so need to wait for the availability of $post)
	function dbx_post_advanced() {
		if (isset($_GET['from_post']) && isset($_GET['new_lang'])) {
			global $post;
			$post->menu_order = get_post($_GET['from_post'])->menu_order;
			$post->page_template = get_post_meta($_GET['from_post'], '_wp_page_template', true);
		}
	}

	// called when a post (or page) is saved, published or updated
	function save_post($post_id) {
		// avoids breaking translations when using inline or bulk edit
		if(isset($_POST['_inline_edit']) || isset($_GET['bulk_edit']))
			return;

		if ($id = wp_is_post_revision($post_id))
			$post_id = $id;

		// the hook is called when the post is created
		// let's use it to initialize some things when using "Add new" (translation)
		if (isset($_GET['from_post']) && isset($_GET['new_lang'])) {

			// translate terms if exist
			foreach (get_taxonomies(array('show_ui'=>true)) as $tax) {
				$newterms = array();
				$terms = get_the_terms($_GET['from_post'], $tax);
				if (is_array($terms)) {
					foreach ($terms as $term) {
						if ($id = $this->get_translation('term', $term->term_id, $_GET['new_lang']))
							$newterms[] = (int) $id; // cast is important otherwise we get 'numeric' tags
					}
				}
				if (!empty($newterms))				
					wp_set_object_terms($post_id, $newterms, $tax);
			}

			// copy metas and allow plugins to do the same
			$metas = apply_filters('pll_copy_post_metas', array('_wp_page_template', '_thumbnail_id'));
			foreach ($metas as $meta) {
				if ($value = get_post_meta($_GET['from_post'], $meta, true))
					update_post_meta($post_id, $meta, $value);
			}
		}

		if (!isset($_POST['post_lang_choice']))
			return;

		// save language and translations
		$this->set_post_language($post_id, $_POST['post_lang_choice']);
		$this->save_translations('post', $post_id, $_POST['post_tr_lang']);

		// synchronise terms and metas in translations
		foreach ($_POST['post_tr_lang'] as $lang=>$tr_id) {
			if (!$tr_id)
				continue;

			// terms
			foreach (get_taxonomies(array('show_ui'=>true)) as $tax) {
				$newterms = array();
				$terms = get_the_terms($post_id, $tax);
				if (is_array($terms)) {
					foreach ($terms as $term) {
						if ($term_id = $this->get_translation('term', $term->term_id, $lang))
							$newterms[] = (int) $term_id; // cast is important otherwise we get 'numeric' tags
					}
				}
				// for some reasons, the user may have untranslated terms in the translation. don't forget them.
				$tr_terms = get_the_terms($tr_id, $tax);
				if (is_array($tr_terms)) {
					foreach ($tr_terms as $term) {
						if (!$this->get_translation('term', $term->term_id, $_POST['post_lang_choice']))
							$newterms[] = (int) $term->term_id;
					}
				}	
				wp_set_object_terms($tr_id, $newterms, $tax); // replace terms in translation
			}

			// copy metas and allow plugins to do the same
			$metas = apply_filters('pll_copy_metas', array('_wp_page_template', '_thumbnail_id'));
			foreach ($metas as $meta) {
				if ($value = get_post_meta($_GET['from_post'], $meta, true))
					update_post_meta($tr_id, $meta, get_post_meta($post_id, $meta, true));
				else
					delete_post_meta($tr_id, $meta);
			}

			// post parent
			if ($parent_id = wp_get_post_parent_id($post_id));
				$post_parent = $this->get_translation('post', $parent_id, $lang);

			global $wpdb;
			$wpdb->update($wpdb->posts, array('post_parent'=> isset($post_parent) ? $post_parent : 0), array( 'ID' => $tr_id ));
		}
	}

	// called when a post (or page) is deleted
	function delete_post($post_id) {
		if ($id = wp_is_post_revision($post_id))
			$post_id = $id;

		$this->delete_translation('post', $post_id);
	}

	// filters categories and post tags by language when needed
	function terms_clauses($clauses, $taxonomies, $args) {
		// does nothing except on taxonomies which have show_ui set to 1 (includes category and post_tags)
		foreach ($taxonomies as $tax) {
			if (!get_taxonomy($tax)->show_ui)
				return $clauses;
		}

		if (function_exists('get_current_screen'))
			$screen = get_current_screen(); // since WP 3.1, may not be available the first time(s) get_terms is called

		// does nothing in the Categories, Post tags, Languages and Posts* admin panels
		// I test $_POST['action'] for ajax since $screen not defined in this case
		// FIXME Can I improve the way I do that ?
		if (!isset($_POST['action']) && (!isset($screen) || $screen->base == 'edit-tags' || $screen->base == 'toplevel_page_mlang' || $screen->base == 'edit'))
			return $clauses;

		// *FIXME I want all categories in the dropdown list and only the ones in the right language in the inline edit
		// It seems that I need javascript to get the post_id as inline edit data are manipulated in inline-edit-post.js

		// The only ajax response I want to deal with is when changing the language in post metabox
		if (isset($_POST['action']) && $_POST['action'] != 'post_lang_choice')
			return $clauses;

		global $post_ID;
		$options = get_option('polylang');

		// ajax response for changing the language in the post metabox
		if (isset($_POST['lang']))
			$lang = $this->get_language($_POST['lang']);

		// the post is created with the 'add new' (translation) link
		elseif (isset($_GET['new_lang']))
			$lang = $this->get_language($_GET['new_lang']);

		elseif (isset($_GET['post']))
			$lang = $this->get_post_language($_GET['post']);

		// when a new category is created in the edit post panel
		elseif (isset($_POST['term_lang_choice']))
			$lang = $this->get_language($_POST['term_lang_choice']);

		// for a new post
		elseif ($screen->base == 'post' && !PLL_DISPLAY_ALL)
			$lang = $this->get_language($options['default_lang']);

		// adds our clauses to filter by current language
		return isset($lang) ? $this->_terms_clauses($clauses, $lang) : $clauses;
	}

	// adds the language field in the 'Categories' and 'Post Tags' panels
	function add_term_form() {
		$taxonomy = $_GET['taxonomy'];

		if (isset($_GET['new_lang']))
			$lang = $this->get_language($_GET['new_lang']);
		else {
			$options = get_option('polylang');
			$lang = $this->get_language($options['default_lang']);
		}

		$listlanguages = $this->get_languages_list();

		// displays the language field
		include(PLL_INC.'/add-term-form.php');
	}

	// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels
	function edit_term_form($tag) {
		$term_id = $tag->term_id;
		$lang = $this->get_term_language($term_id);
		$taxonomy = $tag->taxonomy;
		$listlanguages = $this->get_languages_list();

		include(PLL_INC.'/edit-term-form.php');
	}

	// adds the language column (before the posts column) in the 'Categories' or Post Tags table
	function add_term_column($columns) {
		if (array_key_exists('posts', $columns))
			$end = array_pop($columns);

		foreach ($this->get_languages_list() as $language)
			$columns['language_'.$language->slug] = ($flag = $this->get_flag($language)) ? $flag : esc_html($language->slug);

		if (isset($end))
			$columns['posts'] = $end;

    return $columns;
	}

	// fills the language column in the 'Categories' or Post Tags table
	function term_column($empty, $column, $term_id) {
		if (false === strpos($column, 'language_') || !$this->get_term_language($term_id))
			return;

		global $post_type, $taxonomy;
		$language = $this->get_language(substr($column, 9));

		// link to edit term (or a translation)
		if ($id = $this->get_term($term_id, $language))
			printf('<a title="%1$s" href="%2$s"><img src="%3$s"></a>',
				esc_attr(get_term($id, $taxonomy)->name),
				esc_url(get_edit_term_link($id, $taxonomy, $post_type)),
				esc_url(POLYLANG_URL.'/img/edit.png')
			);

		// link to add a new translation
		else
			printf('<a title="%1$s" href="%2$s"><img src="%3$s"></a>',
				__('Add new translation', 'polylang'),
				esc_url(admin_url(sprintf('edit-tags.php?taxonomy=%1$s&from_tag=%2$d&new_lang=%3$s', $taxonomy, $term_id, $language->slug))),
 				esc_url(POLYLANG_URL.'/img/add.png')
			);
	}

	// called when a category or post tag is created or edited
	function save_term($term_id, $tt_id, $taxonomy) {
		// does nothing except on taxonomies which have show_ui set to 1 (includes category and post_tags)
		if (!get_taxonomy($taxonomy)->show_ui)
			return;

		// avoids breaking translations when using inline edit
		if (isset($_POST['_inline_edit']))
			return;

		if (isset($_POST['term_lang_choice']) && $_POST['term_lang_choice'])
			$this->set_term_language($term_id, $_POST['term_lang_choice']);
		else
			$this->delete_term_language($term_id);

		if (!isset($_POST['term_tr_lang']))
			return;

		foreach ($_POST['term_tr_lang'] as $key=>$tr_id)
			$translations[$key] = (int) $tr_id;

		$this->save_translations('term', $term_id, $translations);
			
		// synchronize translations of this term in all posts

		// get all posts associated to this term 
		$posts = get_posts(array(
			'numberposts'=>-1,
			'post_type' => 'any',
			'post_status'=>'any',
			'fields' => 'ids',
			'tax_query' => array(array(
				'taxonomy'=> $taxonomy,
				'field' => 'id',
				'terms'=> array($term_id)+array_values($translations),
			))
		));

		// associate translated term to translated post
		foreach ($this->get_languages_list() as $language) {
			if ($translated_term = $this->get_term($term_id, $language)) {
				foreach ($posts as $post_id) {				
					if ($translated_post = $this->get_post($post_id, $language))
						wp_set_object_terms($translated_post, $translated_term, $taxonomy, true); 
				}
			}
		}
	}

	// called when a category or post tag is deleted
	function delete_term($term_id) {
		$this->delete_term_language($term_id);
		$this->delete_translation('term', $term_id);
	}

	// returns all terms in the $taxonomy in the $term_language which have no translation in the $translation_language
	function get_terms_not_translated($taxonomy, $term_language, $translation_language) {
		$new_terms = array();
		foreach (get_terms($taxonomy, 'hide_empty=0') as $term) {
			$lang = $this->get_term_language($term->term_id);
			if ($lang && $lang->name == $term_language->name && !$this->get_translation('term', $term->term_id, $translation_language))
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

			include(PLL_INC.'/term-translations.php');
		}
		die();
	}

	// modifies the theme location nav menu metabox
	// thanks to: http://wordpress.stackexchange.com/questions/2770/how-to-add-a-custom-metabox-to-the-menu-management-admin-screen
	function nav_menu_theme_locations() {
		// only if the theme supports nav menus and a nav menu exists
		if ( ! current_theme_supports( 'menus' ) || ! $metabox = &$GLOBALS['wp_meta_boxes']['nav-menus']['side']['default']['nav-menu-theme-locations'] )
			return;

		$metabox['callback'] = array(&$this,'nav_menu_language');
		$metabox['title'] = __('Theme locations and languages', 'polylang');
	}

	// displays a message to redirect to the languages options page
	function nav_menu_language() {
		echo '<p class="howto">' . sprintf (__('Please go to the %slanguages page%s to set theme locations and languages', 'polylang'),
			'<a href="' . esc_url(admin_url('options-general.php?page=mlang&tab=menus')) . '">', '</a>') . '</p>';
	}

	// modifies the widgets forms to add our language dropdwown list
	function in_widget_form($widget) {
		$widget_lang = get_option('polylang_widgets');

		printf(
			'<p><label for="%1$s">%2$s<select name="%1$s" id="%1$s" class="tags-input"><option value="0">%3$s</option>',
			esc_attr($widget->id.'_lang_choice'),
			__('The widget is displayed for:', 'polylang'),
			__('All languages', 'polylang')
		);
		foreach ($this->get_languages_list() as $language) {
			printf(
				"<option value='%s'%s>%s</option>\n",
				esc_attr($language->slug),
				isset($widget_lang[$widget->id]) && $language->slug == $widget_lang[$widget->id] ? ' selected="selected"' : '',
				esc_html($language->name)
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
		// get_current_user_id uses wp_get_current_user which may not be available the first time(s) get_locale is called
		if (function_exists('wp_get_current_user'))
			$loc = get_user_meta(get_current_user_id(), 'user_lang', 'true');
		return isset($loc) && $loc ? $loc : $locale;
	}

	// updates language user preference
	function personal_options_update($user_id) {
		update_user_meta($user_id, 'user_lang', $_POST['user_lang']);
	}

	// form for language user preference
	function personal_options($profileuser) {
		include(PLL_INC.'/personal-options.php');
	}

	// refresh rewrite rules if the 'page_on_front' option is modified
	function update_option($option) {
		global $wp_rewrite;
		if ($option == 'page_on_front')
			$wp_rewrite->flush_rules();
	}
}

?>

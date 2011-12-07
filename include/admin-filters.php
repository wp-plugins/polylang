<?php

// all modifications of the WordPress admin ui
class Polylang_Admin_Filters extends Polylang_Base {
	function __construct() {
		// additionnal filters and actions
		add_action('admin_init',  array(&$this, 'admin_init'));

		// setup js scripts andd css styles
		add_action('admin_print_scripts', array(&$this,'admin_js'));
		add_action('admin_print_styles', array(&$this,'admin_css'));

		// add the language column (as well as a filter by language) in 'All Posts' an 'All Pages' panels
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

		// adds actions related to languages when saving or deleting posts and pages
		add_action('save_post', array(&$this, 'save_post'));
		add_action('before_delete_post', array(&$this, 'delete_post'));

		// filters categories and post tags by language
		add_filter('terms_clauses', array(&$this, 'terms_clauses'), 10, 3);

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

			// adds actions related to languages when saving or deleting categories and post tags
			add_action('created_'.$tax, array(&$this, 'save_term'));
			add_action('edited_'.$tax, array(&$this, 'save_term'));
			add_action('delete_'.$tax, array(&$this, 'delete_term'));
		}
	}

	// setup js scripts
	function admin_js() {
		wp_enqueue_script('polylang_admin', WP_PLUGIN_URL .'/polylang/js/admin.js');
	}

	// setup css styles
	function admin_css() {
		wp_enqueue_style('polylang_admin', WP_PLUGIN_URL .'/polylang/css/admin.css');
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
			echo esc_html($lang->name);
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

		$x->send();
	}

	// saves translations for posts or terms
	// the underscore in '_lang' hides the post meta in the Custom Fields metabox in the Edit Post screen
	function save_translations($type, $id) {
		$lang = call_user_func(array(&$this, 'get_'.$type.'_language'), $id);
		if (!$lang)
			return;

		if (isset($_POST['tr_lang']) && is_array($_POST['tr_lang'])) {
			$tr = serialize(array_merge(array($lang->slug => $id), $_POST['tr_lang']));
			update_metadata($type, $id, '_translations', $tr);

			foreach($_POST['tr_lang'] as $key=>$p)
				update_metadata($type, $p, '_translations', $tr);
		}
	}

	// called when a post (or page) is saved, published or updated
	function save_post($post_id) {
		// avoids breaking translations when using inline or bulk edit
		if(isset($_POST['_inline_edit']) || isset($_GET['bulk_edit']))
			return;

		if ($id = wp_is_post_revision($post_id))
			$post_id = $id;

		if (isset($_POST['post_lang_choice']))
			wp_set_post_terms($post_id, $_POST['post_lang_choice'], 'language' );

		$this->save_translations('post', $post_id);
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
			if(!get_taxonomy($tax)->show_ui)
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

		$columns['language'] = __('Language', 'polylang');

		if (isset($end))
			$columns['posts'] = $end;

    return $columns;
	}

	// fills the language column in the 'Categories' or Post Tags table
	function term_column($empty, $column, $term_id) {
		if ($column == 'language' && $lang = $this->get_term_language($term_id))
			echo esc_html($lang->name);
	}

	// called when a category or post tag is created or edited
	function save_term($term_id) {
		// avoids breaking translations when using inline edit
		if(isset($_POST['_inline_edit']))
			return;

		if (isset($_POST['term_lang_choice']) && $_POST['term_lang_choice'])
			$this->update_term_language($term_id, $_POST['term_lang_choice']);
		else
			$this->delete_term_language($term_id);

		$this->save_translations('term', $term_id);
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

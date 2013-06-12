<?php

// all modifications of the WordPress admin ui
class Polylang_Admin_Filters extends Polylang_Admin_Base {
	private $pre_term_name; // used to store the term name before creating a slug if needed

	function __construct() {
		parent::__construct();

		// additionnal filters and actions
		add_action('wp_loaded', array(&$this, 'admin_init'), 5);// after Polylang_Base::add_post_types_taxonomie

		// refresh rewrite rules if the 'page_on_front' option is modified
		add_action('update_option_page_on_front', 'flush_rewrite_rules');

		// adds a 'settings' link in the plugins table
		add_filter('plugin_action_links_'.basename(POLYLANG_DIR).'/polylang.php', array(&$this, 'plugin_action_links'));

		// ugrades languages files after a core upgrade (timing is important)
		// FIXME private action ? is there a better way to do this ?
		add_action( '_core_updated_successfully', array(&$this, 'upgrade_languages'), 1); // since WP 3.3
	}

	// add these actions and filters here and not in the constructor to be sure that all taxonomies are registered
	function admin_init() {
		if (!$this->get_languages_list())
			return;

		// add the language and translations columns in 'All Posts', 'All Pages' and 'Media library' panels
		foreach ($this->post_types as $type) {
			// use the latest filter late as some plugins purely overwrite what's done by others :(
			// specific case for media
			add_filter('manage_'. ($type == 'attachment' ? 'upload' : 'edit-'. $type) .'_columns', array(&$this, 'add_post_column'), 100);
			add_action('manage_'. ($type == 'attachment' ? 'media' : $type .'_posts') .'_custom_column', array(&$this, 'post_column'), 10, 2);
		}

		// quick edit and bulk edit
		add_filter('quick_edit_custom_box', array(&$this, 'quick_edit_custom_box'), 10, 2);
		add_filter('bulk_edit_custom_box', array(&$this, 'quick_edit_custom_box'), 10, 2);

		// filters posts, pages and media by language
		add_filter('parse_query',array(&$this,'parse_query'));

		// adds the Languages box in the 'Edit Post' and 'Edit Page' panels
		add_action('add_meta_boxes', array(&$this, 'add_meta_boxes'));

		// ajax response for changing the language in the post metabox
		add_action('wp_ajax_post_lang_choice', array(&$this,'post_lang_choice'));

		add_action('wp_ajax_polylang-ajax-tag-search', array(&$this,'ajax_tag_search'));

		// filters the pages by language in the parent dropdown list in the page attributes metabox
		add_filter('page_attributes_dropdown_pages_args', array(&$this, 'page_attributes_dropdown_pages_args'), 10, 2);

		// adds actions and filters related to languages when creating, saving or deleting posts and pages
		add_filter('wp_insert_post_parent', array(&$this, 'wp_insert_post_parent'));
		add_action('dbx_post_advanced', array(&$this, 'dbx_post_advanced'));
		add_action('save_post', array(&$this, 'save_post'), 21, 2); // priority 21 to come after advanced custom fields (20) and before the event calendar which breaks everything after 25
		add_action('before_delete_post', array(&$this, 'delete_post'));

		if ($this->options['media_support']) {
			// adds the language field and translations tables in the 'Edit Media' panel
			add_filter('attachment_fields_to_edit', array(&$this, 'attachment_fields_to_edit'), 10, 2);

			// ajax response for changing the language in media form
			add_action('wp_ajax_media_lang_choice', array(&$this,'media_lang_choice'));

			// adds actions related to languages when creating, saving or deleting media
			add_action('add_attachment', array(&$this, 'add_attachment'));
			add_filter('attachment_fields_to_save', array(&$this, 'save_media'), 10, 2);
			add_action('delete_attachment', array(&$this, 'delete_post'));
			add_filter('wp_delete_file', array(&$this, 'wp_delete_file'));

			// creates a media translation
			if (isset($_GET['action'], $_GET['new_lang'], $_GET['from_media']) && $_GET['action'] == 'translate_media')
				$this->translate_media();
		}

		// filters categories and post tags by language
		add_filter('terms_clauses', array(&$this, 'terms_clauses'), 10, 3);

		foreach ($this->taxonomies as $tax) {
			// adds the language field in the 'Categories' and 'Post Tags' panels
			add_action($tax.'_add_form_fields', array(&$this, 'add_term_form'));

			// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels
			add_action($tax.'_edit_form_fields', array(&$this, 'edit_term_form'));

			// adds the language column in the 'Categories' and 'Post Tags' tables
			add_filter('manage_edit-'.$tax.'_columns', array(&$this, 'add_term_column'));
			add_action('manage_'.$tax.'_custom_column', array(&$this, 'term_column'), 10, 3);

			// adds action related to languages when deleting categories and post tags
			add_action('delete_'.$tax, array(&$this, 'delete_term'));
		}

		// adds actions related to languages when creating or saving categories and post tags
		add_filter('wp_dropdown_cats', array(&$this, 'wp_dropdown_cats'));
		add_action('create_term', array(&$this, 'save_term'), 10, 3);
		add_action('edit_term', array(&$this, 'save_term'), 10, 3);
		add_filter('pre_term_name', array(&$this, 'pre_term_name'));
		add_filter('pre_term_slug', array(&$this, 'pre_term_slug'), 10, 2);

		// ajax response for edit term form
		add_action('wp_ajax_term_lang_choice', array(&$this,'term_lang_choice'));

		// widgets languages filter
		add_action('in_widget_form', array(&$this, 'in_widget_form'));
		add_filter('widget_update_callback', array(&$this, 'widget_update_callback'), 10, 4);

		// language management for users
		add_action('personal_options_update', array(&$this, 'personal_options_update'));
		add_action('edit_user_profile_update', array(&$this, 'personal_options_update'));
		add_action('personal_options', array(&$this, 'personal_options'));

		//modifies posts and terms links when needed
		$this->add_post_term_link_filters();

		// filters comments by language
		add_filter('comments_clauses', array(&$this, 'comments_clauses'), 10, 2);
	}

	// adds languages and translations columns in posts, pages, media, categories and tags tables
	function add_column($columns, $before) {
		if ($n = array_search($before, array_keys($columns))) {
			$end = array_slice($columns, $n);
			$columns = array_slice($columns, 0, $n);
		}

		foreach ($this->get_languages_list() as $language)
			// don't add the column for the filtered language
			if ($language->slug != get_user_meta(get_current_user_id(), 'pll_filter_content', true))
				$columns['language_'.$language->slug] = ($flag = $this->get_flag($language)) ? $flag : esc_html($language->slug);

		return isset($end) ? array_merge($columns, $end) : $columns;
	}

	// returns the first language column in the posts, pages and media library tables
	function get_first_language_column() {
		foreach ($this->get_languages_list() as $language)
			if ($language->slug != get_user_meta(get_current_user_id(), 'pll_filter_content', true))
				$columns[] = 'language_'.$language->slug;

		return reset($columns);
	}

	// adds the language and translations columns (before the comments column) in the posts, pages and media library tables
	// test of $columns avoids to add columns (in screen options) in the edit media form which calls the filter too
	// see get_column_headers in wp-admin/screen.php
	// FIXME I have the same issue for terms but WP adds columns too
	function add_post_column($columns) {
		return $this->add_column($columns, 'comments');
	}

	// fills the language and translations columns in the posts, pages and media library tables
	// take care that when doing ajax inline edit, the post may not be updated in database yet
	// FIXME if inline edit breaks translations, the rows corresponding to these translations are not updated
	function post_column($column, $post_id) {
		$inline = defined('DOING_AJAX') && $_REQUEST['action'] == 'inline-save' ? 1 : 0;
		if (false === strpos($column, 'language_') || !($lang = $inline ? $this->get_language($_POST['post_lang_choice']) : $this->get_post_language($post_id)))
			return;

		$post_type = isset($GLOBALS['post_type']) ? $GLOBALS['post_type'] : $_POST['post_type']; // 2nd case for quick edit
		$language = $this->get_language(substr($column, 9));

		// hidden field containing the post language for quick edit
		if ($column == $this->get_first_language_column())
			printf('<div class="hidden" id="lang_%d">%s</div>', esc_attr($post_id), esc_html($lang->slug));


		// link to edit post (or a translation)
		if ($id = ($inline && $lang->slug != $_POST['old_lang']) ? ($language->slug == $lang->slug ? $post_id : 0) : $this->get_post($post_id, $language))
			printf('<a class="%1$s" title="%2$s" href="%3$s"></a>',
				$id == $post_id ? 'pll_icon_tick' : 'pll_icon_edit',
				esc_attr(get_post($id)->post_title),
				esc_url(get_edit_post_link($id, true ))
			);

		// link to add a new translation
		else
			printf('<a class="pll_icon_add" title="%1$s" href="%2$s"></a>',
				__('Add new translation', 'polylang'),
				esc_url(admin_url('manage_media_custom_column' == current_filter() ?
					'admin.php?action=translate_media&from_media=' . $post_id . '&new_lang=' . $language->slug :
					'post-new.php?post_type=' . $post_type . '&from_post=' . $post_id . '&new_lang=' . $language->slug
				))
			);
	}

	// quick edit & bulk edit
	function quick_edit_custom_box($column, $type) {
		if ($column == $this->get_first_language_column()) {
			$name = $type == 'edit-tags' ? 'inline_lang_choice' : 'post_lang_choice';

			$args = current_filter() == 'bulk_edit_custom_box' ?
				array('name' => $name, 'add_options' => array(array('value' => -1, 'text' => __('&mdash; No Change &mdash;')))) :
				array('name' => $name);

			printf(
				'<fieldset class="inline-edit-col-left">
					<div class="inline-edit-col">
						<input type="hidden" value="" name = "old_lang">' // a hidden field to pass the old language to ajax request
						.'<label for="%s" class="alignleft">
							<span class="title">%s</span>
							%s
						</label>
					</div>
				</fieldset>',
				$name,
				__('Language', 'polylang'),
				$this->dropdown_languages($args)
			);
		}
		return $column;
	}

	// filters posts, pages and media by language
	function parse_query($query) {
		$qvars = &$query->query_vars;

		// do not filter post types such as nav_menu_item
		if (isset($qvars['post_type']) && !in_array($qvars['post_type'], $this->post_types)) {
			unset ($qvars['lang']);
			return;
		}

		// filters the list of media by language when uploading from post
		if ($this->options['media_support'] && ($GLOBALS['pagenow'] == 'media-upload.php' || // WP < 3.5
			($GLOBALS['pagenow'] == 'admin-ajax.php' && isset($_REQUEST['action']) && $_REQUEST['action'] == 'query-attachments')) && // WP 3.5+
			isset($_REQUEST['post_id']) && $lang = $this->get_post_language($_REQUEST['post_id']))
			$query->set('lang', $lang->slug);

		if (isset($qvars['post_type']) && in_array($qvars['post_type'], $this->post_types) && !isset($qvars['lang']) && $lg = get_user_meta(get_current_user_id(), 'pll_filter_content', true))
			$qvars['lang'] = $lg;

		if ((isset($qvars['post_type']) && !in_array($qvars['post_type'], $this->post_types)) || (isset($qvars['lang']) && $qvars['lang'] == 'all'))
			unset ($qvars['lang']);
	}

	// adds the Language box in the 'Edit Post' and 'Edit Page' panels (as well as in custom post types panels)
	function add_meta_boxes($post_type) {
		if (in_array($post_type, $this->post_types))
			add_meta_box('ml_box', __('Languages','polylang'), array(&$this, 'post_language'), $post_type, 'side', 'high');

		// replace tag metabox by our own
		foreach (get_object_taxonomies($post_type) as $tax_name) {
			$taxonomy = get_taxonomy($tax_name);
			if ($taxonomy->show_ui && !is_taxonomy_hierarchical($tax_name)) {
				remove_meta_box('tagsdiv-' . $tax_name, null, 'side');
				add_meta_box('pll-tagsdiv-' . $tax_name, $taxonomy->labels->name, 'post_tags_meta_box', null, 'side', 'core', array('taxonomy' => $tax_name));
			}
		}
	}

	// the Languages metabox in the 'Edit Post' and 'Edit Page' panels
	function post_language() {
		global $post_ID;
		$post_id = $post_ID;
		$post_type = get_post_type($post_ID);

		$lang = ($lg = $this->get_post_language($post_ID)) ? $lg :
			(isset($_GET['new_lang']) ? $this->get_language($_GET['new_lang']) :
			$this->get_preferred_language());

		// NOTE: the class "tags-input" allows to include the field in the autosave $_POST (see autosave.js)
		printf('
			<p><em>%s</em></p>
			<p>%s<br /></p>
			<div id="post-translations" class="translations">',
			__('Language', 'polylang'),
			$this->dropdown_languages(array(
				'name'     => $post_type == 'attachment' ? sprintf('attachments[%d][language]', $post_ID) : 'post_lang_choice',
				'class'    => $post_type == 'attachment' ? 'media_lang_choice' : 'tags-input',
				'selected' => $lang ? $lang->slug : ''
			))
		);
		if ($lang)
			include(PLL_INC.'/'.($post_type == 'attachment' ? 'media' : 'post').'-translations.php');
		echo '</div>'."\n";
	}

	// ajax response for changing the language in the post metabox
	function post_lang_choice() {
		global $post_ID; // obliged to use the global variable for wp_popular_terms_checklist
		$post_ID = $_POST['post_id'];
		$post_type = get_post_type($post_ID);
		$lang = $this->get_language($_POST['lang']);

		$this->set_post_language($post_ID, $lang); // save language, useful to set the language when uploading media from post

		ob_start();
		if ($lang)
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

				$supplemental['dropdown'] = wp_dropdown_categories(array(
					'taxonomy'         => $taxonomy->name,
					'hide_empty'       => 0,
					'name'             => 'new'.$taxonomy->name.'_parent',
					'orderby'          => 'name',
					'hierarchical'     => 1,
					'show_option_none' => '&mdash; '.$taxonomy->labels->parent_item.' &mdash;',
					'echo'             => 0
				));

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

	// replaces ajax tag search of WP to filter tags by language
	function ajax_tag_search() {
		global $wpdb;

		if ( isset( $_GET['tax'] ) ) {
			$taxonomy = sanitize_key( $_GET['tax'] );
			$tax = get_taxonomy( $taxonomy );
			if ( ! $tax )
				die( '0' );
			if ( ! current_user_can( $tax->cap->assign_terms ) )
				die( '-1' );
		} else {
			die('0');
		}

		$s = stripslashes( $_GET['q'] );

		if ( false !== strpos( $s, ',' ) ) {
			$s = explode( ',', $s );
			$s = $s[count( $s ) - 1];
		}
		$s = trim( $s );
		if ( strlen( $s ) < 2 )
			die; // require 2 chars for matching

		$lang = $this->get_language($_GET['lang']);

		$results = $wpdb->get_col( $wpdb->prepare("
			SELECT t.name FROM $wpdb->term_taxonomy AS tt
			INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id
			INNER JOIN $wpdb->termmeta AS tm ON tm.term_id = t.term_id
			WHERE tt.taxonomy = %s AND t.name LIKE (%s) AND tm.meta_key = '_language' AND tm.meta_value = %d",
			$taxonomy, '%' . like_escape( $s ) . '%', $lang->term_id ) );

		echo join( $results, "\n" );
		die;
	}

	// filters the pages by language in the parent dropdown list in the page attributes metabox
	function page_attributes_dropdown_pages_args($dropdown_args, $post) {
		$lang = isset($_POST['lang']) ? $this->get_language($_POST['lang']) : $this->get_post_language($post->ID); // ajax or not ?
		if (!$lang)
			$lang = $this->get_preferred_language();

		$pages = implode(',', $this->exclude_pages($lang->term_id));
		$dropdown_args['exclude'] = isset($dropdown_args['exclude']) ? $dropdown_args['exclude'].','.$pages : $pages;
		return $dropdown_args;
	}

	// translate post parent if exists when using "Add new" (translation)
	function wp_insert_post_parent($post_parent) {
		return isset($_GET['from_post'], $_GET['new_lang']) && ($id = wp_get_post_parent_id($_GET['from_post'])) && ($parent = $this->get_translation('post', $id, $_GET['new_lang'])) ? $parent : $post_parent;
	}

	// copy post metas, menu order, comment and ping status when using "Add new" (translation)
	// the hook was probably not intended for that but did not find a better one
	function dbx_post_advanced() {
		if (isset($_GET['from_post'], $_GET['new_lang'])) {
			global $post;

			$this->copy_post_metas($_GET['from_post'], $post->ID, $_GET['new_lang']);

			$from_post = get_post($_GET['from_post']);
			foreach (array('menu_order', 'comment_status', 'ping_status') as $property)
				$post->$property = $from_post->$property;

			if (in_array('sticky_posts', $this->options['sync']) && is_sticky($_GET['from_post']))
				stick_post($post->ID);
		}
	}

	// copy or synchronize terms and metas
	function copy_post_metas($from, $to, $lang, $sync = false) {
		// copy or synchronize terms
		if (!$sync || in_array('taxonomies', $this->options['sync'])) {
			// FIXME quite a lot of query in foreach
			foreach ($this->taxonomies as $tax) {
				$newterms = array();
				$terms = get_the_terms($from, $tax);
				if (is_array($terms)) {
					foreach ($terms as $term) {
						if ($term_id = $this->get_translation('term', $term->term_id, $lang))
							$newterms[] = (int) $term_id; // cast is important otherwise we get 'numeric' tags
					}
				}

				// for some reasons, the user may have untranslated terms in the translation. don't forget them.
				if ($sync) {
					$tr_terms = get_the_terms($to, $tax);
					if (is_array($tr_terms)) {
						foreach ($tr_terms as $term) {
							if (!$this->get_translation('term', $term->term_id, $_POST['post_lang_choice']))
								$newterms[] = (int) $term->term_id;
						}
					}
				}

				if (!empty($newterms) || $sync)
					wp_set_object_terms($to, $newterms, $tax); // replace terms in translation
			}
		}

		// copy or synchronize post formats
		if (!$sync || in_array('post_format', $this->options['sync']))
			($format = get_post_format($from)) ? set_post_format($to, $format) : set_post_format($to, '');

		// copy or synchronize post metas and allow plugins to do the same
		$metas = get_post_custom($from);

		// get public meta keys (including from translated post in case we just deleted a custom field)
		if (!$sync || in_array('post_meta', $this->options['sync'])) {
			foreach ($keys = array_unique(array_merge(array_keys($metas), array_keys(get_post_custom($to)))) as $k => $meta_key)
				if (is_protected_meta($meta_key))
					unset ($keys[$k]);
		}

		// add page template and featured image
		foreach (array('_wp_page_template', '_thumbnail_id') as $meta)
			if (!$sync || in_array($meta, $this->options['sync']))
				$keys[] = $meta;

		$keys = array_unique(apply_filters('pll_copy_post_metas', empty($keys) ? array() : $keys, $sync));

		// and now copy / synchronize
		foreach ($keys as $key) {
			delete_post_meta($to, $key); // the synchronization process of multiple values custom fields is easier if we delete all metas first
			if (isset($metas[$key])) {
				foreach ($metas[$key] as $value) {
					// important: always maybe_unserialize value coming from get_post_custom. See codex.
					// thanks to goncalveshugo http://wordpress.org/support/topic/plugin-polylang-pll_copy_post_meta
					$value = maybe_unserialize($value);
					// special case for featured images which can be translated
					add_post_meta($to, $key, ($key == '_thumbnail_id' && $tr_value = $this->get_translation('post', $value, $lang)) ? $tr_value : $value);
				}
			}
		}
	}

	// called when a post (or page) is saved, published or updated
	function save_post($post_id, $post) {
		global $wpdb;

		// does nothing except on post types which are filterable
		if (!in_array($post->post_type, $this->post_types))
			return;

		// bulk edit does not modify the language
		if (isset($_GET['bulk_edit']) && $_REQUEST['post_lang_choice'] == -1)
			return;

		if ($id = wp_is_post_revision($post_id))
			$post_id = $id;

		// save language
		if (isset($_REQUEST['post_lang_choice'])) {
			if (($lang = $this->get_post_language($post_id)) && $lang->slug != $_REQUEST['post_lang_choice'])
				$this->delete_translation('post', $post_id); // in case the language is modified using inline edit
			$this->set_post_language($post_id, $_REQUEST['post_lang_choice']);
		}

		elseif (isset($_GET['new_lang']))
			$this->set_post_language($post_id, $_GET['new_lang']);

		elseif (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('post-quickpress-save', 'post-quickpress-publish')))
			$this->set_post_language($post_id, $this->get_preferred_language()); // default language for QuickPress

		elseif ($this->get_post_language($post_id))
			{} // avoids breaking the language if post is updated outside the edit post page (thanks to Gonçalo Peres)

		elseif (($parent_id = wp_get_post_parent_id($post_id)) && $parent_lang = $this->get_post_language($parent_id))
			$this->set_post_language($post_id, $parent_lang);

		else
			$this->set_post_language($post_id, $this->get_preferred_language());

		if (!isset($_POST['post_lang_choice']))
			return;

		// make sure we get save terms in the right language (especially tags with same name in different languages)
		if ($_POST['post_lang_choice']) {
			// FIXME quite a lot of query in foreach
			foreach ($this->taxonomies as $tax) {
				$terms = get_the_terms($post_id, $tax);
				if (is_array($terms)) {
					$newterms = array();
					foreach ($terms as $term) {
						if ($term_id = $this->get_term($term->term_id, $_POST['post_lang_choice']))
							$newterms[] = (int) $term_id; // cast is important otherwise we get 'numeric' tags
						elseif (!is_wp_error($term_info = wp_insert_term($term->name, $tax))) // create the term in the correct language
							$newterms[] = (int) $term_info['term_id'];
					}
					wp_set_object_terms($post_id, $newterms, $tax);
				}
			}
		}

		if (!isset($_POST['post_tr_lang'])) // just in case only one language has been created
			return;

		// save translations after checking the translated post is in the right language
		foreach ($_POST['post_tr_lang'] as $lang=>$tr_id)
			$translations[$lang] = ($tr_id && $this->get_post_language((int) $tr_id)->slug == $lang) ? (int) $tr_id : 0;

		$this->save_translations('post', $post_id, $translations);

		// prepare some synchronizations
		foreach (array('comment_status', 'ping_status', 'menu_order', 'post_date') as $property)
			if (in_array($property, $this->options['sync']))
				$postarr[$property] = $post->$property;

		if (in_array('post_date', $this->options['sync']))
			$post_arr['post_date_gmt'] = $post->post_date_gmt;

		// synchronise terms and metas in translations
		foreach ($translations as $lang => $tr_id) {
			if (!$tr_id)
				continue;

			// synchronize terms and metas
			$this->copy_post_metas($post_id, $tr_id, $lang, true);

			// sticky posts
			if (in_array('sticky_posts', $this->options['sync']))
				isset($_REQUEST['sticky']) ? stick_post($tr_id) : unstick_post($tr_id);

			// synchronize comment status, ping status, menu order...
			if (!empty($postarr))
				$wpdb->update($wpdb->posts, $postarr, array('ID' => $tr_id));

			// FIXME: optimize the 2 db update in 1
			// post parent
			// do not udpate the translation parent if the user set a parent with no translation
			if (in_array('post_parent', $this->options['sync'])) {
				$post_parent = ($parent_id = wp_get_post_parent_id($post_id)) ? $this->get_translation('post', $parent_id, $lang) : 0;
				if (!($parent_id && !$post_parent))
					$wpdb->update($wpdb->posts, array('post_parent'=> $post_parent), array( 'ID' => $tr_id ));
			}
		}
	}

	// called when a post, page or media is deleted
	// don't delete translations if this is a post revision thanks to AndyDeGroo who catched this bug
	// http://wordpress.org/support/topic/plugin-polylang-quick-edit-still-breaks-translation-linking-of-pages-in-072
	function delete_post($post_id) {
		if (!wp_is_post_revision($post_id))
			$this->delete_translation('post', $post_id);
	}

	// adds the language field and translations tables in the 'Edit Media' panel
	function attachment_fields_to_edit($fields, $post) {
		if ($GLOBALS['pagenow'] == 'post.php')
			return $fields; // don't add anything on edit media panel for WP 3.5+ since we have the metabox

		$post_id = $post->ID;
		$lang = $this->get_post_language($post_id);

		$fields['language'] = array(
			'label' => __('Language', 'polylang'),
			'input' => 'html',
			'html'  => $this->dropdown_languages(array(
				'name'     => sprintf('attachments[%d][language]', $post_id),
				'class'    => 'media_lang_choice',
				'selected' => $lang ? $lang->slug : ''
			))
		);

		// don't show translations except on edit media panel for WP < 3.5
		if ($GLOBALS['pagenow'] == 'media.php') {
			if ($lang) {
				ob_start();
				include PLL_INC . '/media-translations.php';
				$fields['translations'] = array(
					'label' => __('Translations', 'polylang'),
					'input' => 'html',
					'html'  => ob_get_contents(),
				);
				ob_end_clean();
			}
			else
				$fields['translations'] = array('tr' => '<tr class="translations"></tr>'); // to get a field for ajax
		}

		return $fields;
	}

	// ajax response for changing the language in media form
	function media_lang_choice() {
		preg_match('#([0-9]+)#', $_POST['post_id'], $matches);
		$post_id = $matches[1];
		$lang = $this->get_language($_POST['lang']);

		ob_start();
		if ($lang) {
			include(PLL_INC.'/media-translations.php');
			$data = ob_get_contents();

			// Special case for WP < 3.5, first add the html generated by WP if non AJAX, then our translation table
			if (version_compare($GLOBALS['wp_version'], '3.5', '<'))
				$data = sprintf('
					<th scope="row" class="label">
						<label for="attachments[%d][translations]">
							<span class="alignleft">%s</span><br class="clear" />
						</label>
					</th>
					<td class="field">
						%s
					</td>',
					$post_id, __('Translations', 'polylang'), $data
				);
		}
		$x = new WP_Ajax_Response(array('what' => 'translations', 'data' => $data));
		ob_end_clean();

		$x->send();
	}

	// creates a media translation
	function translate_media() {
		$post = get_post($_GET['from_media']);
		$post_id = $post->ID;

		// create a new attachment (translate attachment parent if exists)
		$post->ID = null; // will force the creation
		$post->post_parent = ($post->post_parent && $tr_parent = $this->get_translation('post', $post->post_parent, $_GET['new_lang'])) ? $tr_parent : 0;
		$tr_id = wp_insert_attachment($post);
		add_post_meta($tr_id, '_wp_attachment_metadata', get_post_meta($post_id, '_wp_attachment_metadata', true));
		add_post_meta($tr_id, '_wp_attached_file', get_post_meta($post_id, '_wp_attached_file', true));

		$translations = $this->get_translations('post', $post_id);
		if (!$translations && $lang = $this->get_post_language($post_id))
			$translations[$lang->slug] = $post_id;

		$translations[$_GET['new_lang']] = $tr_id;
		$this->save_translations('post', $tr_id, $translations);

		wp_redirect(admin_url(sprintf(
			version_compare($GLOBALS['wp_version'], '3.5', '<') ?
				'media.php?attachment_id=%d&action=edit' : // WP < 3.5
				'post.php?post=%d&action=edit', // WP 3.5+
			$tr_id)));

		exit;
	}

	// sets the language of a new attachment
	function add_attachment($post_id) {
		if (!empty($_GET['new_lang'])) // created as a translation from an existing attachment
			$lang = $_GET['new_lang'];
		else {
			$post = get_post($post_id);
			if (!empty($post->post_parent)) // upload in the "Add media" modal when editing a post
				$lang = $this->get_post_language($post->post_parent);
		}

		$this->set_post_language($post_id, isset($lang) ? $lang : $this->get_preferred_language());
	}

	// called when a media is saved
	function save_media($post, $attachment) {
		$this->set_post_language($post['ID'], $attachment['language']); // FIXME the language is no more automatically saved by WP since WP 3.5

		$this->delete_translation('post', $post['ID']);

		// save translations after checking the translated media is in the right language
		if (isset($_POST['media_tr_lang'])) {
			foreach ($_POST['media_tr_lang'] as $lang=>$tr_id)
				$translations[$lang] = $this->get_post_language((int) $tr_id)->slug == $lang && $tr_id != $post['ID'] ? (int) $tr_id : 0;

			$this->save_translations('post', $post['ID'], $translations);
		}

		return $post;
	}

	// prevents WP deleting files when there are still media using them
	// thanks to Bruno "Aesqe" Babic and its plugin file gallery in which I took all the ideas for this function
	function wp_delete_file($file) {
		global $wpdb;
		$uploadpath = wp_upload_dir();
		$ids = $wpdb->get_col($wpdb->prepare("
			SELECT post_id FROM $wpdb->postmeta
			WHERE meta_key = '_wp_attached_file' AND meta_value = '%s'",
			ltrim($file, $uploadpath['basedir'])
		));

		if (!empty($ids)) {
			// regenerate intermediate sizes if it's an image (since we could not prevent WP deleting them before)
			wp_update_attachment_metadata($ids[0], wp_generate_attachment_metadata($ids[0], $file));
			return ''; // prevent deleting the main file
		}

		return $file;
	}

	// filters categories and post tags by language when needed
	function terms_clauses($clauses, $taxonomies, $args) {
		// does nothing except on taxonomies which are filterable
		foreach ($taxonomies as $tax)
			if (!in_array($tax, $this->taxonomies))
				return $clauses;


		// if get_terms is queried with a 'lang' parameter
		if (!empty($args['lang']))
			return $this->_terms_clauses($clauses, $args['lang']);

		if (function_exists('get_current_screen'))
			$screen = get_current_screen(); // since WP 3.1, may not be available the first time(s) get_terms is called

		// does nothing in Languages and dasboard admin panels
		if (isset($screen) && in_array($screen->base, array('toplevel_page_mlang', 'dashboard')))
			return $clauses;

		// do not filter 'get_terms_not_translated'
		if (!empty($args['pll_get_terms_not_translated']))
			return $clauses;

		// The only ajax response I want to deal with is when changing the language in post metabox
		if (isset($_POST['action']) && !in_array($_POST['action'], array('post_lang_choice', 'term_lang_choice', 'get-tagcloud')))
			return $clauses;

		// I only want to filter the parent dropdown list when editing a term in a hierarchical taxonomy
		if (isset($_POST['action']) && $_POST['action'] == 'term_lang_choice' && !(isset($args['class']) || isset($args['unit'])))
			return $clauses;

		// ajax response for changing the language in the post metabox (or in the edit-tags panels)
		if (isset($_POST['lang']))
			$lang = $this->get_language($_POST['lang']);

		// the post is created with the 'add new' (translation) link
		elseif (!empty($_GET['new_lang']))
			$lang = $this->get_language($_GET['new_lang']);

		// the language filter selection has just changed
		// test $screen->base to avoid interference between the language filter and the post language selection and the category parent dropdown list
		elseif (!empty($_GET['lang']) && !in_array($screen->base, array('post', 'edit-tags'))) {
			if ($_GET['lang'] != 'all')
				$lang = $this->get_language($_GET['lang']);
			elseif ($screen->base == 'edit-tags' && isset($args['class']))
				$lang = $this->get_preferred_language(); // parent dropdown
		}

		// again the language filter
		elseif (($lg = get_user_meta(get_current_user_id(), 'pll_filter_content', true)) &&
			$screen->base != 'post' && !($screen->base == 'edit-tags' && isset($args['class']))) // don't apply to post edit and the category parent dropdown list
		 	$lang = $this->get_language($lg);

		elseif (isset($_GET['post']))
			$lang = $this->get_post_language($_GET['post']);

		// for the parent dropdown list in edit term
		elseif (isset($_GET['tag_ID']))
			$lang = $this->get_term_language($_GET['tag_ID']);

		// when a new category is created in the edit post panel
		elseif (isset($_POST['term_lang_choice']))
			$lang = $this->get_language($_POST['term_lang_choice']);

		// for a new post (or the parent dropdown list of a new term)
		elseif (isset($screen) && ($screen->base == 'post' || ($screen->base == 'edit-tags' && isset($args['class']))))
			$lang = $this->get_preferred_language();

		// adds our clauses to filter by current language
		return !empty($lang) ? $this->_terms_clauses($clauses, $lang) : $clauses;
	}

	// adds the language field in the 'Categories' and 'Post Tags' panels
	function add_term_form() {
		$taxonomy = $_GET['taxonomy'];
		$lang = isset($_GET['new_lang']) ? $this->get_language($_GET['new_lang']) : $this->get_preferred_language();

		printf('
			<div class="form-field">
				<label for="term_lang_choice">%s</label>
				%s
				<p>%s</p>
			</div>',
			__('Language', 'polylang'),
			$this->dropdown_languages(array('name' => 'term_lang_choice', 'value' => 'term_id', 'selected' => $lang ? $lang->term_id : '')),
			__('Sets the language', 'polylang')
		);

		// adds translation fields
		echo '<div id="term-translations" class="form-field">';
		if ($lang)
			include(PLL_INC.'/term-translations.php');
		echo '</div>'."\n";
	}

	// adds the language field and translations tables in the 'Edit Category' and 'Edit Tag' panels
	function edit_term_form($tag) {
		$term_id = $tag->term_id;
		$lang = $this->get_term_language($term_id);
		$taxonomy = $tag->taxonomy;

		printf('
			<tr class="form-field">
				<th scope="row">
					<label for="term_lang_choice">%s</label>
				</th>
				<td>
					%s<br />
					<span class="description">%s</span>
				</td>
			</tr>',
			__('Language', 'polylang'),
			$this->dropdown_languages(array('name' => 'term_lang_choice', 'value' => 'term_id', 'selected' => $lang ? $lang->term_id : '')),
			__('Sets the language', 'polylang')
		);

		echo '<tr id="term-translations" class="form-field">';
		if ($lang)
			include(PLL_INC.'/term-translations.php');
		echo '</tr>'."\n";
	}

	// adds the language column (before the posts column) in the 'Categories' or 'Post Tags' table
	function add_term_column($columns) {
		return $this->add_column($columns, 'posts');
	}

	// fills the language column in the 'Categories' or 'Post Tags' table
	// FIXME if inline edit breaks translations, the rows corresponding to these translations are not updated
	function term_column($empty, $column, $term_id) {
		$inline = defined('DOING_AJAX') && $_REQUEST['action'] == 'inline-save-tax' ? 1 : 0;
		if (false === strpos($column, 'language_') || !($lang = $inline ? $this->get_language($_POST['inline_lang_choice']) : $this->get_term_language($term_id)))
			return;

		$post_type = isset($GLOBALS['post_type']) ? $GLOBALS['post_type'] : $_POST['post_type']; // 2nd case for quick edit
		$taxonomy = isset($GLOBALS['taxonomy']) ? $GLOBALS['taxonomy'] : $_POST['taxonomy'];
		$language = $this->get_language(substr($column, 9));

		if ($column == $this->get_first_language_column())
			printf('<div class="hidden" id="lang_%d">%s</div>', esc_attr($term_id), esc_html($lang->slug));

		// link to edit term (or a translation)
		if ($id = ($inline && $lang->slug != $_POST['old_lang']) ? ($language->slug == $lang->slug ? $term_id : 0) : $this->get_term($term_id, $language))
			printf('<a class="%1$s" title="%2$s" href="%3$s"></a>',
				$id == $term_id ? 'pll_icon_tick' : 'pll_icon_edit',
				esc_attr(get_term($id, $taxonomy)->name),
				esc_url(get_edit_term_link($id, $taxonomy, $post_type))
			);

		// link to add a new translation
		else
			printf('<a class="pll_icon_add" title="%1$s" href="%2$s"></a>',
				__('Add new translation', 'polylang'),
				esc_url(admin_url(sprintf('edit-tags.php?taxonomy=%1$s&from_tag=%2$d&new_lang=%3$s', $taxonomy, $term_id, $language->slug)))
			);
	}

	// translate term parent if exists when using "Add new" (translation)
	function wp_dropdown_cats($output) {
		if (isset($_GET['taxonomy'], $_GET['from_tag'], $_GET['new_lang']) && $id = get_term($_GET['from_tag'], $_GET['taxonomy'])->parent) {
			if ($parent = $this->get_translation('term', $id, $_GET['new_lang']))
				return str_replace('"'.$parent.'"', '"'.$parent.'" selected="selected"', $output);
		}
		return $output;
	}

	// called when a category or post tag is created or edited
	function save_term($term_id, $tt_id, $taxonomy) {
		// does nothing except on taxonomies which are filterable
		if (!in_array($taxonomy, $this->taxonomies))
			return;

		// save language
		if (isset($_POST['term_lang_choice']))
			$this->set_term_language($term_id, $_POST['term_lang_choice']);
		if (isset($_POST['inline_lang_choice'])) {
			// don't use term_lang_choice for quick edit to avoid conflict with the "add term" form
			if ($this->get_term_language($term_id)->slug != $_POST['inline_lang_choice'])
				$this->delete_translation('term', $term_id);
			$this->set_term_language($term_id, $_POST['inline_lang_choice']);
		}
		elseif (isset($_POST['post_lang_choice']))
			$this->set_term_language($term_id, $_POST['post_lang_choice']);

		elseif ($this->get_term_language($term_id))
			{} // avoids breaking the language if the term is updated outside the edit post or edit tag pages

		// sets language from term parent if exists thanks to Scott Kingsley Clark
		elseif (($term = get_term($term_id, $taxonomy)) && !empty($term->parent) && $parent_lang = $this->get_term_language($term->parent))
			$this->set_term_language($term_id, $parent_lang);

		else
			$this->set_term_language($term_id, $this->get_preferred_language());

		if (!isset($_POST['term_tr_lang']))
			return;

		// save translations after checking the translated term is in the right language (as well as cast id to int)
		foreach ($_POST['term_tr_lang'] as $lang=>$tr_id) {
			$tr_lang = $this->get_term_language((int) $tr_id);
			$translations[$lang] = $tr_lang && $tr_lang->slug == $lang ? (int) $tr_id : 0;
		}

		$this->save_translations('term', $term_id, $translations);

		// synchronize translations of this term in all posts

		// STOP synchronisation if unwanted
		if (!in_array('taxonomies', $this->options['sync']))
			return;

		// get all posts associated to this term
		$posts = get_posts(array(
			'numberposts' => -1,
			'nopaging'    => true,
			'post_type'   => 'any',
			'post_status' => 'any',
			'fields'      => 'ids',
			'tax_query'   => array(array(
				'taxonomy' => $taxonomy,
				'field'    => 'id',
				'terms'    => array_merge(array($term_id), array_values($translations)),
			))
		));

		// associate translated term to translated post
		// FIXME quite a lot of query in foreach
		foreach ($this->get_languages_list() as $language) {
			if ($translated_term = $this->get_term($term_id, $language)) {
				foreach ($posts as $post_id) {
					if ($translated_post = $this->get_post($post_id, $language))
						wp_set_object_terms($translated_post, $translated_term, $taxonomy, true);
				}
			}
		}

		// synchronize parent in translations
		// calling clean_term_cache *after* this is mandatory otherwise the $taxonomy_children option is not correctly updated
		// but clean_term_cache can be called (efficiently) only one time due to static array which prevents to update the option more than once
		// this is the reason to use the edit_term filter and not edited_term
		// take care that $_POST contains the only valid values for the current term
		foreach ($_POST['term_tr_lang'] as $lang=>$tr_id) {
			if (!$tr_id)
				continue;

			if (isset($_POST['parent']) && $_POST['parent'] != -1) // since WP 3.1
				$term_parent = $this->get_translation('term', $_POST['parent'], $lang);

			global $wpdb;
			$wpdb->update($wpdb->term_taxonomy,
				array('parent'=> isset($term_parent) ? $term_parent : 0),
				array('term_taxonomy_id' => get_term($tr_id, $taxonomy)->term_taxonomy_id));
		}
	}

	// stores the term name for use in pre_term_slug
	function pre_term_name($name) {
		return $this->pre_term_name = $name;
	}

	// creates the term slug in case the term already exists in another language
	function pre_term_slug($slug, $taxonomy) {
		$name = sanitize_title($this->pre_term_name);

		// if the new term has the same name as a language, we *need* to differentiate the term
		// see http://core.trac.wordpress.org/ticket/23199
		if (term_exists($name, 'language') && !term_exists($name, $taxonomy) && (!$slug || $slug == $name))
			$slug = $name.'-'.$taxonomy; // a convenient slug which may be modified later by the user

		return !$slug && in_array($taxonomy, $this->taxonomies) && term_exists($name, $taxonomy) ?
			$name.'-'.$this->get_language($_POST['term_lang_choice'])->slug : $slug;
	}

	// called when a category or post tag is deleted
	function delete_term($term_id) {
		$this->delete_term_language($term_id);
		$this->delete_translation('term', $term_id);
	}

	// returns all terms in the $taxonomy in the $term_language which have no translation in the $translation_language
	function get_terms_not_translated($taxonomy, $term_language, $translation_language) {
		$new_terms = array();
		// it is more efficient to use one common query for all languages as son as there are more than 2
		// pll_get_terms_not_translated arg to identify this query in terms_clauses filter
		foreach (get_terms($taxonomy, 'hide_empty=0&pll_get_terms_not_translated=1') as $term) {
			$lang = $this->get_term_language($term->term_id);
			if ($lang && $lang->name == $term_language->name && !$this->get_translation('term', $term->term_id, $translation_language))
				$new_terms[] = $term;
		}
		return $new_terms;
	}

	// ajax response for edit term form
	function term_lang_choice() {
		$lang = $this->get_language($_POST['lang']);
		$term_id = isset($_POST['term_id']) ? $_POST['term_id'] : null;
		$taxonomy = $_POST['taxonomy'];

		ob_start();
		if ($lang)
			include(PLL_INC.'/term-translations.php');
		$x = new WP_Ajax_Response(array('what' => 'translations', 'data' => ob_get_contents()));
		ob_end_clean();

		// parent dropdown list (only for hierarchical taxonomies)
		// $args copied from edit_tags.php except echo
		if (is_taxonomy_hierarchical($taxonomy)) {
			$args = array(
				'hide_empty' => 0,
				'hide_if_empty' => false,
				'taxonomy' => $taxonomy,
				'name' => 'parent',
				'orderby' => 'name',
				'hierarchical' => true,
				'show_option_none' => __('None'),
				'echo' => 0,
			);
			$x->Add(array('what' => 'parent', 'data' => wp_dropdown_categories($args)));
		}

		// tag cloud
		// tests copied from edit_tags.php
		else {
			$tax = get_taxonomy($taxonomy);
		 	if (!is_null($tax->labels->popular_items)) {
				$args = array('taxonomy' => $taxonomy, 'echo' => false);
				if (current_user_can($tax->cap->edit_terms))
					$args = array_merge($args, array('link' => 'edit'));

				if ($tag_cloud = wp_tag_cloud($args))
					$x->Add(array('what' => 'tag_cloud', 'data' => '<h3>'.$tax->labels->popular_items.'</h3>'.$tag_cloud));
			}
		}

		$x->send();
	}

	// modifies the widgets forms to add our language dropdwown list
	function in_widget_form($widget) {
		printf('<p><label for="%1$s">%2$s%3$s</label></p>',
			esc_attr( $widget->id.'_lang_choice'),
			__('The widget is displayed for:', 'polylang'),
			$this->dropdown_languages(array(
				'name'        => $widget->id.'_lang_choice',
				'class'       => 'tags-input',
				'add_options' => array(array('value' => 0, 'text' => __('All languages', 'polylang'))),
				'selected'    => empty($this->options['widgets'][$widget->id]) ? '' : $this->options['widgets'][$widget->id]
			))
		);
	}

	// called when widget options are saved
	function widget_update_callback($instance, $new_instance, $old_instance, $widget) {
		$this->options['widgets'][$widget->id] = $_POST[$widget->id.'_lang_choice'];
		update_option('polylang', $this->options);
		return $instance;
	}

	// updates language user preference
	function personal_options_update($user_id) {
		update_user_meta($user_id, 'user_lang', $_POST['user_lang']); // admin language
		foreach ($this->get_languages_list() as $lang)
			update_user_meta($user_id, 'description_'.$lang->slug, $_POST['description_'.$lang->slug]); // biography translations
	}

	// form for language user preference
	function personal_options($profileuser) {
		printf('
			<tr>
				<th><label for="user_lang">%s</label></th>
				<td>%s</td>
			</tr>',
			__('Admin language', 'polylang'),
			$this->dropdown_languages(array(
				'name'        => 'user_lang',
				'value'       => 'description',
				'selected'    => get_user_meta($profileuser->ID, 'user_lang', true),
				'add_options' => array(array('value' => 0, 'text' => __('Wordpress default', 'polylang')))
			))
		);

		// hidden informations to modify the biography form with js
		foreach ($this->get_languages_list() as $lang)
			printf('<input type="hidden" class="biography" name="%s-%s" value="%s" />',
				esc_attr($lang->slug),
				esc_attr($lang->name),
				esc_attr(get_user_meta($profileuser->ID, 'description_'.$lang->slug, true))
			);
	}

	// filters comments by language
	function comments_clauses($clauses, $query) {
		if (!empty($query->query_vars['lang']))
			$lang = $query->query_vars['lang'];

		elseif (!empty($_GET['lang']) && $_GET['lang'] != 'all')
			$lang = $this->get_language($_GET['lang']);

		elseif ($lg = get_user_meta(get_current_user_id(), 'pll_filter_content', true))
		 	$lang = $this->get_language($lg);

		return empty($lang) ? $clauses : $this->_comments_clauses($clauses, $lang);
	}

	// adds a 'settings' link in the plugins table
	function plugin_action_links($links) {
		array_unshift($links, '<a href="admin.php?page=mlang">' . __('Settings', 'polylang') . '</a>');
		return $links;
	}

	// ugrades languages files after a core upgrade
	function upgrade_languages($version) {
		apply_filters('update_feedback', __('Upgrading language files&#8230;', 'polylang'));
		foreach ($this->get_languages_list() as $language)
			if ($language->description != $_POST['locale']) // do not (re)update the language files of a localized WordPress
				$this->download_mo($language->description, $version);
	}

	// returns either the user preferred language or the default language
	function get_preferred_language() {
		$default_language = $this->get_language(($lg = get_user_meta(get_current_user_id(), 'pll_filter_content', true)) ? $lg : $this->options['default_lang']);
		return apply_filters('pll_admin_preferred_language', $default_language);
	}

} // class Polylang_Admin_Filters

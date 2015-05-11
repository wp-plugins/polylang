<?php

/*
 * manages filters and actions related to posts on admin side
 *
 * @since 1.2
 */
class PLL_Admin_Filters_Post extends PLL_Admin_Filters_Post_Base {
	public $options, $curlang;

	/*
	 * constructor: setups filters and actions
	 *
	 * @since 1.2
	 *
	 * @param object $polylang
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);
		$this->options = &$polylang->options;
		$this->curlang = &$polylang->curlang;

		add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));

		// filters posts, pages and media by language
		add_action('parse_query',array(&$this,'parse_query'));

		// adds the Languages box in the 'Edit Post' and 'Edit Page' panels
		add_action('add_meta_boxes', array(&$this, 'add_meta_boxes'));

		// ajax response for changing the language in the post metabox
		add_action('wp_ajax_post_lang_choice', array(&$this,'post_lang_choice'));
		add_action('wp_ajax_pll_posts_not_translated', array(&$this,'ajax_posts_not_translated'));

		// adds actions and filters related to languages when creating, saving or deleting posts and pages
		add_action('save_post', array(&$this, 'save_post'), 21, 3); // priority 21 to come after advanced custom fields (20) and before the event calendar which breaks everything after 25
		add_action('before_delete_post', array(&$this, 'delete_post'));
		if ($this->options['media_support'])
			add_action('delete_attachment', array(&$this, 'delete_post')); // action shared with media

		// filters the pages by language in the parent dropdown list in the page attributes metabox
		add_filter('page_attributes_dropdown_pages_args', array(&$this, 'page_attributes_dropdown_pages_args'), 10, 2);
	}

	/*
	 * outputs a javascript list of terms ordered by language and hierarchical taxonomies
	 * to filter the category checklist per post language in quick edit
	 * outputs a javascript list of pages ordered by language
	 * to filter the parent dropdown per post language in quick edit
	 *
	 * @since 1.7
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

		//hierarchical taxonomies
		if ('edit' == $screen->base && $taxonomies = get_object_taxonomies($screen->post_type, 'object')) {
			// get translated hierarchical taxonomies
			foreach ($taxonomies as $taxonomy) {
				if ( $taxonomy->hierarchical && $this->model->is_translated_taxonomy($taxonomy->name))
					$hierarchical_taxonomies[] = $taxonomy->name;
			}

			if (!empty($hierarchical_taxonomies)) {
				$terms = get_terms($hierarchical_taxonomies, array( 'get' => 'all' ));

				foreach($terms as $term) {
					if ($lang = $this->model->get_term_language($term->term_id))
						$term_languages[$lang->slug][$term->taxonomy][] = $term->term_id;
				}

				// send all these data to javascript
				if (!empty($term_languages)) {
					wp_localize_script('pll_post', 'pll_term_languages', $term_languages);
				}
			}
		}
		
		// hierarchical post types
		if ('edit' == $screen->base && is_post_type_hierarchical($screen->post_type)) {
			$pages = get_pages();

			foreach($pages as $page) {
				if ($lang = $this->model->get_post_language($page->ID))
					$page_languages[$lang->slug][] = $page->ID;
			}

			// send all these data to javascript
			if (!empty($page_languages)) {
				wp_localize_script('pll_post', 'pll_page_languages', $page_languages);
			}
		}
	}

	/*
	 * filters posts, pages and media by language
	 *
	 * @since 0.1
	 *
	 * @param object $query a WP_Query object
	 */
	public function parse_query($query) {
		$qvars = &$query->query_vars;

		// do not filter post types such as nav_menu_item
		if (isset($qvars['post_type']) && !$this->model->is_translated_post_type($qvars['post_type'])) {
			unset ($qvars['lang']);
			return;
		}

		if (isset($qvars['post_type']) && !isset($qvars['lang'])) {
			// filters the list of media (or wp-links) by language when uploading from post
			if (isset($_REQUEST['pll_post_id']) && $lang = $this->model->get_post_language((int) $_REQUEST['pll_post_id']))
				$query->set('lang', $lang->slug);

			elseif (!empty($this->curlang))
				$qvars['lang'] = $this->curlang->slug;
		}

		if (isset($qvars['lang']) && 'all' === $qvars['lang'])
			unset ($qvars['lang']);
	}

	/*
	 * adds the Language box in the 'Edit Post' and 'Edit Page' panels (as well as in custom post types panels)
	 *
	 * @since 0.1
	 *
	 * @param string $post_type
	 */
	public function add_meta_boxes($post_type) {
		if ($this->model->is_translated_post_type($post_type))
			add_meta_box('ml_box', __('Languages','polylang'), array(&$this, 'post_language'), $post_type, 'side', 'high');
	}

	/*
	 * displays the Languages metabox in the 'Edit Post' and 'Edit Page' panels
	 *
	 * @since 0.1
	 */
	public function post_language() {
		global $post_ID;
		$post_id = $post_ID;
		$post_type = get_post_type($post_ID);

		$lang = ($lg = $this->model->get_post_language($post_ID)) ? $lg :
			(isset($_GET['new_lang']) ? $this->model->get_language($_GET['new_lang']) :
			$this->pref_lang);

		$dropdown = new PLL_Walker_Dropdown();

		wp_nonce_field('pll_language', '_pll_nonce');

		// NOTE: the class "tags-input" allows to include the field in the autosave $_POST (see autosave.js)
		printf('
			<p><strong>%s</strong></p>
			<div id="select-%s-language">%s</div>
			<div id="post-translations" class="translations">',
			__('Language', 'polylang'),
			$post_type == 'attachment' ? 'media' : 'post',
			$dropdown->walk($this->model->get_languages_list(), array(
				'name'     => $post_type == 'attachment' ? sprintf('attachments[%d][language]', $post_ID) : 'post_lang_choice',
				'class'    => $post_type == 'attachment' ? 'media_lang_choice' : 'tags-input',
				'selected' => $lang ? $lang->slug : '',
				'flag'     => true
			))
		);
		if ($lang)
			include(PLL_ADMIN_INC.'/view-translations-'.($post_type == 'attachment' ? 'media' : 'post').'.php');
		echo '</div>'."\n";
	}

	/*
	 * ajax response for changing the language in the post metabox
	 *
	 * @since 0.2
	 */
	public function post_lang_choice() {
		check_ajax_referer('pll_language', '_pll_nonce');

		global $post_ID; // obliged to use the global variable for wp_popular_terms_checklist
		$post_ID = (int) $_POST['post_id'];
		$post_type = get_post_type($post_ID);
		$lang = $this->model->get_language($_POST['lang']);

		$post_type_object = get_post_type_object($post_type);
		if (!current_user_can($post_type_object->cap->edit_post, $post_ID))
			wp_die(-1);

		$this->model->set_post_language($post_ID, $lang); // save language, useful to set the language when uploading media from post

		ob_start();
		if ($lang)
			include(PLL_ADMIN_INC.'/view-translations-post.php');
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
		if (in_array($post_type, get_post_types(array('hierarchical' => true)))) {
			$post = get_post($post_ID);

			// args and filter from 'page_attributes_meta_box' in wp-admin/includes/meta-boxes.php of WP 4.2.1
			$dropdown_args = array(
				'post_type'        => $post->post_type,
				'exclude_tree'     => $post->ID,
				'selected'         => $post->post_parent,
				'name'             => 'parent_id',
				'show_option_none' => __('(no parent)'),
				'sort_column'      => 'menu_order, post_title',
				'echo'             => 0,
			);
			$dropdown_args = apply_filters( 'page_attributes_dropdown_pages_args', $dropdown_args, $post ); // since WP 3.3
			
			$x->Add(array('what' => 'pages', 'data' => wp_dropdown_pages( $dropdown_args )));
		}

		// flag
		$x->Add(array('what' => 'flag', 'data' => empty($lang->flag) ? esc_html($lang->slug) : $lang->flag));

		$x->send();
	}

	/*
	 * ajax response for input in translation autocomplete input box
	 *
	 * @since 1.5
	 */
	public function ajax_posts_not_translated() {
		check_ajax_referer('pll_language', '_pll_nonce');

		if (!post_type_exists($_REQUEST['post_type']))
			die(0);
		
		$post_language = $this->model->get_language($_REQUEST['post_language']);
		$translation_language = $this->model->get_language($_REQUEST['translation_language']);
		
		// don't order by title: see https://wordpress.org/support/topic/find-translated-post-when-10-is-not-enough
		$args = array(
			's'                => wp_unslash($_REQUEST['term']),
			'suppress_filters' => 0, // to make the post_fields filter work
			'lang'             => 0, // avoid admin language filter
			'numberposts'      => 20, // limit to 20 posts
			'post_status'      => 'any',
			'post_type'        => $_REQUEST['post_type'],
			'tax_query'        => array(array(
				'taxonomy' => 'language',
				'field'    => 'term_taxonomy_id', // WP 3.5+
				'terms'    => $translation_language->term_taxonomy_id
			))
		);
		
		// allow plugins to change args help fixing edge cases: see same topic as above
		$args = apply_filters('pll_ajax_posts_not_translated_args', $args);
		$posts = get_posts($args);

		$return = array();

		foreach ($posts as $key => $post) {
			if (!$this->model->get_translation('post', $post->ID, $post_language))
				$return[] = array('id' => $post->ID, 'value' => $post->post_title, 'link' => $this->edit_translation_link($post->ID));
		}

		// add current translation in list
		if ($post_id = $this->model->get_translation('post', (int) $_REQUEST['pll_post_id'], $translation_language)) {
			$post = get_post($post_id);
			array_unshift($return, array(
				'id' => $post_id,
				'value' => $post->post_title,
				'link' => $this->edit_translation_link($post_id)
			));
		}

		wp_die(json_encode($return));
	}

	/*
	 * saves language
	 * checks the terms saved are in the right language
	 *
	 * @since 1.5
	 *
	 * @param int $post_id
	 * @param array $post
	 */
	protected function save_language($post_id, $post) {
		// security checks are necessary to accept language modifications
		// as 'wp_insert_post' can be called from outside WP admin

		// edit post
		if (isset($_REQUEST['post_lang_choice'])) {
			check_admin_referer('pll_language', '_pll_nonce');
			$this->model->set_post_language($post_id, $lang = $this->model->get_language($_REQUEST['post_lang_choice']));
		}

		// quick edit and bulk edit
		elseif (isset($_REQUEST['inline_lang_choice'])) {
			// bulk edit does not modify the language
			if (isset($_REQUEST['bulk_edit']) && $_REQUEST['inline_lang_choice'] == -1) {
				check_admin_referer('bulk-posts');
				$lang = $this->model->get_post_language($post_id); // get the post language for later use when saving terms 
			}
			// a language is set in the language dropdown
			else {
				isset($_REQUEST['bulk_edit']) ? check_admin_referer('bulk-posts') : check_admin_referer('inlineeditnonce', '_inline_edit');

				$old_lang = $this->model->get_post_language($post_id); // stores the old  language
				$this->model->set_post_language($post_id, $lang = $this->model->get_language($_REQUEST['inline_lang_choice'])); // set new language

				// checks if the new language already exists in the translation group
				if ($old_lang && $old_lang->slug != $lang->slug) {
					$translations = $this->model->get_translations('post', $post_id);

					// if yes, separate this post from the translation group
					if (array_key_exists($lang->slug, $translations)) {
						$this->model->delete_translation('post', $post_id);
					}

					elseif (array_key_exists($old_lang->slug, $translations)) {
						unset($translations[$old_lang->slug]);
						$this->model->save_translations('post', $post_id, $translations);
					}
				}
			}
		}

		// quick press
		// 'post-quickpress-save', 'post-quickpress-publish' = backward compatibility WP < 3.8
		elseif (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('post-quickpress-save', 'post-quickpress-publish', 'post-quickdraft-save'))) {
			check_admin_referer('add-' . $post->post_type);
			$this->model->set_post_language($post_id, $lang = $this->pref_lang); // default language for Quick draft
		}

		else
			$this->set_default_language($post_id);

		// make sure we get save terms in the right language (especially tags with same name in different languages)
		if (!empty($lang)) {

			// FIXME quite a lot of queries in foreach
			foreach ($this->model->get_translated_taxonomies() as $tax) {
				$terms = get_the_terms($post_id, $tax);

				if (is_array($terms)) {
					$newterms = array();
					foreach ($terms as $term) {
						// check if the term is in the correct language or if a translation exist (mainly for default category)
						if ($newterm = $this->model->get_term($term->term_id, $lang))
							$newterms[] = (int) $newterm;

						// or choose the correct language for tags (initially defined by name)
						elseif ($newterm = $this->model->term_exists($term->name, $tax, $term->parent, $lang))
							$newterms[] = (int) $newterm; // cast is important otherwise we get 'numeric' tags

						// or create the term in the correct language
						elseif (!is_wp_error($term_info = wp_insert_term($term->name, $tax)))
							$newterms[] = (int) $term_info['term_id'];
					}

					wp_set_object_terms($post_id, $newterms, $tax);
				}
			}
		}
	}

	/*
	 * called when a post (or page) is saved, published or updated
	 * saves languages and translations
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 * @param object $post
	 * @param bool $update whether it is an update or not
	 */
	public function save_post($post_id, $post, $update) {
		// does nothing except on post types which are filterable
		if (!$this->model->is_translated_post_type($post->post_type))
			return;

		if ($id = wp_is_post_revision($post_id))
			$post_id = $id;

		// capability check
		// as 'wp_insert_post' can be called from outside WP admin
		$post_type_object = get_post_type_object($post->post_type);
		if (($update && current_user_can($post_type_object->cap->edit_post, $post_id)) || (!$update && current_user_can($post_type_object->cap->create_posts))) {
			$this->save_language($post_id, $post);

			if (isset($_POST['post_tr_lang']))
				$translations = $this->save_translations($post_id, $_POST['post_tr_lang']);

			do_action('pll_save_post', $post_id, $post, empty($translations) ? $this->model->get_translations('post', $post_id) : $translations);
		}

		// attempts to set a default language even if no capability
		else
			$this->set_default_language($post_id);
	}

	/*
	 * called when a post, page or media is deleted
	 * don't delete translations if this is a post revision thanks to AndyDeGroo who catched this bug
	 * http://wordpress.org/support/topic/plugin-polylang-quick-edit-still-breaks-translation-linking-of-pages-in-072
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 */
	public function delete_post($post_id) {
		if (!wp_is_post_revision($post_id))
			$this->model->delete_translation('post', $post_id);
	}

	/*
	 * filters the pages by language in the parent dropdown list in the page attributes metabox
	 *
	 * @since 0.6
	 *
	 * @param array $dropdown_args arguments passed to wp_dropdown_pages
	 * @param object $post
	 * @return array modified arguments
	 */
	public function page_attributes_dropdown_pages_args($dropdown_args, $post) {
		$dropdown_args['lang'] = isset($_POST['lang']) ? $this->model->get_language($_POST['lang']) : $this->model->get_post_language($post->ID); // ajax or not ?
		if (!$dropdown_args['lang'])
			$dropdown_args['lang'] = $this->pref_lang;

		return $dropdown_args;
	}

	/*
	 * returns html markup for a translation link
	 *
	 * @since 1.4
	 *
	 * @param int $post_id translation post id
	 * @return string
	 */
	public function edit_translation_link($post_id) {
		return sprintf(
			'<a href="%1$s" class="pll_icon_edit" title="%2$s"></a>',
			esc_url(get_edit_post_link($post_id)),
			__('Edit', 'polylang')
		);
	}
}

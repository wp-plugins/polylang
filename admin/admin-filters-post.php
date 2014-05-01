<?php

/*
 * manages filters and actions related to posts on admin side
 *
 * @since 1.2
 */
class PLL_Admin_Filters_Post {
	public $links, $model, $options, $curlang, $pref_lang;

	/*
	 * constructor: setups filters and actions
	 *
	 * @since 1.2
	 *
	 * @param object $model instance of PLL_Model
	 * @param object $pref_lang language chosen in admin filter or default language
	 */
	public function __construct(&$links, &$curlang, $pref_lang) {
		$this->links = &$links;
		$this->model = &$links->links_model->model;
		$this->options = &$this->model->options;
		$this->curlang = &$curlang;
		$this->pref_lang = $pref_lang;

		// filters posts, pages and media by language
		add_filter('parse_query',array(&$this,'parse_query'));

		// adds the Languages box in the 'Edit Post' and 'Edit Page' panels
		add_action('add_meta_boxes', array(&$this, 'add_meta_boxes'));

		// ajax response for changing the language in the post metabox
		add_action('wp_ajax_post_lang_choice', array(&$this,'post_lang_choice'));
		add_action('wp_ajax_pll_posts_not_translated', array(&$this,'ajax_posts_not_translated'));

		// adds actions and filters related to languages when creating, saving or deleting posts and pages
		add_action('save_post', array(&$this, 'save_post'), 21, 2); // priority 21 to come after advanced custom fields (20) and before the event calendar which breaks everything after 25
		add_action('before_delete_post', array(&$this, 'delete_post'));
		if ($this->options['media_support'])
			add_action('delete_attachment', array(&$this, 'delete_post')); // action shared with media

		// filters the pages by language in the parent dropdown list in the page attributes metabox
		add_filter('page_attributes_dropdown_pages_args', array(&$this, 'page_attributes_dropdown_pages_args'), 10, 2);
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
			if (isset($_REQUEST['pll_post_id']) && $lang = $this->model->get_post_language($_REQUEST['pll_post_id']))
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

		// NOTE: the class "tags-input" allows to include the field in the autosave $_POST (see autosave.js)
		printf('
			<p><strong>%s</strong></p>
			%s
			<div id="post-translations" class="translations">',
			__('Language', 'polylang'),
			$dropdown->walk($this->model->get_languages_list(), array(
				'name'     => $post_type == 'attachment' ? sprintf('attachments[%d][language]', $post_ID) : 'post_lang_choice',
				'class'    => $post_type == 'attachment' ? 'media_lang_choice' : 'tags-input',
				'selected' => $lang ? $lang->slug : ''
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
		global $post_ID; // obliged to use the global variable for wp_popular_terms_checklist
		$post_ID = $_POST['post_id'];
		$post_type = get_post_type($post_ID);
		$lang = $this->model->get_language($_POST['lang']);

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
			require_once( ABSPATH . 'wp-admin/includes/meta-boxes.php' );
			ob_start();
			page_attributes_meta_box(get_post($post_ID));
			$x->Add(array('what' => 'pages', 'data' => ob_get_contents()));
			ob_end_clean();
		}

		$x->send();
	}

	/*
	 * ajax response for input in translation autocomplete input box
	 *
	 * @since 1.5
	 */
	public function ajax_posts_not_translated() {
		$posts = get_posts(array(
			's'                => $_REQUEST['term'],
			'suppress_filters' => 0, // to make the post_fields filter work
			'lang'             => 0, // avoid admin language filter
			'numberposts'      => 10, // limit to 10 posts
			'nopaging'         => true,
			'post_status'      => 'any',
			'post_type'        => $_REQUEST['post_type'],
			'orderby'          => 'title',
			'order'            => 'ASC',
			'tax_query'        => array(array(
				'taxonomy' => 'language',
				'field'    => 'term_taxonomy_id', // WP 3.5+
				'terms'    => $this->model->get_language($_REQUEST['translation_language'])->term_taxonomy_id
			))
		));

		$return = array();

		foreach ($posts as $key => $post) {
			if (!$this->model->get_translation('post', $post->ID, $_REQUEST['post_language']))
				$return[] = array('id' => $post->ID, 'value' => $post->post_title, 'link' => $this->edit_translation_link($post->ID));
		}

		// add current translation in list
		if ($post_id = $this->model->get_translation('post', $_REQUEST['pll_post_id'],$_REQUEST['translation_language'])) {
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
	 * called when a post (or page) is saved, published or updated
	 * saves languages and translations
	 * checks the terms saved are in the right language
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 * @param object $post
	 */
	public function save_post($post_id, $post) {
		// does nothing except on post types which are filterable
		if (!$this->model->is_translated_post_type($post->post_type))
			return;

		// bulk edit does not modify the language
		if (isset($_GET['bulk_edit']) && $_REQUEST['inline_lang_choice'] == -1)
			return;

		if ($id = wp_is_post_revision($post_id))
			$post_id = $id;

		// save language
		if (isset($_REQUEST['post_lang_choice']))
			$this->model->set_post_language($post_id, $lang = $_REQUEST['post_lang_choice']);

		elseif (isset($_REQUEST['inline_lang_choice'])) {
			if (($old_lang = $this->model->get_post_language($post_id)) && $old_lang->slug != $_REQUEST['inline_lang_choice'])
				$this->model->delete_translation('post', $post_id);
			$this->model->set_post_language($post_id, $lang = $_REQUEST['inline_lang_choice']);
		}

		elseif (isset($_GET['new_lang']))
			$this->model->set_post_language($post_id, $_GET['new_lang']);

		elseif (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('post-quickpress-save', 'post-quickpress-publish')))
			$this->model->set_post_language($post_id, $this->pref_lang); // default language for QuickPress

		elseif ($this->model->get_post_language($post_id))
			{} // avoids breaking the language if post is updated outside the edit post page (thanks to Gonçalo Peres)

		elseif (($parent_id = wp_get_post_parent_id($post_id)) && $parent_lang = $this->model->get_post_language($parent_id))
			$this->model->set_post_language($post_id, $parent_lang);

		else
			$this->model->set_post_language($post_id, $this->pref_lang);

		// make sure we get save terms in the right language (especially tags with same name in different languages)
		if (!empty($lang)) {
			// FIXME quite a lot of query in foreach
			foreach ($this->model->get_translated_taxonomies() as $tax) {
				$terms = get_the_terms($post_id, $tax);

				if (is_array($terms)) {
					$newterms = array();
					foreach ($terms as $term) {
						if ($newterm = $this->model->term_exists($term->name, $tax, $term->parent, $lang))
							$newterms[] = (int) $newterm; // cast is important otherwise we get 'numeric' tags

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
		foreach ($_POST['post_tr_lang'] as $lang => $tr_id)
			$translations[$lang] = ($tr_id && $this->model->get_post_language((int) $tr_id)->slug == $lang) ? (int) $tr_id : 0;

		$this->model->save_translations('post', $post_id, $translations);

		// refresh language cache when a static front page has been translated
		if (($pof = get_option('page_on_front')) && in_array($pof, $translations))
			$this->model->clean_languages_cache();

		do_action('pll_save_post', $post_id, $post, $translations);
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

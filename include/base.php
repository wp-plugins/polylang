<?php

// setups basic functions used for admin and frontend
abstract class Polylang_Base {
	protected $options;
	protected $home;
	protected $strings = array(); // strings to translate
	protected $post_types; // post types to filter by language
	protected $taxonomies; // taxonomies to filter by language

	function __construct() {
		// init options often needed
		$this->options = get_option('polylang');
		$this->home = get_option('home');

		add_action('wp_loaded', array(&$this, 'add_post_types_taxonomies')); // must come late to be sure that all post types and taxonomies are registered
	}

	// init post types and taxonomies to filter by language
	function add_post_types_taxonomies() {
		$this->post_types = apply_filters('pll_get_post_types', get_post_types(array('show_ui' => true)));
		$this->taxonomies = apply_filters('pll_get_taxonomies', get_taxonomies(array('show_ui'=>true)));
	}

	// returns the list of available languages
	function get_languages_list($hide_empty = false) {
		return get_terms('language', array('hide_empty'=>$hide_empty, 'orderby'=>'term_group' ));
	}

	// retrieves the dropdown list of the languages
	function dropdown_languages($args) {
		$defaults = array('name' => 'lang_choice', 'class' => '', 'show_option_none' => false, 'show_option_all' => false,
			'hide_empty' => false, 'value' => 'slug', 'selected' => '');
		extract(wp_parse_args($args, $defaults));

		$out = sprintf('<select name="%1$s" id="%1$s"%2$s>'."\n", esc_attr($name), $class ? ' class="'.esc_attr($class).'"' : '');
		$out .= $show_option_none ? "<option value='0'></option>\n" : '';
		$out .= $show_option_all ? "<option value='0'>".__('All languages', 'polylang')."</option>\n" : '';
		foreach ($this->get_languages_list($hide_empty) as $language) {
			$out .= sprintf("<option value='%s'%s>%s</option>\n",
				esc_attr($language->$value),
				$language->$value == $selected ? ' selected="selected"' : '',
				esc_html($language->name)
			);
		}
		$out .= "</select>\n";
		return $out;
	}

	// returns the language by its id or its slug
	// Note: it seems that a numeric value is better for performance (3.2.1)
	function get_language($value) {
		if (is_object($value))
			return $value;
		if (is_numeric($value) || (int) $value)
			return get_term((int) $value, 'language');
		elseif (is_string($value))
			return get_term_by('slug', $value , 'language'); // seems it is not cached in 3.2.1
		return null;
	}

	// saves translations for posts or terms
	// the underscore in '_lang' hides the post meta in the Custom Fields metabox in the Edit Post screen
	// $type: either 'post' or 'term'
	// $id: post id or term id
	// $translations: an associative array of translations with language code as key and translation id as value
	function save_translations($type, $id, $translations) {
		$lang = call_user_func(array(&$this, 'get_'.$type.'_language'), $id);
		if (!$lang)
			return;

		if (isset($translations) && is_array($translations)) {
			$tr = serialize(array_merge(array($lang->slug => $id), $translations));
			update_metadata($type, $id, '_translations', $tr);

			foreach($translations as $key=>$p)
				update_metadata($type, (int) $p, '_translations', $tr);
		}
	}

	// deletes a translation of a post or term
	function delete_translation($type, $id) {
		$translations = unserialize(get_metadata($type, $id, '_translations', true));
		if (is_array($translations)) {
			$slug = array_search($id, $translations);
			unset($translations[$slug]);
			$tr = serialize($translations);
			foreach($translations as $p)
				update_metadata($type, (int) $p, '_translations', $tr);
			delete_metadata($type, $id, '_translations');
		}
	}

	// returns the id of the translation of a post or term
	// $type: either 'post' or 'term'
	// $id: post id or term id
	// $lang: object or slug (in the order of preference latest to avoid)
	function get_translation($type, $id, $lang) {
		$translations = unserialize(get_metadata($type, $id, '_translations', true));
		$slug = $this->get_language($lang)->slug;
		return isset($translations[$slug]) ? (int) $translations[$slug] : '';
	}

	// store the post language in the database
	function set_post_language($post_id, $lang) {
		wp_set_post_terms($post_id, $this->get_language($lang)->slug, 'language' );
	}

	// returns the language of a post
	function get_post_language($post_id) {
		$lang = get_the_terms($post_id, 'language' );
		return ($lang) ? reset($lang) : null; // there's only one language per post : first element of the array returned
	}

	// among the post and its translations, returns the id of the post which is in $lang
	function get_post($post_id, $lang) {
		if (!$lang)
			return '';

		$lang = $this->get_language($lang);
		return $this->get_post_language($post_id)->term_id == $lang->term_id ? $post_id : $this->get_translation('post', $post_id, $lang);
	}

	// store the term language in the database
	function set_term_language($term_id, $lang) {
		update_metadata('term', $term_id, '_language', $this->get_language($lang)->term_id);
	}

	// remove the term language in the database
	function delete_term_language($term_id) {
		delete_metadata('term', $term_id, '_language');
	}

	// returns the language of a term
	function get_term_language($value, $taxonomy = '') {
		if (is_numeric($value))
			$term_id = $value;
		elseif (is_string($value) && $taxonomy)
			$term_id = get_term_by('slug', $value , $taxonomy)->term_id;
		return $term_id ? $this->get_language(get_metadata('term', $term_id, '_language', true)) : null;
	}

	// among the term and its translations, returns the id of the term which is in $lang
	function get_term($term_id, $lang) {
		$lg = $this->get_term_language($term_id);
		if (!$lang || !$lg || $lg == null) // FIXME should be more consistent in returned values
			return '';

		$lang = $this->get_language($lang);
		return $lg->term_id == $lang->term_id ? $term_id : $this->get_translation('term', $term_id, $lang);
	}

	// adds language information to a link when using pretty permalinks
	function add_language_to_link($url, $lang) {
		if (!isset($lang) || !$lang) // FIXME avoid notice when adding a page to a custom menu
			return;

		global $wp_rewrite;
		if ($wp_rewrite->using_permalinks()) {
			$base = $this->options['rewrite'] ? '' : 'language/';
			$slug = $this->options['default_lang'] == $lang->slug && $this->options['hide_default'] ? '' : $base.$lang->slug.'/';
			return esc_url(str_replace($this->home.'/'.$wp_rewrite->root, $this->home.'/'.$wp_rewrite->root.$slug, $url));
		}

		// special case for pages which do not accept adding the lang parameter
		elseif ('_get_page_link' != current_filter())
			return add_query_arg( 'lang', $lang->slug, $url );

		return $url;
	}

	// optionally rewrite posts, pages links to filter them by language
	// rewrite post format (and optionally categories and post tags) archives links to filter them by language
	function add_post_term_link_filters() {
		if ($this->options['force_lang'] && $GLOBALS['wp_rewrite']->using_permalinks()) {
			foreach (array('post_link', '_get_page_link', 'post_type_link') as $filter)
				add_filter($filter, array(&$this, 'post_link'), 10, 2);
		}

		add_filter('term_link', array(&$this, 'term_link'), 10, 3);
	}

	// modifies post & page links
	function post_link($link, $post) {
		return $this->add_language_to_link($link, $this->get_post_language('_get_page_link' == current_filter() ? $post : $post->ID));
	}

	// modifies term link
	function term_link($link, $term, $tax) {
		return $tax == 'post_format' || ($this->options['force_lang'] && $GLOBALS['wp_rewrite']->using_permalinks() && $tax != 'language') ?
			$this->add_language_to_link($link, $this->get_term_language($term->term_id)) : $link;
	}

	// returns the html link to the flag if exists
	// $lang: object
	function get_flag($lang) {
		if (file_exists(POLYLANG_DIR.($file = '/flags/'.$lang->description.'.png')))
			$url = POLYLANG_URL.$file;

		// overwrite with custom flags
		if (!is_admin() && ( // never use custom flags on admin side
			file_exists(PLL_LOCAL_DIR.($file = '/'.$lang->description.'.png')) ||
			file_exists(PLL_LOCAL_DIR.($file = '/'.$lang->description.'.jpg')) ))
			$url = PLL_LOCAL_URL.$file;

		$title = apply_filters('pll_flag_title', $lang->name, $lang->slug, $lang->description);
		return isset($url) ? '<img src="'.esc_url($url).'" title="'.esc_attr($title).'" alt="'.esc_attr($lang->name).'" />' : '';
	}

	// adds terms clauses to get_terms - used in both frontend and admin
	function _terms_clauses($clauses, $lang, $display_all = false) {
		global $wpdb;
		if (isset($lang) && !is_wp_error($lang)) {
			$clauses['join'] .= $wpdb->prepare(" LEFT JOIN $wpdb->termmeta AS pll_tm ON t.term_id = pll_tm.term_id");
			$where_lang = $wpdb->prepare("pll_tm.meta_key = '_language' AND pll_tm.meta_value = %d", $lang->term_id); // add terms in the right language
			$where_all = $wpdb->prepare("t.term_id NOT IN (SELECT term_id FROM $wpdb->termmeta WHERE meta_key IN ('_language'))");	// add terms with no language set
			$clauses['where'] .= $display_all ? " AND (($where_lang) OR ($where_all))" : " AND $where_lang";
		}
		return $clauses;
	}

	// returns all page ids *not in* language defined by $lang_id
	function exclude_pages($lang_id) {
		$q = array(
			'numberposts'=>-1,
			'post_type' => 'page',
			'fields' => 'ids',
			'tax_query' => array(array(
				'taxonomy'=>'language',
				'fields' => 'id',
				'terms'=>$lang_id,
				'operator'=>'NOT IN'
			))
		);
		return get_posts($q);
	}

	// export a mo object in options
	function mo_export($mo, $lang) {
		$strings = array();
		foreach ($mo->entries as $entry)
			$strings[] = array($entry->singular, $mo->translate($entry->singular));
		update_option('polylang_mo'.$lang->term_id, $strings);
	}

	// import a mo object from options
	function mo_import($lang) {
		$mo = new MO();
		if ($strings = get_option('polylang_mo'.$lang->term_id)) {
			foreach ($strings as $msg)
				$mo->add_entry($mo->make_entry($msg[0], $msg[1]));
		}
 		return $mo;
	}

} //class Polylang_Base
?>

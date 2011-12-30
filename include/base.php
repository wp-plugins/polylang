<?php

// setups basic functions
abstract class Polylang_Base {

	// returns the query variables of the referer
	function get_referer_vars() {
		$qvars = array();
		$referer = wp_get_referer();
		if ($referer) {
			$urlparts = parse_url($referer);
			if (isset($urlparts['query'])) {
				parse_str($urlparts['query'], $qvars);
			}
		}
		return $qvars;
	}

	// returns the list of available languages
	function get_languages_list($hide_empty = false) {
		return get_terms('language', array('hide_empty'=>$hide_empty, 'orderby'=>'term_group' ));
	}

	// returns the language by its id or its slug
	// Note: it seems that the first option is better for performance (3.2.1)
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
	function save_translations($type, $id, $translations) {
		$lang = call_user_func(array(&$this, 'get_'.$type.'_language'), $id);
		if (!$lang)
			return;

		if (isset($translations) && is_array($translations)) {
			$tr = serialize(array_merge(array($lang->slug => $id), $translations));
			update_metadata($type, $id, '_translations', $tr);

			foreach($translations as $key=>$p)
				update_metadata($type, $p, '_translations', $tr);
		}
	}

	// deletes a translation of a post or term
	function delete_translation($type, $id) {
		$translations = unserialize(get_metadata($type, $id, '_translations', true));
		if (is_array($translations)) {
			$slug = array_search($id, $translations);
			unset($translations[$slug]);
			$tr = serialize($translations);
			foreach($translations as $key=>$p)
				update_metadata($type, $p, '_translations', $tr);
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
		return isset($translations[$slug]) ? $translations[$slug] : '';
	}

	// returns the language of a post
	function get_post_language($post_id) {
		$lang = get_the_terms($post_id, 'language' );
		return ($lang) ? reset($lang) : null; // there's only one language per post : first element of the array returned
	}

	// among the post and its translations, returns the id of the post which is in $lang
	function get_post($post_id, $lang) {
		$lang = $this->get_language($lang);		
		return $this->get_post_language($post_id)->term_id == $lang->term_id ? $post_id : $this->get_translation('post', $post_id, $lang);
	}

	// store the term language in the database
	function update_term_language($term_id, $lang) {
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
		$lang = $this->get_language($lang);		
		return $this->get_term_language($term_id)->term_id == $lang->term_id ? $term_id : $this->get_translation('term', $term_id, $lang);
	}

	// returns the html link to the flag if exists
	// $lang: object
	function get_flag($lang) {
		if (file_exists(POLYLANG_DIR.($file = '/flags/'.$lang->description.'.png')))
			$url = WP_PLUGIN_URL.'/polylang'.$file;

		// overwrite with custom flags
		if ( !is_admin() && ( // never use custom flags on admin side
			file_exists(WP_CONTENT_DIR.($file = '/polylang/'.$lang->description.'.png')) ||
			file_exists(WP_CONTENT_DIR.($file = '/polylang/'.$lang->description.'.jpg')) ))
			$url = WP_CONTENT_URL.$file;

		return isset($url) ? '<img src="'.esc_url($url).'" alt="'.esc_attr($lang->name).'" />' : '';
	}

	// adds terms clauses to get_terms - used in both frontend and admin
	function _terms_clauses($clauses, $lang, $display_all = false) {
		global $wpdb;
		if (isset($lang) && !is_wp_error($lang)) {
			$clauses['join'] .= " LEFT JOIN $wpdb->termmeta AS tm ON t.term_id = tm.term_id";
			$where_lang = $wpdb->prepare("tm.meta_key = '_language' AND tm.meta_value = %d", $lang->term_id); // add terms in the right language
			$where_all = "t.term_id NOT IN (SELECT term_id FROM $wpdb->termmeta WHERE meta_key IN ('_language'))";	// add terms with no language set
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

} //class Polylang_Base
?>

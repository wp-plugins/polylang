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
		return get_terms('language', array('hide_empty'=>$hide_empty));
	}

	// returns the language by its id or its slug
	// Note: it seems that the first option is better for performance (3.2.1)
	function get_language($value) {
		if (is_numeric($value))
			return get_term($value, 'language');
		elseif (is_string($value))
			return get_term_by('slug', $value , 'language'); // seems it is not cached in 3.2.1
		return null;
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
	function get_translation($type, $id, $lang) {
		$translations = unserialize(get_metadata($type, $id, '_translations', true));
		$slug = is_string($lang) ? $lang : $lang->slug;
		return isset($translations[$slug]) ? $translations[$slug] : '';
	}

	// returns the language of a post
	function get_post_language($post_id) {
		$lang = get_the_terms($post_id, 'language' );
		return ($lang) ? reset($lang) : null; // there's only one language per post : first element of the array returned
	}

	// among the post and its translations, returns the id of the post which is in $lang
	function get_post($post_id, $lang) {
		$slug = is_string($lang) ? $lang : $lang->slug;		
		return $this->get_post_language($post_id)->slug == $slug ? $post_id : $this->get_translation('post', $post_id, $lang);
	}

	function update_term_language($term_id, $lang) {
		if (is_numeric($lang))
			$lang_id = $lang;
		elseif(is_string($lang))
			$lang_id = $this->get_language($lang)->term_id;
		else
			$lang_id = $lang->term_id;

		update_metadata('term', $term_id, '_language', $lang_id );
	}

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
		$slug = is_string($lang) ? $lang : $lang->slug;		
		return $this->get_term_language($term_id)->slug == $slug ? $term_id : $this->get_translation('term', $term_id, $lang);
	}

	// returns the html link to the flag if exists
	function get_flag($lang) {
		return ( !is_admin() && ( // never use local flags on admin side
			file_exists(POLYLANG_DIR.($file = '/local_flags/'.$lang->description.'.png')) ||
			file_exists(POLYLANG_DIR.($file = '/local_flags/'.$lang->description.'.jpg')) )) ||
			file_exists(POLYLANG_DIR.($file = '/flags/'.$lang->description.'.png')) ?
			'<img src="'.esc_url(WP_PLUGIN_URL.'/polylang'.$file).'" alt="'.esc_attr($lang->name).'" />' : '';
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

} //class Polylang_Base
?>

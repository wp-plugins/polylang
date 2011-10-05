<?php

class Polylang_Base {

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
	// Note ist seems that the first option is better for performance (3.2.1)
	function get_language($value) {
		if (is_numeric($value))
			return get_term($value, 'language');
		elseif (is_string($value))
			return get_term_by('slug', $value , 'language');
		return null;
	}

	// returns the language of a post
	function get_post_language($post_id) {
		$lang = get_the_terms($post_id, 'language' );
		return ($lang) ? reset($lang) : null; // there's only one language per post : first element of the array returned
	}

	// returns the id of the translation of a post
	function get_translated_post($post_id, $language) {
		return get_post_meta($post_id, '_lang-'.$language->slug, true); 
	}

	// among the post and its translations, returns the id of the post which is in $language
	function get_post($post_id, $language) {
		$lang = $this->get_post_language($post_id);
		return $lang->slug == $language->slug ? $post_id : $this->get_translated_post($post_id, $language);
	}

	// returns the language of a term
	function get_term_language($term_id) {
		return $this->get_language(get_metadata('term', $term_id, '_language', true));
	}

	// returns the id of the translation of a term (category or post_tag)
	function get_translated_term($term_id, $language) {
		return get_metadata('term', $term_id, '_lang-'.$language->slug, true); 
	}

} //class Polylang_Base
?>

<?php

// template tag: displays the language switcher
function pll_the_languages($args = '') {
	global $polylang;
	return isset($polylang) ? $polylang->the_languages($args) : '';
}

// among the post and its translations, returns the id of the post which is in the language represented by $slug
function pll_get_post($post_id, $slug) {
	global $polylang;
	return isset($polylang) ? $polylang->get_post($post_id, $slug) : null;
}

// among the term and its translations, returns the id of the term which is in the language represented by $slug
function pll_get_term($term_id, $slug) {
	global $polylang;
	return isset($polylang) ? $polylang->get_term($term_id, $slug) : null;	
}

// acts as is_front_page but knows about translated front page
function pll_is_front_page() {
	global $polylang;
	return isset($polylang) ? $polylang->is_front_page() : null;
}

?>

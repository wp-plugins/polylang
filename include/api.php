<?php

// template tag: displays the language switcher
function pll_the_languages($args = '') {
	global $polylang;
	return isset($polylang) ? $polylang->the_languages($args) : '';
}

// returns the current language
function pll_current_language($args = 'slug') {
	global $polylang;
	return isset($polylang) ? $polylang->current_language($args) : false;
}

// among the post and its translations, returns the id of the post which is in the language represented by $slug
function pll_get_post($post_id, $slug = false) {
	global $polylang;
	$slug = $slug ? $slug : pll_current_language();
	return isset($polylang) && $slug ? $polylang->get_post($post_id, $slug) : null;
}

// among the term and its translations, returns the id of the term which is in the language represented by $slug
function pll_get_term($term_id, $slug = false) {
	global $polylang;
	$slug = $slug ? $slug : pll_current_language();
	return isset($polylang) && $slug ? $polylang->get_term($term_id, $slug) : null;
}

// acts as is_front_page but knows about translated front page
function pll_is_front_page() {
	global $polylang;
	return isset($polylang) ? $polylang->is_front_page() : is_front_page();
}

// returns the home url in the right language
function pll_home_url() {
	global $polylang;
	return isset($polylang) ? $polylang->get_home_url() : home_url('/');
}

// register strings for translation in the "strings translation" panel
function pll_register_string($name, $string) {
	global $polylang;
	if (isset($polylang))
		$polylang->register_string($name, $string);
}

// translates string (previously registered with pll_register_string)
function pll__($string) {
	return __($string, 'pll_string');
}

// echoes translated string (previously registered with pll_register_string)
function pll_e($string) {
	_e($string, 'pll_string');
}
?>

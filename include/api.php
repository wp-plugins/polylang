<?php

// template tag: displays the language switcher
function pll_the_languages($args = '') {
	global $polylang;
	return isset($polylang) ? $polylang->the_languages($args) : '';
}

// among the post and its translations, returns the id of the post which is in the language represented by $slug
function pll_get_post($post_id, $slug = '') {
	global $polylang;
	$slug = $slug ? $slug : reset(explode('_', get_locale()));
	return isset($polylang) ? $polylang->get_post($post_id, $slug) : null;
}

// among the term and its translations, returns the id of the term which is in the language represented by $slug
function pll_get_term($term_id, $slug = '') {
	global $polylang;
	$slug = $slug ? $slug : reset(explode('_', get_locale()));
	return isset($polylang) ? $polylang->get_term($term_id, $slug) : null;	
}

// acts as is_front_page but knows about translated front page
function pll_is_front_page() {
	global $polylang;
	return isset($polylang) ? $polylang->is_front_page() : null;
}

// register strings for translation in the "strings translation" panel
function pll_register_string($name, $string) {
	global $polylang;
	if ($polylang)
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

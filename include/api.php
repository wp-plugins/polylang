<?php

// template tag: displays the language switcher
function pll_the_languages($args = '') {
	global $polylang;
	return class_exists('Polylang_Core') && $polylang instanceof Polylang_Core ? $polylang->the_languages($args) : '';
}

// returns the current language
function pll_current_language($args = 'slug') {
	global $polylang;
	return class_exists('Polylang_Core') && $polylang instanceof Polylang_Core ? $polylang->current_language($args) : false;
}

// among the post and its translations, returns the id of the post which is in the language represented by $slug
function pll_get_post($post_id, $slug = false) {
	global $polylang;
	return isset($polylang) && ($slug = $slug ? $slug : pll_current_language()) ? $polylang->get_post($post_id, $slug) : null;
}

// among the term and its translations, returns the id of the term which is in the language represented by $slug
function pll_get_term($term_id, $slug = false) {
	global $polylang;
	return isset($polylang) && ($slug = $slug ? $slug : pll_current_language()) ? $polylang->get_term($term_id, $slug) : null;
}

// returns the home url in the right language
function pll_home_url() {
	global $polylang;
	return class_exists('Polylang_Core') && $polylang instanceof Polylang_Core ? $polylang->get_home_url() : home_url('/');
}

// register strings for translation in the "strings translation" panel
function pll_register_string($name, $string, $multiline = false) {
	global $polylang;
	if (isset($polylang) && is_admin())
		$polylang->register_string($name, $string, $multiline);
}

// translates string (previously registered with pll_register_string)
function pll__($string) {
	return __($string, 'pll_string');
}

// echoes translated string (previously registered with pll_register_string)
function pll_e($string) {
	_e($string, 'pll_string');
}

// compatibility with WPML API
add_action('pll_language_defined', 'pll_define_wpml_constants');

function pll_define_wpml_constants() {
	if(!defined('ICL_LANGUAGE_CODE'))
    define('ICL_LANGUAGE_CODE', pll_current_language());

	if(!defined('ICL_LANGUAGE_NAME'))
    define('ICL_LANGUAGE_NAME', pll_current_language('name'));
}

if (!function_exists('icl_object_id')) {
	function icl_object_id($id, $type, $return_original_if_missing, $lang = false) {
		global $polylang;
		return isset($polylang) && ($lang = $lang ? $lang : pll_current_language()) && ($tr_id = $polylang->get_translation($type, $id, $lang)) ? $tr_id :
			($return_original_if_missing ? $id : null);
	}
}

if (!function_exists('icl_get_home_url')) {
	function icl_get_home_url() {
		return pll_home_url();
	}
}

if (!function_exists('icl_register_string')) {
	function icl_register_string($context, $name, $string) {
		pll_register_string($name, $string);
	}
}

if (!function_exists('icl_t')) {
	function icl_t($context, $name, $string) {
		return pll__($string);
	}
}

?>

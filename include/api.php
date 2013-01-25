<?php

/*
 * template tag: displays the language switcher
 */
function pll_the_languages($args = '') {
	global $polylang;
	return class_exists('Polylang_Core') && $polylang instanceof Polylang_Core ? $polylang->the_languages($args) : '';
}

/*
 * returns the current language
 */
function pll_current_language($args = 'slug') {
	global $polylang;
	return !(class_exists('Polylang_Core') && $polylang instanceof Polylang_Core && isset($polylang->curlang)) ? false :
		($args == 'name' ? $polylang->curlang->name :
		($args == 'locale' ? $polylang->curlang->description :
		$polylang->curlang->slug));
}

/*
 * returns the default language
 */
function pll_default_language($arg = 'slug') {
	global $polylang;
	return !(isset($polylang) && ($options = get_options('polylang')) && isset($options['default-lang']) && $lang = $polylang->get_language($options['default-lang'])) ? false :
		($args == 'name' ? $lang->name :
		($args == 'locale' ? $lang->description :
		$lang->slug));
}

/*
 * among the post and its translations, returns the id of the post which is in the language represented by $slug
 */
function pll_get_post($post_id, $slug = false) {
	global $polylang;
	return isset($polylang) && ($slug = $slug ? $slug : pll_current_language()) ? $polylang->get_post($post_id, $slug) : null;
}

/*
 * among the term and its translations, returns the id of the term which is in the language represented by $slug
 */
function pll_get_term($term_id, $slug = false) {
	global $polylang;
	return isset($polylang) && ($slug = $slug ? $slug : pll_current_language()) ? $polylang->get_term($term_id, $slug) : null;
}

/*
 * returns the home url in the current language
 */
function pll_home_url() {
	global $polylang;
	return class_exists('Polylang_Core') && $polylang instanceof Polylang_Core ? $polylang->get_home_url() : home_url('/');
}

/*
 * registers a string for translation in the "strings translation" panel
 */
function pll_register_string($name, $string, $multiline = false) {
	global $polylang;
	if (isset($polylang) && is_admin())
		$polylang->register_string($name, $string, $multiline);
}

/*
 * translates a string (previously registered with pll_register_string)
 */
function pll__($string) {
	return __($string, 'pll_string');
}

/*
 * echoes a translated string (previously registered with pll_register_string)
 */
function pll_e($string) {
	_e($string, 'pll_string');
}

/*
 * returns true if Polylang manages languages and translation for this post type
 */
function pll_is_translated_post_type($post_type) {
	global $polylang;
	return isset($polylang) && in_array($post_type, $polylang->post_types);
}

/*
 * returns true if Polylang manages languages and translation for this taxonomy
 */
function pll_is_translated_taxonomy($tax) {
	global $polylang;
	return isset($polylang) && in_array($tax, $polylang->taxonomies);
}


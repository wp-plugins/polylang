<?php

class Polylang_Uninstall {

	function __construct() {
		global $wpdb;

		// check if it is a multisite uninstall - if so, run the uninstall function for each blog id
		if (is_multisite()) {
			foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
				switch_to_blog($blog_id);
				$this->uninstall();
			}
			restore_current_blog();
		}
		else
			$this->uninstall();
	}

	// removes ALL plugin data (languages, translation, and the termmeta table if empty
	function uninstall() {
		global $wpdb;
		$wpdb->termmeta = $wpdb->prefix . 'termmeta'; // registers the termmeta table in wpdb

		// need to register the language taxonomy
		register_taxonomy('language', apply_filters('pll_get_post_types', get_post_types(array('show_ui' => true))),
			array('label' => false, 'query_var'=>'lang'));

		$languages = get_terms('language', array('hide_empty'=>false));

		// delete users options
		foreach (get_users(array('fields' => 'ID')) as $user_id) {
			delete_user_meta($user_id, 'user_lang');
			delete_user_meta($user_id, 'pll_filter_content');
			foreach ($languages as $lang)
				delete_user_meta($user_id, 'description_'.$lang->slug);
		}

		// delete posts translations
		$ids = get_posts(array(
			'numberposts' => -1,
			'nopaging'    => true,
			'fields'      => 'ids',
			'meta_key'    => '_translations',
			'post_type'   => 'any',
			'post_status' => 'any'
		));

		foreach ($ids as $id)
			delete_post_meta($id, '_translations');

		// delete menu language switchers
		$ids = get_posts(array(
			'numberposts' => -1,
			'nopaging'    => true,
			'post_type'   => 'nav_menu_item',
			'fields'      => 'ids',
			'meta_key'    => '_pll_menu_item'
		));

		foreach ($ids as $id)
			wp_delete_post($id, true);

		// delete terms translations
		$ids = get_terms(apply_filters('pll_get_taxonomies', get_taxonomies(array('show_ui'=>true))), array('get'=>'all', 'fields'=>'ids'));
		foreach ($ids as $id) {
			delete_metadata('term', $id, '_translations');
			delete_metadata('term', $id, '_language');
		}

		foreach ($languages as $lang) {
			delete_metadata('term', $lang->term_id, '_rtl'); // delete rtl meta
			delete_option('polylang_mo'.$lang->term_id); // delete the string translations
			wp_delete_term($lang->term_id, 'language'); // finally delete languages
		}

		// delete the termmeta table only if it is empty as other plugins may use it
		$count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->termmeta;");
		if (!$count) {
			$wpdb->query("DROP TABLE $wpdb->termmeta;");
			unset($wpdb->termmeta);
		}

		// delete options
		delete_option('polylang');
		delete_option('widget_polylang'); // automatically created by WP
		delete_option('polylang_wpml_strings'); // strings registered with icl_register_string
	}
}

new Polylang_Uninstall();

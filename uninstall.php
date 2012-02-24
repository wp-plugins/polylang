<?php

class Polylang_Uninstall {

	function __construct() {
		global $wpdb;

		// check if it is a multisite uninstall - if so, run the uninstall function for each blog id
		if (is_multisite()) {
			foreach ($wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs")) as $blog_id) {
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
		register_taxonomy('language', apply_filters('pll_get_post_types', get_post_types(array('show_ui' => true))), array('label' => false, 'query_var'=>'lang'));

		$languages = get_terms('language', array('hide_empty'=>false));

		// delete posts translations
		$ids = get_posts(array('numberposts'=> -1, 'fields' => 'ids', 'meta_key'=>'_translations', 'post_type'=>'any', 'post_status'=>'any'));
		foreach ($ids as $id)
			delete_post_meta($id, '_translations');

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
		$table = $wpdb->termmeta;
		$count = $wpdb->get_var("SELECT COUNT(*) FROM $table;");
		if (!$count) {
			$wpdb->query($wpdb->prepare("DROP TABLE $table;"));
			unset($wpdb->termmeta);
		}

		// delete options
		delete_option('polylang');
		delete_option('polylang_nav_menus');
		delete_option('polylang_widgets');
		delete_option('widget_polylang'); // automatically created by WP
	}
}

if (class_exists("Polylang_Uninstall"))
	new Polylang_Uninstall();

?>

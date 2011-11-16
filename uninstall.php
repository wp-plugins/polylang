<?php

global $wpdb;
$wpdb->termmeta = $wpdb->prefix . 'termmeta'; // registers the termmeta table in wpdb
register_taxonomy('language', get_post_types(array('show_ui' => true)), array('label' => false, 'query_var'=>'lang')); // temporarily register the language taxonomy

$languages = get_terms('language', array('hide_empty'=>false));

foreach ($languages as $lang) {
	// delete references to this language in all posts
	$args = array('numberposts'=> -1, 'post_type'=>'any', 'post_status'=>'any');
	$posts = get_posts($args);
	foreach ($posts as $post) {
		delete_post_meta($post->ID, '_lang-'.$lang->slug);
	}
	// delete references to this language in categories & post tags
	$terms = get_terms(get_taxonomies(array('show_ui'=>true)), 'get=all');
 	foreach ($terms as $term) {
		delete_metadata('term', $term->term_id, '_language');
		delete_metadata('term', $term->term_id, '_lang-'.$lang->slug);
	}				
	// finally delete the language itself
	wp_delete_term($lang->term_id, 'language');
}

// delete the termmeta table only if it is empty as other plugins may use it
$table = $wpdb->termmeta;
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table;");
if (!$count) {
	$wpdb->query("DROP TABLE $table;");
	unset($wpdb->termmeta);
}

// delete options 
delete_option('polylang');
delete_option('polylang_nav_menus');
delete_option('polylang_widgets');
delete_option('widget_polylang_widget'); // automatically created by WP
?>

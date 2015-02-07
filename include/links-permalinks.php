<?php

/*
 * links model base class when using pretty permalinks
 *
 * @since 1.6
 */
abstract class PLL_Links_Permalinks extends PLL_Links_Model {
	public $using_permalinks = true;
	protected $always_rewrite = array('date', 'root', 'comments', 'search', 'author');

	/*
	 * returns the link to the first page when using pretty permalinks
	 *
	 * @since 1.2
	 *
	 * @param string $url url to modify
	 * @return string modified url
	 */
	public function remove_paged_from_link($url) {
		return preg_replace('#\/page\/[0-9]+\/#', '/', $url); // FIXME trailing slash ?
	}

	/*
	 * returns the link to the paged page when using pretty permalinks
	 *
	 * @since 1.5
	 *
	 * @param string $url url to modify
	 * @param int $page
	 * @return string modified url
	 */
	public function add_paged_to_link($url, $page) {
		return trailingslashit($url) . 'page/' . $page; // FIXME trailing slash ?
	}

	/*
	 * prepares rewrite rules filters
	 *
	 * @since 1.6
	 */
	public function get_rewrite_rules_filters() {
		// make sure we have the right post types and taxonomies
		$types = array_values(array_merge($this->model->get_translated_post_types(), $this->model->get_translated_taxonomies(), $this->model->get_filtered_taxonomies()));
		$types = array_merge($this->always_rewrite, $types);
		return apply_filters('pll_rewrite_rules', $types); // allow plugins to add rewrite rules to the language filter
	}
}

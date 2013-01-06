<?php

// Thanks to Matt Van Andel (http://www.mattvanandel.com) for its plugin "Custom List Table Example" !

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ); // since WP 3.1
}

// Just an abstract class with common code for our list tables
abstract class Polylang_List_Table extends WP_List_Table {
	function __construct($args = array()) {
		parent::__construct($args);
	}

	static function column_default($item, $column_name) {
		return $item[$column_name];
	}

	function _prepare_items($data = array(), $per_page, $default_sort = '') {
		$this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

		$usort_reorder = function($a, $b) use ($default_sort) {
			$orderby = !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : $default_sort;
			$result = strcmp($a[$orderby], $b[$orderby]); // determine sort order
			return (empty($_REQUEST['order']) || $_REQUEST['order'] == 'asc') ? $result : -$result; // send final sort direction to usort
		};

		if (!empty($default_sort) || !empty($_REQUEST['orderby']))
			usort($data, $usort_reorder);

		$total_items = count($data);
		$this->items = array_slice($data, ($this->get_pagenum() - 1) * $per_page, $per_page);

		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_items/$per_page)
		));
	}
} // Polylang_List_Table

class Polylang_Languages_Table extends Polylang_List_Table {
	function __construct() {
		parent::__construct(array(
			'singular' => __('Language','polylang'),
			'plural'   => __('Languages','polylang'),
			'ajax'     => false
		));
	}

	function column_name($item) {
		$edit_link = esc_url(admin_url('admin.php?page=mlang&amp;action=edit&amp;lang=' . $item['term_id']));
		$delete_link = wp_nonce_url('?page=mlang&amp;action=delete&amp;noheader=true&amp;lang=' . $item['term_id'], 'delete-lang');
		$actions = array(
			'edit'   => '<a href="' . $edit_link . '">' . __('Edit','polylang') . '</a>',
			'delete' => '<a href="' . $delete_link .'">' . __('Delete','polylang') .'</a>'
		);
		return $item['name'] . $this->row_actions($actions);
	}

  function get_columns() {
		return array(
			'name'        => __('Full name', 'polylang'),
			'description' => __('Locale', 'polylang'),
			'slug'        => __('Code', 'polylang'),
			'term_group'  => __('Order', 'polylang'),
			'flag'        => __('Flag', 'polylang'),
			'count'       => __('Posts', 'polylang')
		);
	}

	function get_sortable_columns() {
		return array(
			'name'        => array('name', true), // sorted by name by default
			'description' => array('description', false),
			'slug'        => array('slug', false),
			'term_group'  => array('term_group', false),
			'count'       => array('count', false)
		);
	}

	function prepare_items($data = array()) {
		$per_page = $this->get_items_per_page('pll_lang_per_page');
		$this->_prepare_items($data, $per_page, 'name');
	}
} // class Polylang_Languages_Table

class Polylang_String_Table extends Polylang_List_Table {
	function __construct() {
		parent::__construct(array(
			'singular' => __('Strings translation','polylang'),
			'plural'   => __('Strings translations','polylang'),
			'ajax'     => false
		));
	}

	static function column_string($item) {
		return format_to_edit($item['string']); // don't interpret special chars for the string column
	}

	static function column_translations($item) {
		$out = '';
		foreach($item['translations'] as $key => $translation) {
			$input_type = $item['multiline'] ?
				'<textarea name="translation[%1$s][%2$s]" id="%1$s-%2$s">%4$s</textarea>' :
				'<input name="translation[%1$s][%2$s]" id="%1$s-%2$s" value="%4$s" />';
			$out .= sprintf('<div class="translation"><label for="%1$s-%2$s">%3$s</label>'.$input_type.'</div>'."\n",
				esc_attr($key),
				esc_attr($item['row']),
				esc_html($key),
				format_to_edit($translation)); // don't interpret special chars
		}
		return $out;
	}

  function get_columns() {
		return array(
			'name'         => __('Name', 'polylang'),
			'string'       => __('String', 'polylang'),
			'translations' => __('Translations', 'polylang'),
		);
	}

	function get_sortable_columns() {
		return array(
			'name'   => array('name', false),
			'string' => array('string', false),
		);
	}

	function prepare_items($data = array()) {
		$per_page = $this->get_items_per_page('pll_strings_per_page');
		$this->_prepare_items($data, $per_page);
	}
} // class Polylang_String_Table

<?php

// Thanks to Matt Van Andel (http://www.mattvanandel.com) for its plugin "Custom List Table Example" !

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ); // since WP 3.1
}

class Polylang_List_Table extends WP_List_Table {
	function __construct() {             
		parent::__construct( array(
			'singular' => __('Language','polylang'),
			'plural' => __('Languages','polylang'),
			'ajax'=> false)
		);        
	}

	function column_default( $item, $column_name){
		return $item[$column_name];
	}

	function column_name($item){
		$edit_link = esc_url(admin_url('admin.php?page=mlang&amp;action=edit&amp;lang='.$item['term_id']));
		$delete_link = wp_nonce_url('?page=mlang&amp;action=delete&amp;noheader=true&amp;lang=' . $item['term_id'], 'delete-lang');
		$actions = array(
			'edit'   => '<a href="' . $edit_link . '">' . __('Edit','polylang') . '</a>',
			'delete' => '<a href="' . $delete_link .'">' . __('Delete','polylang') .'</a>'
		);
        
		return $item['name'].$this->row_actions($actions);
	}

	function column_cb($item){
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ esc_attr($this->_args['singular']),
			/*$2%s*/ esc_attr($item['term_id'])
		);
	}

  function get_columns(){
		$columns = array(
// FIXME checkboxes are useles for now
//			'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text 
			'name'     => __('Full name', 'polylang'),
			'description'    => __('Locale', 'polylang'),
			'slug'  => __('Code', 'polylang'),
			'term_group' => __('Order', 'polylang'),
			'flag' => __('Flag', 'polylang'),
			'count'  => __('Posts', 'polylang')
		);
		return $columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'name' => array('name',true), // sorted by name by default
			'description' => array('description',false),
			'slug' => array('slug',false),
			'term_group' => array('term_group',false),
			'count' => array('count',false)
		);
		return $sortable_columns;
	}

/*
	function get_bulk_actions() {
		return array('delete' => 'Delete');
	}  
*/ 
    
	function prepare_items($data = array()) {
		$per_page = 10; // 10 languages per page
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
        
		$this->_column_headers = array($columns, $hidden, $sortable);

		function usort_reorder($a,$b){
			$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'name'; // if no sort, default to name
			$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; // if no order, default to asc
			$result = strcmp($a[$orderby], $b[$orderby]); // determine sort order
			return ($order==='asc') ? $result : -$result; // send final sort direction to usort
		}
		usort($data, 'usort_reorder');
               
		$current_page = $this->get_pagenum();
		$total_items = count($data);
		$data = array_slice($data,(($current_page-1)*$per_page),$per_page);
		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $total_items,                  //WE have to calculate the total number of items
			'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
			'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
		) );
	}    
}

class Polylang_String_Table extends WP_List_Table {
	function __construct() {             
		parent::__construct( array(
			'singular' => __('Strings translation','polylang'),
			'plural' => __('Strings translations','polylang'),
			'ajax'=> false)
		);        
	}

	function column_default($item, $column_name){
		return $item[$column_name];
	}

	function column_translations($item){
		$out = '';
		foreach($item['translations'] as $key=>$translation)
			$out .= sprintf('<div class="translation"><label for="%1$s-%2$s">%3$s</label><input name="translation[%1$s][%2$s]" id="%1$s-%2$s" value="%4$s" /></div>',
				esc_attr($key), esc_attr($item['row']), esc_html($key), esc_html($translation));

		return $out;
	}

  function get_columns(){
		return array(
			'name' => __('Name', 'polylang'),
			'string' => __('String', 'polylang'),
			'translations' => __('Translations', 'polylang'),
		);
	}

	function get_sortable_columns() {
		return array(
			'name' => array('name',false),
			'string' => array('string',false),
		);
	}
    
	function prepare_items($data = array()) {
		$per_page = 10; // 10 strings per page
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
        
		$this->_column_headers = array($columns, $hidden, $sortable);

		function usort_reorder($a,$b){		
			$orderby = $_REQUEST['orderby'];
			$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; // if no order, default to asc
			$result = strcmp($a[$orderby], $b[$orderby]); // determine sort order

			return ($order==='asc') ? $result : -$result; // send final sort direction to usort
		}
		if (!empty($_REQUEST['orderby'])) // no sort by default
			usort($data, 'usort_reorder');

		$current_page = $this->get_pagenum();
		$total_items = count($data);
		$data = array_slice($data,(($current_page-1)*$per_page),$per_page);
		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_items/$per_page)
		) );
	}    
}
?>

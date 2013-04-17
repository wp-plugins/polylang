<?php
class Polylang_Nav_Menu {

	public function __construct() {
		if ($GLOBALS['polylang']->is_admin) {
			add_action('admin_init', array(&$this, 'admin_init'), 20); // ater update
			add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
			add_action('wp_update_nav_menu_item', array( &$this, 'wp_update_nav_menu_item' ), 10, 3 );

			$theme = get_option( 'stylesheet' );
			add_filter("pre_update_option_theme_mods_$theme", array($this, 'update_nav_menu_locations'));

			// filter _wp_auto_add_pages_to_menu by language
			add_action('transition_post_status', array(&$this, 'auto_add_pages_to_menu'), 5, 3); // before _wp_auto_add_pages_to_menu
		}

		else {
			add_filter('wp_nav_menu_objects', array(&$this, 'wp_nav_menu_objects'));
			add_filter('nav_menu_link_attributes', array(&$this, 'nav_menu_link_attributes'), 10, 3);

			// filters menus locations by language
			add_filter('get_nav_menu', array($this, 'get_nav_menu'), 1);
		}
	}

	public function admin_init(){
		// FIXME is it possible to choose the order (after theme locations) ?
		add_meta_box('pll_lang_switch_box', __('Language switcher', 'polylang'), array( &$this, 'lang_switch' ), 'nav-menus', 'side', 'high');

		// create new nav menu locations except for all non-default language (only on admin side)
		global $_wp_registered_nav_menus, $polylang;
		if (isset($_wp_registered_nav_menus)) {
			foreach ($_wp_registered_nav_menus as $loc => $name)
				foreach ($polylang->get_languages_list() as $lang)
					$arr[$loc . (pll_default_language() == $lang->slug ? '' : '#' . $lang->slug)] = $name . ' ' . $lang->name;

			$_wp_registered_nav_menus = $arr;
		}
	}

	// language switcher metabox
	public function lang_switch() {
		global $_nav_menu_placeholder, $nav_menu_selected_id;
		$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;?>

		<div id="posttype-lang-switch" class="posttypediv">
			<div id="tabs-panel-lang-switch" class="tabs-panel tabs-panel-active">
				<ul id ="lang-switch-checklist" class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-object-id]" value="-1"> <?php _e('Language switcher', 'polylang'); ?>
						</label>
						<input type="hidden" class="menu-item-type" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type]" value="custom">
						<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-title]" value="<?php _e('Language switcher', 'polylang'); ?>">
						<input type="hidden" class="menu-item-url" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-url]" value="#">
	   				</li>
	   			</ul>
	   		</div>
	   		<p class="button-controls">
	   			<span class="add-to-menu">
	   				<input type="submit" <?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-to-menu right" value="Add to Menu" name="add-post-type-menu-item" id="submit-posttype-lang-switch">
	   				<span class="spinner"></span>
	   			</span>
	   		</p>
	   	</div><?php
	}

	// prepares javascript to modify the language switcher menu item
	function admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ('nav-menus' != $screen->base)
			return;

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script('pll_nav_menu', POLYLANG_URL .'/js/nav-menu'.$suffix.'.js', array('jquery'), POLYLANG_VERSION);

		// the strings for the options
		foreach (array_reverse($GLOBALS['polylang']->get_switcher_options('menu', 'string')) as $str)
			$data['strings'][] = $str;

		$data['strings'][] = __('Language switcher', 'polylang'); // the title

		$items = get_posts(array(
			'numberposts' => -1,
			'nopaging'    => true,
			'post_type'   => 'nav_menu_item',
			'fields'      => 'ids',
			'meta_query'  => array(array(
				'key'   => '_pll_menu_item',
				'value' => 1,
			))
		));

		$data['val'] = array();
		foreach ($items as $item) {
			foreach (array('hide_current','force_home','show_flags','show_names') as $opt)
				$data['val'][$item][$opt] = get_post_meta($item, '_pll_menu_item_'.$opt, true);
		}

		wp_localize_script('pll_nav_menu', 'pll_data', $data);
	}

	// save our menu item data
	function wp_update_nav_menu_item( $menu_id = 0, $menu_item_db_id = 0, $menu_item_data = array() ) {
		if (empty($_POST['menu-item-title'][$menu_item_db_id]) || $_POST['menu-item-title'][$menu_item_db_id] != __('Language switcher', 'polylang'))
			return;

		foreach (array('hide_current','force_home','show_flags','show_names') as $opt)
			update_post_meta($menu_item_db_id, '_pll_menu_item_'.$opt, empty($_POST['menu-item-'.$opt][$menu_item_db_id]) ? 0 : 1);

		update_post_meta($menu_item_db_id, '_pll_menu_item', 1);
	}

	// assign menu languages and translations based on locations
	function update_nav_menu_locations($mods) {
		global $polylang;
		$default = pll_default_language();

		$arr = array();
		foreach ($mods['nav_menu_locations'] as $loc => $menu) {
			if (!strpos($loc, '#'))
				$arr[$loc][$default] = $menu;
			elseif ($pos = strpos($loc, '#')) {
				$arr[substr($loc, 0, $pos)][substr($loc, $pos+1)] = $menu;
			}
		}

		// assign menus language and translations
		foreach ($arr as $loc => $translations) {
			foreach ($translations as $lang=>$menu) {
				$polylang->set_term_language($menu, $lang);
				$polylang->save_translations('term', $menu, $translations);
			}
		}

		return $mods;
	}

	// filter _wp_auto_add_pages_to_menu by language
	function auto_add_pages_to_menu( $new_status, $old_status, $post ) {
		if ('publish' != $new_status || 'publish' == $old_status || 'page' != $post->post_type || ! empty($post->post_parent) || !($lang = $this->get_post_language($post->ID)))
			return;

		// get all the menus in the post language
		$menus = get_terms('nav_menu', array('lang'=>$lang, 'fields'=>'ids', 'hide_empty'=>false));
		$menus = implode(',', $menus);

		add_filter('option_nav_menu_options', create_function('$a', "\$a['auto_add'] = array_intersect(\$a['auto_add'], array($menus)); return \$a;"));
	}

	// split the one item of backend in several items on frontend
	function wp_nav_menu_objects($items) {
		global $polylang;
		$new_items = array();

		foreach ($items as $key => $item) {
			if (get_post_meta($item->ID, '_pll_menu_item', true))
				$new_items = array_merge($new_items, $polylang->the_languages(array(
					'menu' => 1,
					'item' => $item,
					'show_flags' => get_post_meta($item->ID, '_pll_menu_item_show_flags', true),
					'show_names' => get_post_meta($item->ID, '_pll_menu_item_show_names', true),
					'force_home' => get_post_meta($item->ID, '_pll_menu_item_force_home', true),
					'hide_if_no_translation' => get_post_meta($item->ID, '_pll_menu_item_hide_current', true),
				)));
			else
				$new_items[] = $item;
		}

		unset($items); // free some memory
		return $new_items;
	}

	// hreflang attribute for the language switcher menu items
	function nav_menu_link_attributes($atts, $item, $args) {
		if (isset($item->lang))
			$atts['hreflang'] = $item->lang;
		return $atts;
	}

	// gets the menu in the correct language
	function get_nav_menu($term) {
		global $polylang;
		remove_filter('get_nav_menu', array($this, 'get_nav_menu'), 1); // avoid infinite loop
		$term = get_term(pll_get_term($term->term_id), 'nav_menu');
		add_filter('get_nav_menu', array($this, 'get_nav_menu'), 1);
		return $term;
	}
}

new Polylang_Nav_Menu();

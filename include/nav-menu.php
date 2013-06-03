<?php
class Polylang_Nav_Menu {
	public function __construct() {
		if ($GLOBALS['polylang']->is_admin) {
			// integration in the WP menu interface
			add_action('admin_init', array(&$this, 'admin_init'), 20); // ater update

			// remove the customize menu section as it is unusable with Polylang
			add_action('customize_register', array(&$this, 'customize_register'), 20); // since WP 3.4
		}
		else {
			// split the language switcher menu item in several language menu items
			add_filter('wp_get_nav_menu_items', array(&$this, 'wp_get_nav_menu_items'));
			add_filter('wp_nav_menu_objects', array(&$this, 'wp_nav_menu_objects'));
			add_filter('nav_menu_link_attributes', array(&$this, 'nav_menu_link_attributes'), 10, 3);

			// filters menus locations by language
			add_filter('get_nav_menu', array($this, 'get_nav_menu'), 1);
		}
	}

	// add the language switcher metabox and create new nav menu locations
	public function admin_init(){
		global $_wp_registered_nav_menus, $polylang;

		if (!($languages = $polylang->get_languages_list()))
			return;

		add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
		add_action('wp_update_nav_menu_item', array(&$this, 'wp_update_nav_menu_item'), 10, 3);
		add_filter('wp_get_nav_menu_items', array(&$this, 'translate_switcher_title'));

		// translation of menus based on chosen locations
		$theme = get_option( 'stylesheet' );
		add_filter("pre_update_option_theme_mods_$theme", array($this, 'update_nav_menu_locations'));

		// filter _wp_auto_add_pages_to_menu by language
		add_action('transition_post_status', array(&$this, 'auto_add_pages_to_menu'), 5, 3); // before _wp_auto_add_pages_to_menu

		// FIXME is it possible to choose the order (after theme locations in WP3.5 and older) ?
		add_meta_box('pll_lang_switch_box', __('Language switcher', 'polylang'), array( &$this, 'lang_switch' ), 'nav-menus', 'side', 'high');

		// create new nav menu locations except for all non-default language (only on admin side)
		if (isset($_wp_registered_nav_menus)) {
			foreach ($_wp_registered_nav_menus as $loc => $name)
				foreach ($languages as $lang)
					$arr[$loc . (pll_default_language() == $lang->slug ? '' : '#' . $lang->slug)] = $name . ' ' . $lang->name;

			$_wp_registered_nav_menus = $arr;
		}
	}

	// language switcher metabox
	// The checkbox and all hidden fields are important
	// thanks to John Morris for his very interesting post http://www.johnmorrisonline.com/how-to-add-a-fully-functional-custom-meta-box-to-wordpress-navigation-menus/
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
						<input type="hidden" class="menu-item-url" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-url]" value="#pll_switcher">
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

		// get all language switcher menu items
		$items = get_posts(array(
			'numberposts' => -1,
			'nopaging'    => true,
			'post_type'   => 'nav_menu_item',
			'fields'      => 'ids',
			'meta_key'    => '_pll_menu_item'
		));

		// the options values for the language switcher
		$data['val'] = array();
		foreach ($items as $item)
			$data['val'][$item] = get_post_meta($item, '_pll_menu_item', true);

		// send all these data to javascript
		wp_localize_script('pll_nav_menu', 'pll_data', $data);
	}

	// save our menu item options
	function wp_update_nav_menu_item( $menu_id = 0, $menu_item_db_id = 0, $menu_item_data = array() ) {
		if (empty($_POST['menu-item-url'][$menu_item_db_id]) || $_POST['menu-item-url'][$menu_item_db_id] != '#pll_switcher')
			return;

		$options = array('hide_current' => 0,'force_home' => 0 ,'show_flags' => 0 ,'show_names' => 1); // default values
		// our jQuery form has not been displayed
		if (empty($_POST['menu-item-pll-detect'][$menu_item_db_id])) {
			if (!get_post_meta($menu_item_db_id, '_pll_menu_item', true)) // our options were never saved
				update_post_meta($menu_item_db_id, '_pll_menu_item', $options);
		}
		else {
			foreach ($options as $opt=>$v)
				$options[$opt] = empty($_POST['menu-item-'.$opt][$menu_item_db_id]) ? 0 : 1;
			update_post_meta($menu_item_db_id, '_pll_menu_item', $options); // allow us to easily identify our nav menu item
		}

	}

	// translates the language switcher menu items title in case the user swirhces the admin language
	function translate_switcher_title($items) {
		foreach ($items as $item)
			if ($item->url == '#pll_switcher')
				$item->post_title = __('Language switcher', 'polylang');
		return $items;
	}

	// assign menu languages and translations based on locations
	function update_nav_menu_locations($mods) {
		if (isset($mods['nav_menu_locations'])) {
			global $polylang;
			$default = pll_default_language();
			$arr = array();

			// extract language and menu from locations
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
		}
		return $mods;
	}

	// filter _wp_auto_add_pages_to_menu by language
	function auto_add_pages_to_menu( $new_status, $old_status, $post ) {
		if ('publish' != $new_status || 'publish' == $old_status || 'page' != $post->post_type || ! empty($post->post_parent) || !($lang = $GLOBALS['polylang']->get_post_language($post->ID)))
			return;

		// get all the menus in the post language
		$menus = get_terms('nav_menu', array('lang'=>$lang, 'fields'=>'ids', 'hide_empty'=>false));
		$menus = implode(',', $menus);

		add_filter('option_nav_menu_options', create_function('$a', "\$a['auto_add'] = array_intersect(\$a['auto_add'], array($menus)); return \$a;"));
	}

	// FIXME remove the customize menu section as it is currently unusable with Polylang
	function customize_register() {
		$GLOBALS['wp_customize']->remove_section('nav'); // since WP 3.4
	}


	// split the one item of backend in several items on frontend
	// take care to menu_order as it is used later in wp_nav_menu
	function wp_get_nav_menu_items($items) {
		global $polylang;
		$new_items = array();
		$offset = 0;

		foreach ($items as $key => $item) {
			if ($options = get_post_meta($item->ID, '_pll_menu_item', true)) {
				extract($options);
				$i = 0;

				foreach ($polylang->the_languages(array_merge(array('raw' => 1), $options)) as $language) {
					extract($language);
					$lang_item = clone $item;
					$lang_item->title = $show_flags && $show_names ? $flag.'&nbsp;'.$name : ($show_flags ? $flag : $name);
					$lang_item->url = $url;
					$lang_item->lang = $slug; // save this for use in nav_menu_link_attributes
					$lang_item->classes = $classes;
					$lang_item->menu_order += $offset + $i++;
					$new_items[] = $lang_item;
				}
				$offset += $i - 1;
			}
			else {
				$item->menu_order += $offset;
				$new_items[] = $item;
			}
		}

		return $new_items;
	}

	function get_ancestors($item) {
		$ids = array();
		$_anc_id = (int) $item->db_id;
		while(($_anc_id = get_post_meta($_anc_id, '_menu_item_menu_item_parent', true)) && !in_array($_anc_id, $ids))
			$ids[] = $_anc_id;
		return $ids;
	}

	// remove current-menu and current-menu-ancestor classes to lang switcher when not on the home page
	function wp_nav_menu_objects($items) {
		$r_ids = $k_ids = array();

		foreach ($items as $item) {
			if (in_array('current-lang', $item->classes)) {
				$item->classes = array_diff($item->classes, array('current-menu-item'));
				$r_ids = array_merge($r_ids, $this->get_ancestors($item)); // remove the classes for these ancestors
			}
			elseif (in_array('current-menu-item', $item->classes))
				$k_ids = array_merge($k_ids, $this->get_ancestors($item)); // keep the classes for these ancestors
		}

		$r_ids = array_diff($r_ids, $k_ids);

		foreach ($items as $item) {
			if (in_array($item->db_id, $r_ids))
				$item->classes = array_diff($item->classes, array('current-menu-ancestor', 'current-menu-parent', 'current_page_parent', 'current_page_ancestor'));
		}

		return $items;
	}

	// hreflang attribute for the language switcher menu items
	// available since WP3.6
	function nav_menu_link_attributes($atts, $item, $args) {
		if (isset($item->lang))
			$atts['hreflang'] = $item->lang;
		return $atts;
	}

	// get the menu in the correct language
	// avoid infinite loop and http://core.trac.wordpress.org/ticket/9968
	function get_nav_menu($term) {
		static $once = false;
		if (!$once && $tr = pll_get_term($term->term_id)) {
			$once = true; // breaks the loop
			$term = get_term($tr, 'nav_menu');
			$once = false; // for the next call
		}

		return $term;
	}
}

new Polylang_Nav_Menu();

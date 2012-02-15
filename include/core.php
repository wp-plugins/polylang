<?php
// filters posts and terms by language, rewrites links to include the language, etc...
// used only for frontend
class Polylang_Core extends Polylang_base {
	private $curlang; // current language
	private $default_locale;
	private $list_textdomains = array(); // all text domains
	private $search_form_filter = false; // did we pass our get_search_form filter ?
	private $page_id;

	// options often needed
	private $page_for_posts;
	private $page_on_front;

	function __construct() {
		parent::__construct();

		// init options often needed
		$this->page_for_posts = get_option('page_for_posts');
		$this->page_on_front = get_option('page_on_front');

		// text domain management
		add_action('init', array(&$this, 'init'));
		add_filter('override_load_textdomain', array(&$this, 'mofile'), 10, 3);
		add_action('wp', array(&$this, 'load_textdomains'));

		// filters posts according to the language
		add_filter('pre_get_posts', array(&$this, 'pre_get_posts'));
		add_filter('wp', array(&$this, 'post_get_posts'));
	}

	// set these filters and actions only once the current language has been defined
	function add_language_filters() {
		// filters the WordPress locale
		add_filter('locale', array(&$this, 'get_locale'));

		// filters categories and post tags by language
		add_filter('terms_clauses', array(&$this, 'terms_clauses'), 10, 3);

		// meta in the html head section
		add_action('wp_head', array(&$this, 'wp_head'));

		// modifies the page link in case the front page is not in the default language
		add_filter('page_link', array(&$this, 'page_link'), 10, 2);

		// prevents redirection of the homepage
		add_filter('redirect_canonical', array(&$this, 'redirect_canonical'), 10, 2);

		// adds javascript at the end of the document
		add_action('wp_print_footer_scripts', array(&$this, 'wp_print_footer_scripts'));

		// adds the language information in the search form
		// low priority in case the search form is created using the same filter as described in http://codex.wordpress.org/Function_Reference/get_search_form
		add_filter('get_search_form', array(&$this, 'get_search_form'), 99);

		// filters the pages according to the current language in wp_list_pages
		add_filter('wp_list_pages_excludes', array(&$this, 'wp_list_pages_excludes'));

		// filters the comments according to the current language
		add_filter('comments_clauses', array(&$this, 'comments_clauses'), 10, 2);

		// rewrites archives, next and previous post links to filter them by language
		foreach (array('getarchives', 'get_previous_post', 'get_next_post') as $filter)
			foreach (array('_join', '_where') as $clause)
				add_filter($filter.$clause, array(&$this, 'posts'.$clause));

		// rewrites author and date links to filter them by language
		foreach (array('feed_link', 'author_link', 'post_type_archive_link', 'year_link', 'month_link', 'day_link') as $filter)
			add_filter($filter, array(&$this, 'archive_link'));

		$this->add_post_term_link_filters(); // these filters are in base as they may be used on admin side too

		// filters the nav menus according to the current language
		add_filter('theme_mod_nav_menu_locations', array(&$this, 'nav_menu_locations'));
		add_filter('wp_nav_menu_args', array(&$this, 'wp_nav_menu_args'));
		add_filter('wp_nav_menu_items', array(&$this, 'wp_nav_menu_items'), 10, 2);
		add_filter('wp_nav_menu_objects', array(&$this, 'wp_nav_menu_objects'), 10, 2);
		add_filter('wp_page_menu', array(&$this, 'wp_page_menu'), 10, 2);

		// filters the widgets according to the current language
		add_filter('widget_display_callback', array(&$this, 'widget_display_callback'), 10, 3);

		// strings translation (must be applied before WordPress applies its default formatting filters)
		add_filter('widget_title', array(&$this, 'widget_title'), 1);
		add_filter('bloginfo', array(&$this, 'bloginfo'), 1, 2);
		add_filter('get_bloginfo_rss', array(&$this, 'bloginfo'), 1, 2);

		// loads front page template on translated front page
		add_filter('template_include', array(&$this, 'template_include'));

		// modifies the home url
		add_filter('home_url', array(&$this, 'home_url'));

		// allows a new value for the 'show' parameter to display the homepage url according to the current language
		// FIXME Backward compatibility for versions < 0.5 -> replaced by a filter on home_url
		add_filter('bloginfo_url', array(&$this, 'bloginfo_url'), 10, 2);

		// Template tag: displays the language switcher
		// FIXME Backward compatibility for versions < 0.5 -> replaced by pll_the_languages
		add_action('the_languages', array(&$this, 'the_languages'));
	}

	// returns the language according to browser preference or the default language
	function get_preferred_language() {
		// check first is the user was already browsing this site
		if (isset($_COOKIE['wordpress_polylang']))
			return $this->get_language($_COOKIE['wordpress_polylang']);

		// sets the browsing language according to the browser preferences
		// code adapted from http://www.thefutureoftheweb.com/blog/use-accept-language-header
		if ($this->options['browser']) {
			$accept_langs = array();

			if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
				// break up string into pieces (languages and q factors)
				preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

				if (count($lang_parse[1])) {
					// NOTE: array_combine => PHP5
					$accept_langs = array_combine($lang_parse[1], $lang_parse[4]); // create a list like "en" => 0.8
					// set default to 1 for any without q factor
					foreach ($accept_langs as $accept_lang => $val) {
						if ($val === '') $accept_langs[$accept_lang] = 1;
					}
					arsort($accept_langs, SORT_NUMERIC); // sort list based on value
				}
			}

			// looks through sorted list and use first one that matches our language list
			$listlanguages = $this->get_languages_list(true); // hides languages with no post
			foreach ($accept_langs as $accept_lang => $val) {
				foreach ($listlanguages as $language) {
					if (strpos($accept_lang, $language->slug) === 0 && !isset($pref_lang)) {
						$pref_lang = $language;
					}
				}
			}
		} // options['browser']

		// return default if there is no preferences in the browser or preferences does not match our languages or it is requested not to use the browser preference
		return isset($pref_lang) ? $pref_lang : $this->get_language($this->options['default_lang']);
	}

	// returns the current language
	function get_current_language() {

		if ($this->curlang)
			return $this->curlang;

		// no language set for 404 and attachment
		if (is_404() || is_attachment())
			return $this->get_preferred_language();

		if ($var = get_query_var('lang'))
			$lang = $this->get_language($var);

		elseif ((is_single() || is_page()) && ( ($var = get_queried_object_id()) || ($var = get_query_var('p')) || ($var = get_query_var('page_id')) ))
			$lang = $this->get_post_language($var);

		else {
			foreach (get_taxonomies(array('show_ui'=>true)) as $taxonomy) {
				if ($var = get_query_var(get_taxonomy($taxonomy)->query_var))
					$lang = $this->get_term_language($var, $taxonomy);
			}
		}
		return (isset($lang)) ? $lang : false;
	}

	// save the default locale before we start any language manipulation
	function init() {
		$this->default_locale = get_locale();
	}

	// returns the locale based on current language
	function get_locale($locale) {
		return $this->curlang->description;
	}

	// saves all text domains in a table for later usage
	function mofile($bool, $domain, $mofile) {
		$this->list_textdomains[] = array ('mo' => $mofile, 'domain' => $domain);
		return true; // prevents WP loading text domains as we will load them all later
	}

	// NOTE: I believe there are two ways for a plugin to force the WP language
	// as done by xili_language and here: load text domains and reinitialize wp_locale with the action 'wp'
	// as done by qtranslate: define the locale with the action 'plugins_loaded', but in this case, the language must be specified in the url.
	function load_textdomains() {
		// sets the current language
		if (!($this->curlang = $this->get_current_language()))
			return; // something went wrong

		// set a cookie to remember the language. check headers have not been sent to avoid ugly error
		if (!headers_sent())
			setcookie('wordpress_polylang', $this->curlang->slug, time() + 31536000 /* 1 year */, COOKIEPATH, COOKIE_DOMAIN);

		// set all our language filters and actions
		$this->add_language_filters();

		// our override_load_textdomain filter has done its job. let's remove it before calling load_textdomain
		remove_filter('override_load_textdomain', array(&$this, 'mofile'));

		// now we can load text domains with the right language
		$new_locale = get_locale();
		foreach ($this->list_textdomains as $textdomain)
			load_textdomain( $textdomain['domain'], str_replace($this->default_locale, $new_locale, $textdomain['mo']));

		// and finally load user defined strings (check first that base64_decode is not disabled)
		if (function_exists('base64_decode')) {
			global $l10n;
			$mo = new MO();
			$reader = new POMO_StringReader(base64_decode(get_option('polylang_mo'.$this->curlang->term_id)));
			$mo->import_from_reader($reader);
			$l10n['pll_string'] = &$mo;
		}

		// reinitializes wp_locale for weekdays and months, as well as for text direction
		global $wp_locale;
		$wp_locale->init();
		$wp_locale->text_direction = get_metadata('term', $this->curlang->term_id, '_rtl', true) ? 'rtl' : 'ltr';
	}

	// filters posts according to the language
	function pre_get_posts($query) {
		$qvars = $query->query_vars;

		// detect our exclude pages query and returns to avoid conflicts
		// this test should be sufficient
		if (isset($qvars['tax_query'][0]) && isset($qvars['tax_query'][0]['taxonomy']) && isset($qvars['tax_query'][0]['operator']))
			return;

		// homepage is requested, let's set the language
		if (empty($query->query)) {
			// find out the language
			if ($this->options['hide_default'] && isset($_COOKIE['wordpress_polylang']))
				$this->curlang = $this->get_language($this->options['default_lang']);
			else
				$this->curlang = $this->get_preferred_language(); // sets the language according to browser preference or default language

			// we are already on the right page
			if ($this->options['default_lang'] == $this->curlang->slug && $this->options['hide_default']) {
				if ($this->page_on_front && $link_id = $this->get_post($this->page_on_front, $this->curlang))
					$query->set('page_id', $link_id);
				else
					$query->set('lang', $this->curlang->slug);
			}
			// redirect to the home page in the right language
			else {
				if ($this->page_on_front && $link_id = $this->get_post($this->page_on_front, $this->curlang))
					$url = $this->add_language_to_link(_get_page_link($link_id), $this->curlang);
				else
					$url = $this->add_language_to_link(home_url(), $this->curlang);

				wp_redirect($url);
				exit;
			}
		}

		// sets is_home on translated home page when it displays posts
		// is_home must be true on page 2, 3... too
		if (!$this->page_on_front && $query->is_tax && (count($query->query) == 1 || (is_paged() && count($query->query) == 2))) {
			$query->is_home = true;
			$query->is_tax = false;
		}

		// sets the language for posts page in case the front page displays a static page
		if ($this->page_for_posts) {
			// If permalinks are used, WordPress does set and use $query->queried_object_id and sets $query->query_vars['page_id'] to 0
			// and does set and use $query->query_vars['page_id'] if permalinks are not used :(
			if (isset($query->queried_object_id))
				$this->page_id = $query->queried_object_id;
			elseif (isset($qvars['page_id']))
				$this->page_id = $qvars['page_id'];

			if (isset($this->page_id) && $this->page_id && $this->get_post($this->page_id, $this->get_post_language($this->page_for_posts)) == $this->page_for_posts) {
				$query->set('lang',$this->get_post_language($this->page_id)->slug);
				$query->queried_object_id = $this->page_for_posts;
				$query->query_vars['page_id'] = $this->page_for_posts; // FIXME the trick works but breaks .current-menu-item and .current_page_item
				$query->is_page = false;
				$query->is_home = true;
				$query->is_posts_page = true;
				$query->is_singular = false;
			}
		}
	
		// FIXME to generalize as I probably forget things
		$is_archive = (count($query->query) == 1 && isset($qvars['paged']) && $qvars['paged']) ||
			(isset($qvars['m']) && $qvars['m']) ||
			(isset($qvars['author']) && $qvars['author']) ||
			(isset($qvars['post_type']) && is_post_type_archive() && !in_array($qvars['post_type'], get_post_types(array('show_ui' => false))));
	
		// sets 404 when the language is not set for archives needing the language in the url
		if (!$this->options['hide_default'] && !isset($qvars['lang']) && !$GLOBALS['wp_rewrite']->using_permalinks() && $is_archive)		
			$query->set_404();

		// sets the language in case we hide the default language
		if ($this->options['hide_default'] && !isset($qvars['lang']) && ($is_archive || (count($query->query) == 1 && isset($qvars['feed']) && $qvars['feed']) ))
			$query->set('lang', $this->options['default_lang']);

		// allow filtering recent posts by the current language
		// take care not to break queries for non visible post types such as nav_menu_items, attachments...
		if ($query->is_home && $this->curlang && (!isset($qvars['post_type']) || !in_array($qvars['post_type'], get_post_types(array('show_ui' => false)))))
			$query->set('lang', $this->curlang->slug);

		// remove pages query when the language is set unless we do a search
		// FIXME is only search broken by this ?
		if (isset($qvars['lang']) && $qvars['lang'] && !isset($qvars['post_type']) && !is_search())
			$query->set('post_type', 'post');

		// unset the is_archive flag for language pages to prevent loading the archive template
		// keep archive flag for comment feed otherwise the language filter does not work
		if (isset($qvars['lang']) && $qvars['lang'] && !is_comment_feed() &&
			!is_post_type_archive() && !is_date() && !is_author() && !is_category() && !is_tag() && !is_tax('post_format'))
			$query->is_archive = false;

		// unset the is_tax flag for authors pages
		// FIXME Probably I should do this for other cases
		if (isset($qvars['lang']) && $qvars['lang'] && is_author())
			$query->is_tax = false;

		// sets a language for theme preview
		if (isset($_GET['preview']))
			$query->set('lang', $this->options['default_lang']);

		if (PLL_DISPLAY_ALL) {
			// add posts with no language set
			$query->query_vars['tax_query'] = array(
				'relation' => 'OR', array(
					'taxonomy'=> 'language',
					'terms'=> get_terms('language', array('fields'=>'ids')),
					'operator'=>'NOT IN'));
		}
	}

	// used only for page for posts: after posts have been selected, come back to right page_id
	// may be useful for some plugins ?
	function post_get_posts() {
		if ($this->page_id) {
			global $wp_query;
			$wp_query->queried_object_id = $this->page_id;
			$wp_query->query_vars['page_id'] = $this->page_id;
		}
	}

	// filters categories and post tags by language when needed
	function terms_clauses($clauses, $taxonomies, $args) {
		// does nothing except on taxonomies which have show_ui set to 1 (includes category and post_tags)
		foreach ($taxonomies as $tax) {
			if (!get_taxonomy($tax)->show_ui)
				return $clauses;
		}

		// adds our clauses to filter by current language
		return $this->_terms_clauses($clauses, $this->curlang, PLL_DISPLAY_ALL);
	}

	// meta in the html head section
	function wp_head() {
		// outputs references to translated pages (if exists) in the html head section
		foreach ($this->get_languages_list() as $language) {
			if ($language->slug != $this->curlang->slug && $url = $this->get_translation_url($language))
				printf("<link hreflang='%s' href='%s' rel='alternate' />\n", esc_attr($language->slug), esc_url($url));
		}
	}

	// modifies the page link in case the front page is not in the default language
	function page_link($link, $id) {
		if ($this->page_on_front && $id == $this->get_post($this->page_on_front, $this->options['default_lang']))
			return home_url('/');
		return _get_page_link($id);
	}

	// prevents redirection of the homepage
	function redirect_canonical($redirect_url, $requested_url) {
		return $requested_url == _get_page_link($this->page_on_front) ? false : $redirect_url;
	}

	// adds some javascript workaround knowing it's not perfect...
	function wp_print_footer_scripts() {
		$js = '';

		// modifies the search form since filtering get_search_form won't work if the template uses searchform.php
		// don't use directly e[0] just in case there is somewhere else an element named 's'
		// check before if the hidden input has not already been introduced by get_search_form
		if (!$this->search_form_filter) {
			$lang = esc_js($this->curlang->slug);
			$js .= "e = document.getElementsByName('s');
			for (i = 0; i < e.length; i++) {
				if (e[i] == '[object HTMLInputElement]') {
					var ih = document.createElement('input');
					ih.type = 'hidden';
					ih.name = 'lang';
					ih.value = '$lang';
					e[i].parentNode.appendChild(ih);
				}
			}";
		}

		if ($js)
			echo "<script type='text/javascript'>" .$js. "</script>";
	}

	// adds the language information in the search form
	// does not work if searchform.php is used
	function get_search_form($form) {
		if ($form) {
			$this->search_form_filter = true;
			$form = str_replace('</form>', '<input type="hidden" name="lang" value="'.esc_attr($this->curlang->slug).'" /></form>', $form);
		}
		return $form;
	}

	// excludes pages which are not in the current language for wp_list_pages
	// useful for the pages widget
	function wp_list_pages_excludes($pages) {
		return array_merge($pages, $this->exclude_pages($this->curlang->term_id));
	}

	// filters the comments according to the current language mainly for the recent comments widget
	function comments_clauses($clauses, $comment_query) {
		// first test if wp_posts.ID already available in the query
		if (strpos($clauses['join'], '.ID')) {
			global $wpdb;
			$clauses['join'] .= $wpdb->prepare(" INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID");
			$clauses['where'] .= $wpdb->prepare(" AND pll_tr.term_taxonomy_id = %d", $this->curlang->term_taxonomy_id);
		}
		return $clauses;
	}

	// modifies the sql request for wp_get_archives an get_adjacent_post to filter by the current language
	function posts_join($sql) {
		global $wpdb;
		return $sql . $wpdb->prepare(" INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID");
	}

	// modifies the sql request for wp_get_archives and get_adjacent_post to filter by the current language
	function posts_where($sql) {
		global $wpdb;
		return $sql . $wpdb->prepare(" AND pll_tr.term_taxonomy_id = %d", $this->curlang->term_taxonomy_id);
	}

	// modifies the author and date links to add the language parameter (as well as feed link)
	function archive_link($link) {
		return $this->add_language_to_link($link, $this->curlang);
	}

	// returns the url of the translation (if exists) of the current page
	function get_translation_url($language) {
		global $wp_query, $wp_rewrite;
		$qvars = $wp_query->query;
		$hide = $this->options['default_lang'] == $language->slug && $this->options['hide_default'];

		// is_single is set to 1 for attachment but no language is set
		if (is_single() && !is_attachment() && $id = $this->get_post($wp_query->queried_object_id, $language))
			$url = get_permalink($id);

		// page for posts
		elseif (get_option('show_on_front') == 'page' && isset($wp_query->queried_object_id) &&
			$this->get_post($wp_query->queried_object_id, $language) == ($id = $this->get_post($this->page_for_posts, $language)))
			$url = get_permalink($id);

		elseif (is_page() && $id = $this->get_post($wp_query->queried_object_id, $language))
			$url = $hide && $id == $this->get_post($this->page_on_front, $language) ? $this->home : _get_page_link($id);

		elseif (!is_tax('post_format') && !is_tax('language') && (is_category() || is_tag() || is_tax ()) ) {
			$term = get_queried_object();
			$lang = $this->get_term_language($term->term_id);
			$taxonomy = $term->taxonomy;

			if ($language->slug == $lang->slug)
				$url = get_term_link($term, $taxonomy); // self link
			elseif ($link_id = $this->get_translation('term', $term->term_id, $language))
				$url = get_term_link(get_term($link_id, $taxonomy), $taxonomy);
		}

		// don't test if there are existing translations before creating the url as it would be very expensive in sql queries
		elseif (is_archive()) {
			if ($wp_rewrite->using_permalinks()) {
				$base = $this->options['rewrite'] ? '/' : '/language/';
				$base = $hide ? '' : $base.$language->slug;
				$base = $this->home.$base.'/';

				if (is_author())
					$url = esc_url($base.'author/'.$qvars['author_name'].'/');

				if (is_year())
					$url = esc_url($base.$qvars['year'].'/');

				if (is_month())
					$url = esc_url($base.$qvars['year'].'/'.$qvars['monthnum'].'/');

				if (is_day())
					$url = esc_url($base.$qvars['year'].'/'.$qvars['monthnum'].'/'.$qvars['day'].'/');

				if (is_tax('post_format'))
					$url = esc_url($base.'type/'.$qvars['post_format'].'/');

				if (is_post_type_archive())
					$url = esc_url($base.$qvars['post_type'].'/');
			}
			else
				$url = $hide ? remove_query_arg('lang') : add_query_arg('lang', $language->slug);
		}

		elseif (is_home() || is_tax('language') )
			$url = $hide ? $this->home : get_term_link($language, 'language');

		return isset($url) ? $url : null;
	}

	// filters the nav menus according to the current language when called from get_nav_menu_locations()
	// mainly for Artisteer generated themes
	function nav_menu_locations($menus) {
		if (is_array($menus)) {
			foreach($menus as $location => $menu) {
				$menu_lang = get_option('polylang_nav_menus');
				if (isset($menu_lang[$location][$this->curlang->slug]))
					$menus[$location] = $menu_lang[$location][$this->curlang->slug];
			}
		}
		return $menus;
	}

	// filters the nav menus according to the current language when called from wp_nav_menus
	function wp_nav_menu_args($args) {
		if (!$args['menu'] && $args['theme_location']) {
			$menu_lang = get_option('polylang_nav_menus');
			if (isset($menu_lang[$args['theme_location']][$this->curlang->slug]))
				$args['menu'] = $menu_lang[$args['theme_location']][$this->curlang->slug];
		}
		return $args;
	}

	// adds the language switcher at the end of the menu
	function wp_nav_menu_items($items, $args) {
		$menu_lang = get_option('polylang_nav_menus');
		return isset($args->theme_location) && isset($menu_lang[$args->theme_location]['switcher']) && $menu_lang[$args->theme_location]['switcher'] ?
			$items . $this->the_languages(array_merge($menu_lang[$args->theme_location], array('menu' => 1, 'echo' => 0))) : $items;
	}

	// corrects some issues on front page and post pages nav menus items
	function wp_nav_menu_objects($menu_items, $args) {
		global $wp_query;

		// corrects classes in the menu for posts page
		// FIXME check for child pages
		if ($wp_query->is_posts_page) {
			$classes = array('current-menu-item', 'current_page_item', 'current_page_parent');
			foreach($menu_items as $item) {
				$item->classes = array_diff($item->classes, $classes);
				if ($item->object_id == $this->get_post($this->page_for_posts, $this->curlang))
					$item->classes = array_merge($item->classes, $classes);
			}
		}
		return $menu_items;
	}

	// corrects the output of the function for translated home
	function wp_page_menu($menu, $args) {
		global $wp_query;

		// add current_page_item class to posts page
		if ($wp_query->is_posts_page) {
			$id = $this->get_post($this->page_for_posts, $this->curlang);
			$menu = str_replace('<li class="page_item page-item-'.$id.'">', '<li class="page_item page-item-'.$id.' current_page_item">', $menu);
		}

		// add current_page_item class to home page
		// normally only the homepage has no class. note the space in <li >
		if ($this->is_front_page() && !is_paged())
			$menu = str_replace('<li >', '<li class="current_page_item">', $menu);

		// remove the 2nd occurrence of homepage (when translated)
		if ($this->page_on_front) {
			$id = $this->get_post($this->page_on_front, $this->curlang);
			$url = _get_page_link($id);
			$title = apply_filters('the_title', get_page($id)->post_title);
			$menu = str_replace('<li class="page_item page-item-'.$id.'"><a href="'.$url.'">'.$title.'</a></li>', '', $menu);
			$menu = str_replace('<li class="page_item page-item-'.$id.' current_page_item"><a href="'.$url.'">'.$title.'</a></li>', '', $menu);
		}
		return $menu;
	}

	// filters the widgets according to the current language
	function widget_display_callback($instance, $widget, $args) {
		$widget_lang = get_option('polylang_widgets');
		// don't display if a language filter is set and this is not the current one
		return isset($widget_lang[$widget->id]) && $widget_lang[$widget->id] && $widget_lang[$widget->id] != $this->curlang->slug ? false : $instance;
	}

	// translates widget titles
	function widget_title($title) {
		return __($title, 'pll_string');
	}

	// translates site title and tagline
	function bloginfo($output, $show) {
		return in_array($show, array('', 'name', 'description')) ? __($output, 'pll_string') : $output;
	}

	// acts as is_front_page but knows about translated front page
	function is_front_page() {
		return ('posts' == get_option('show_on_front') && is_home()) ||
			('page' == get_option('show_on_front') && $this->page_on_front && is_page($this->get_post($this->page_on_front, $this->get_current_language()))) ||
			(is_tax('language') && !is_archive());
	}

	// loads front page template on translated front page
	function template_include($template) {
		return ($this->is_front_page() && $front_page = get_front_page_template()) ? $front_page : $template;
	}

	// filters the home url to get the right language
	function home_url($url) {
		if ( !(did_action('template_redirect') && rtrim($url,'/') == rtrim($this->home,'/')) )
			return $url;

		// don't like this but at least for WP_Widget_Categories::widget, it seems to be the only solution
		// FIXME are there other exceptions ?
		foreach (debug_backtrace() as $trace) {
			$exceptions = $trace['function'] == 'get_pagenum_link' ||
				$trace['function'] == 'get_author_posts_url' ||
				$trace['function'] == 'get_search_form' ||
				(isset($trace['file']) && strpos($trace['file'], 'searchform.php')) ||
				($trace['function'] == 'widget' && $trace['class'] == 'WP_Widget_Categories');
			if ($exceptions)
				return $url;
		}
		return $this->get_home_url($this->curlang);
	}

	// returns the home url in the right language
	function get_home_url($language) {
		if ($this->options['default_lang'] == $language->slug && $this->options['hide_default'])
			return trailingslashit($this->home);

		// a static page is used as front page
		if ($this->page_on_front && $id = $this->get_post($this->page_on_front, $language))
			$url = _get_page_link($id);

		return isset($url) ? $url : get_term_link($language, 'language');
	}

	// adds 'lang_url' as possible value to the 'show' parameter of bloginfo to display the home url in the correct language
	// FIXME not tested
	function bloginfo_url($output, $show) {
		if ($show == 'lang_url') {
			$url = $this->get_home_url($this->curlang);
			$output = isset($url) ? $url : $this->home;
		}
		return $output;
	}

	// displays the language switcher
	function the_languages($args = '') {
		$defaults = array(
			'dropdown' => 0, // display as list and not as dropdown
			'echo' => 1, // echoes the list
			'hide_if_empty' => 1, // hides languages with no posts (or pages)
			'menu' => '0', // not for nav menu
			'show_flags' => 0, // don't show flags
			'show_names' => 1, // show language names
			'force_home' => 0, // tries to find a translation (available only if display != dropdown)
			'hide_if_no_translation' => 0, // don't hide the link if there is no translation
			'hide_current' => 0, // don't hide current language
		);
		extract(wp_parse_args($args, $defaults));

		if ($dropdown)
			$output = $this->dropdown_languages(array('hide_empty' => $hide_if_empty, 'selected' => $this->curlang->slug));

		else {
			$output = '';

			foreach ($this->get_languages_list($hide_if_empty) as $language) {
				// hide current language
				if ($this->curlang->term_id == $language->term_id && $hide_current)
					continue;

				$url = $force_home ? null : $this->get_translation_url($language);
				$url = apply_filters('pll_the_language_link', $url, $language->slug, $language->description);

				// hide if no translation exists
				if (!isset($url) && $hide_if_no_translation)
					continue;

				$url = isset($url) ? $url : $this->get_home_url($language); // if the page is not translated, link to the home page

				$class = 'lang-item lang-item-'.esc_attr($language->term_id);
				$class .= $language->term_id == $this->curlang->term_id ? ' current-lang' : '';
				$class .= $menu ? ' menu-item' : '';

				$flag = $show_flags ? $this->get_flag($language) : '';
				$name = $show_names || !$show_flags ? esc_html($language->name) : '';

				$output .= sprintf("<li class='%s'><a hreflang='%s' href='%s'>%s</a></li>\n",
					$class, esc_attr($language->slug), esc_url($url), $show_flags && $show_names ? $flag.'&nbsp;'.$name : $flag.$name);
			}
		}

		if ($echo)
			echo $output;
		else
			return $output;
	}
}
?>

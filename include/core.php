<?php
// filters posts and terms by language, rewrites links to include the language, etc...
// used only for frontend
class Polylang_Core extends Polylang_base {
	private $curlang; // current language
	private $default_locale;
	private $list_textdomains = array(); // all text domains
	private $search_form_filter = false; // did we pass our get_search_form filter ?

	// options often needed
	private $home;
	private $options;
	private $page_for_posts;
	private $page_on_front;

	function __construct() {

		// options often needed
		$this->options = get_option('polylang');
		$this->home = get_option('home');
		$this->page_for_posts = get_option('page_for_posts');
		$this->page_on_front = get_option('page_on_front');

		// text domain management
		add_action('init', array(&$this, 'init'));
		add_filter('locale', array(&$this, 'get_locale'));
		add_filter('override_load_textdomain', array(&$this, 'mofile'), 10, 3);
		add_action('wp', array(&$this, 'load_textdomains'));

		// filters posts according to the language
		add_filter('pre_get_posts', array(&$this, 'pre_get_posts'));

		// filters categories and post tags by language
		add_filter('terms_clauses', array(&$this, 'terms_clauses'), 10, 3);

		// meta in the html head section
		remove_action('wp_head', 'rel_canonical');
		add_action('wp_head', array(&$this, 'wp_head'));

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

		// rewrites feed links to filter them by language
		add_filter('feed_link', array(&$this, 'feed_link'), 10, 2);

		// rewrites archives links to filter them by language
		add_filter('getarchives_join', array(&$this, 'posts_join'));
		add_filter('getarchives_where', array(&$this, 'posts_where'));

		// rewrites author and date links to filter them by language
		add_filter('author_link', array(&$this, 'archive_link'));
		add_filter('year_link', array(&$this, 'archive_link'));
		add_filter('month_link', array(&$this, 'archive_link'));
		add_filter('day_link', array(&$this, 'archive_link'));

		// rewrites next and previous post links to filter them by language
		add_filter('get_previous_post_join', array(&$this, 'posts_join'));
		add_filter('get_next_post_join', array(&$this, 'posts_join'));
		add_filter('get_previous_post_where', array(&$this, 'posts_where'));
		add_filter('get_next_post_where', array(&$this, 'posts_where'));

		// filters the nav menus according to the current language			
		add_filter('wp_nav_menu_args', array(&$this, 'wp_nav_menu_args'));
		add_filter('wp_nav_menu_items', array(&$this, 'wp_nav_menu_items'), 10, 2);
		add_filter('wp_nav_menu_objects', array(&$this, 'wp_nav_menu_objects'), 10, 2);
		add_filter('wp_page_menu', array(&$this, 'wp_page_menu'), 10, 2);

		// filters the widgets according to the current language			
		add_filter('widget_display_callback', array(&$this, 'widget_display_callback'), 10, 3);

		// strings translation
		add_filter('widget_title', array(&$this, 'widget_title'));
		add_filter('bloginfo', array(&$this,'bloginfo'), 10, 2);

		// modifies the home url
		add_filter('home_url', array(&$this, 'home_url'));

		// allows a new value for the 'show' parameter to display the homepage url according to the current language
		// FIXME Backward compatibily for versions < 0.5 -> replaced by a filter on home_url
		add_filter('bloginfo_url', array(&$this, 'bloginfo_url'), 10, 2);

		// Template tag: displays the language switcher
		// FIXME Backward compatibily for versions < 0.5 -> replaced by pll_the_languages
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

		// either there is no preference in the browser or preferences does not match our language list or it is requested not to use the browser preference
		if (!isset($pref_lang))
			$pref_lang = $this->get_language($this->options['default_lang']);

		return $pref_lang;
	}

	// returns the current language
	function get_current_language() {
		if($this->curlang)
			return $this->curlang;

		// no language set for 404 and attachment
		if (is_404() || is_attachment())
			return $this->get_preferred_language();

		if ($var = get_query_var('lang'))
			$lang = $this->get_language($var);

		elseif (is_single() || is_page() && $var = get_queried_object_id())
			$lang = $this->get_post_language($var);

		else {
			foreach (get_taxonomies(array('show_ui'=>true)) as $taxonomy) {
				if ($var = get_query_var(get_taxonomy($taxonomy)->query_var))
					$lang = $this->get_term_language($var, $taxonomy);
			}
		}	

		return (isset($lang)) ? $lang : NULL;
	}

	// save the default locale before we start any language manipulation
	function init() {
		$this->default_locale = get_locale();
	}

	// returns the locale based on current language
	function get_locale($locale) {
		if ($this->curlang)
			$locale = $this->curlang->description;
		return $locale;
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
		// sets the current language and set a cookie to remember it
		if ($this->curlang = $this->get_current_language())
			setcookie('wordpress_polylang', $this->curlang->slug, time() + 31536000 /* 1 year */, COOKIEPATH, COOKIE_DOMAIN);			

		// our override_load_textdomain filter has done its job. let's remove it before calling load_textdomain
		remove_filter('override_load_textdomain', array(&$this, 'mofile'));

		// now we can load text domains with the right language		
		$new_locale = get_locale();
		foreach ($this->list_textdomains as $textdomain)
			load_textdomain( $textdomain['domain'], str_replace($this->default_locale, $new_locale, $textdomain['mo']));

		// and finally load local strings
		global $l10n;
		$mo = new MO();
		$reader = new POMO_StringReader(base64_decode(get_option('polylang_mo'.$this->curlang->term_id)));
		$mo->import_from_reader($reader);
		$l10n['polylang_string'] = &$mo;	

		// reinitializes wp_locale for weekdays and months
		global $wp_locale;
		$wp_locale->init();
	}

	// filters posts according to the language
	function pre_get_posts($query) {
		global $wp_rewrite;
		$qvars = $query->query_vars;

		// detect our exclude pages query and returns to avoid conflicts
		// this test should be sufficient
		if (isset($qvars['tax_query'][0]) && isset($qvars['tax_query'][0]['taxonomy']) && isset($qvars['tax_query'][0]['operator']))
			return;

		if (empty($query->query)) {
			if ($this->options['hide_default'] && isset($_COOKIE['wordpress_polylang']))
				$this->curlang = $this->get_language($this->options['default_lang']);
			else
				$this->curlang = $this->get_preferred_language(); // sets the language according to browser preference or default language

			if ($this->options['default_lang'] == $this->curlang->slug && $this->options['hide_default']) {
				if ($this->page_on_front && $link_id = $this->get_post($this->page_on_front, $this->curlang))
					$query->set('page_id', $link_id);
				else
					$query->set('lang', $this->curlang->slug);
			}
			else {
				if ($this->page_on_front && $link_id = $this->get_post($this->page_on_front, $this->curlang))
					$url = _get_page_link($link_id);
				else
					$url = home_url('?lang='.$this->curlang->slug);

				wp_redirect($url);
				exit;
			}	
		}

		// sets is_home on translated home page when it displays posts
		if (!$this->page_on_front && $query->is_tax && count($query->query) == 1) {
			$query->is_home = true;
			$query->is_tax = false;
		}

		// sets the language for posts page in case the front page displays a static page
		if ($this->page_for_posts) {
			// If permalinks are used, WordPress does set and use $query->queried_object_id and sets $query->query_vars['page_id'] to 0
			// and does set and use $query->query_vars['page_id'] if permalinks are not used :(
			if (isset($query->queried_object_id))
				$page_id = $query->queried_object_id;
			elseif (isset($qvars['page_id']))
				$page_id = $qvars['page_id'];

			if (isset($page_id) && $page_id && $this->get_post($page_id, $this->get_post_language($this->page_for_posts)) == $this->page_for_posts) {
				$query->set('lang',$this->get_post_language($page_id)->slug);
				$query->queried_object_id = $this->page_for_posts;
				$query->query_vars['page_id'] = $this->page_for_posts; // FIXME the trick works but breaks .current-menu-item and .current_page_item
				$query->is_page = false;
				$query->is_home = true;
				$query->is_posts_page = true;
				$query->is_singular = false;
			}
		}

		// FIXME to generalize as I probably forget things
		// sets the language in case we hide the default language
		if ( $this->options['hide_default'] && !isset($qvars['lang']) && (
			(count($query->query) == 1 && isset($qvars['paged']) && $qvars['paged']) ||
			isset($qvars['m']) && $qvars['m'] ||
			isset($qvars['feed']) && $qvars['feed'] ||
			isset($qvars['author']) && $qvars['author']))
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
		if (isset($qvars['lang']) && $qvars['lang'] && !is_post_type_archive() && !is_date() && !is_author() && !is_category() && !is_tag() && !is_comment_feed())
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

	// filters categories and post tags by language when needed
	function terms_clauses($clauses, $taxonomies, $args) {
		// does nothing except on taxonomies which have show_ui set to 1 (includes category and post_tags)
		foreach ($taxonomies as $tax) {
			if(!get_taxonomy($tax)->show_ui)
				return $clauses;
		}

		// adds our clauses to filter by current language
		return isset($this->curlang) ? $this->_terms_clauses($clauses, $this->curlang, PLL_DISPLAY_ALL) : $clauses;
	}

	// meta in the html head section
	function wp_head() {
		// modifies the canonical link to the homepage
		if (is_singular()) {
			global $wp_the_query;
			if ($id = $wp_the_query->get_queried_object_id()) {
				if (is_page())
					$link = _get_page_link($id); // ignores page_on_front unlike get_permalink
				else
					$link = get_permalink ($id);
				echo "<link rel='canonical' href='$link' />\n";
			}
		}

		// outputs references to translated pages (if exists) in the html head section
		foreach ($this->get_languages_list() as $language) {
			if ($language->slug != $this->curlang->slug && $url = $this->get_translation_url($language))
				printf("<link hreflang='%s' href='%s' rel='alternate' />\n", esc_attr($language->slug), esc_url($url));
		}		
	}

	// prevents redirection of the homepage
	function redirect_canonical($redirect_url, $requested_url) {
		if($requested_url == _get_page_link($this->page_on_front))
			return false;
		return $redirect_url;
	}

	// adds some javascript workaround knowing it's not perfect...
	function wp_print_footer_scripts() {
		if ($this->curlang) {
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
		if (isset($this->curlang))
			$pages = array_merge($pages, $this->exclude_pages($this->curlang->term_id));

		return $pages;
	}

	// filters the comments according to the current language mainly for the recent comments widget
	function comments_clauses($clauses, $comment_query) {
		// first test if wp_posts.ID already available in the query
		if ($this->curlang && strpos($clauses['join'], '.ID')) {
			global $wpdb;
			$clauses['join'] .= " INNER JOIN $wpdb->term_relationships AS tr ON tr.object_id = ID";
			$clauses['where'] .= $wpdb->prepare(" AND tr.term_taxonomy_id = %d", $this->curlang->term_taxonomy_id);
		}
		return $clauses;
	}

	// adds language information to a link when using pretty permalinks
	function add_language_to_link($url) {
		$base = $this->options['rewrite'] ? '/' : '/language/';
		$slug = $this->options['default_lang'] == $this->curlang->slug && $this->options['hide_default'] ? '' : $base.$this->curlang->slug;
		return esc_url(str_replace($this->home, $this->home.$slug, $url));
	}

	// Modifies the feed link to add the language parameter
	function feed_link($url, $feed) {
		global $wp_rewrite;

		if ($this->curlang) {
			if ($wp_rewrite->using_permalinks())
				$url = $this->add_language_to_link($url);
			elseif ($feed)
				$url = esc_url(home_url('?lang='.$this->curlang->slug.'&feed='.$feed));
		}			
		return $url;
	}

	// modifies the sql request for wp_get_archives an get_adjacent_post to filter by the current language
	function posts_join($sql) {
		if ($this->curlang) {
			global $wpdb;
			$sql .= " INNER JOIN $wpdb->term_relationships ON object_id = ID";
		}
		return $sql;
	}

	// modifies the sql request for wp_get_archives and get_adjacent_post to filter by the current language
	function posts_where($sql) {
		if ($this->curlang) {
			global $wpdb;
			$sql .= $wpdb->prepare(" AND term_taxonomy_id = %d", $this->curlang->term_taxonomy_id);
		}
		return $sql;
	}

	// modifies the author and date links to add the language parameter
	function archive_link($link) {
		global $wp_rewrite;

		if ($this->curlang) {
			if ($wp_rewrite->using_permalinks()) 
				$link = $this->add_language_to_link($link);
			else
				$link = esc_url(str_replace($this->home.'/?', $this->home.'/?lang='.$this->curlang->slug.'&amp;', $link));
		}
		return $link;
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
		elseif (get_option('show_on_front') == 'page' && isset($wp_query->queried_object_id) && $wp_query->queried_object_id == $this->page_for_posts)
			$url = get_permalink($this->get_post($this->page_for_posts, $language));

		elseif (is_page() && $id = $this->get_post($wp_query->queried_object_id, $language))
			$url = $hide && $id == $this->get_post($this->page_on_front, $language) ?
				$this->home :
				_get_page_link($id);

		elseif ( !is_tax ('language') && (is_category() || is_tag() || is_tax () ) ) {
			$term = get_queried_object();
			$lang = $this->get_term_language($term->term_id);
			$taxonomy = $term->taxonomy;

			if ($language->slug == $lang->slug)
				$url = get_term_link($term, $taxonomy); // self link
			elseif ($link_id = $this->get_translation('term', $term->term_id, $language))
				$url = get_term_link(get_term($link_id, $taxonomy), $taxonomy);
		}

		// don't test if there are existing translations before creating the url as it would be very expensive in sql queries
		elseif(is_archive()) {
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
			}
			else
				$url = $hide ? remove_query_arg('lang') : add_query_arg('lang', $language->slug);
		}

		elseif (is_home() || is_tax('language') )
			$url = $hide ? $this->home : get_term_link($language, 'language');

		return isset($url) ? $url : null;
	}

	// filters the nav menus according to the current language			
	function wp_nav_menu_args($args) {
		if (!$args['menu'] && $args['theme_location'] && $this->curlang) {
			$menu_lang = get_option('polylang_nav_menus');
			$args['menu'] = $menu_lang[$args['theme_location']][$this->curlang->slug];
		}
		return $args;
	}

	// filters the widgets according to the current language			
	function widget_display_callback($instance, $widget, $args) {
		$widget_lang = get_option('polylang_widgets');			
		// don't display if a language filter is set and this is not the current one
		if (isset($this->curlang) && isset($widget_lang[$widget->id]) && $widget_lang[$widget->id] && $widget_lang[$widget->id] != $this->curlang->slug)
			return false;

		return $instance;
	}

	// adds the language switcher at the end of the menu
	function wp_nav_menu_items($items, $args) {
		$menu_lang = get_option('polylang_nav_menus');
		return $menu_lang[$args->theme_location]['switcher'] ?
			$items . $this->the_languages(array(
				'menu' => 1,
				'show_names' => $menu_lang[$args->theme_location]['show_names'],
				'show_flags' => $menu_lang[$args->theme_location]['show_flags'],
				'force_home' => $menu_lang[$args->theme_location]['force_home'],
				'echo' => 0)) :
			$items;
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

		foreach($menu_items as $item) {
			if ($item->object == 'page')
				$item->url = _get_page_link($item->object_id); // avoids bad link on translated front page
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
			$title = apply_filters( 'the_title', get_page($id)->post_title);
			$menu = str_replace('<li class="page_item page-item-'.$id.'"><a href="'.$url.'">'.$title.'</a></li>', '', $menu);
			$menu = str_replace('<li class="page_item page-item-'.$id.' current_page_item"><a href="'.$url.'">'.$title.'</a></li>', '', $menu);
		}
		return $menu;
	}

	// translates widget titles
	function widget_title($title) {
		return __($title, 'polylang_string');
	}

	// translates site title and tagline
	function bloginfo($output, $show) {
		return in_array($show, array('', 'name', 'description')) ? __($output, 'polylang_string') : $output;
	}

	// acts as is_front_page but knows about translated front page
	function is_front_page() {
		if ('posts' == get_option('show_on_front') && is_home())
			return true;
		elseif ('page' == get_option('show_on_front') && $this->page_on_front && is_page($this->get_post($this->page_on_front, $this->get_current_language())))
			return true;
		elseif(is_tax('language'))
			return true;
		else
			return false;
	}

	// filters the home url to get the right language
	function home_url($url) {
		if ( !(did_action('template_redirect') && rtrim($url,'/') == rtrim($this->home,'/') && $this->curlang) )
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
		if ($show == 'lang_url' && $this->curlang) {
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

		$output = '';
		$listlanguages = $this->get_languages_list($hide_if_empty);

		foreach ($listlanguages as $language) {
			if ($dropdown) {
				$output .= sprintf("<option value='%s'%s>%s</option>\n",
					esc_attr($language->slug),
					$language->term_id == $this->curlang->term_id ? ' selected="selected"' : '',
					esc_html($language->name)
				);
			}
			else {
				// hide current language
				if ($this->curlang->term_id == $language->term_id && $hide_current)
					continue;
				
				$url = $force_home ? null : $this->get_translation_url($language);

				// hide if no translation exists 
				if (!isset($url) && $hide_if_no_translation)
					continue;

				$url = isset($url) ? $url : $this->get_home_url($language); // if the page is not translated, link to the home page

				$class = 'lang-item lang-item-'.esc_attr($language->term_id);
				$class .= $language->term_id == $this->curlang->term_id ? ' current-lang' : '';
				$class .= $menu ? ' menu-item' : '';

				$flag = $show_flags ? $this->get_flag($language) : '';
				$name = $show_names || !$show_flags ? esc_html($language->name) : '';

				$output .= '<li class="'.$class.'"><a href="'.esc_url($url).'">'.($show_flags && $show_names ? $flag.'&nbsp;'.$name : $flag.$name)."</a></li>\n";	
			}
		}

		$output = $dropdown ? "<select name='lang_choice' id='lang_choice'>\n" . $output . "</select>\n" : $output;

		if ($echo)
			echo $output;
		else
			return $output;
	}
}
?>

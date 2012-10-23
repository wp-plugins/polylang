<?php
// filters posts and terms by language, rewrites links to include the language, etc...
// used only for frontend
class Polylang_Core extends Polylang_base {
	private $curlang; // current language
	private $default_locale;
	private $list_textdomains = array(); // all text domains
	private $labels; // post types and taxonomies labels to translate
	private $first_query = true;

	// options often needed
	private $page_for_posts;
	private $page_on_front;

	// used to cache results
	private $posts = array();
	private $translation_url = array();
	private $home_urls = array();

	function __construct() {
		parent::__construct();

		// init options often needed
		$this->page_for_posts = get_option('page_for_posts');
		$this->page_on_front = get_option('page_on_front');

		// if no language found, choose the preferred one
		add_filter('pll_get_current_language', array(&$this, 'pll_get_current_language'));

		// sets the language of comment
		add_action('pre_comment_on_post', array(&$this, 'pre_comment_on_post'));

		// text domain management
		if ($this->options['force_lang'] && get_option('permalink_structure') && PLL_LANG_EARLY)
			add_action('setup_theme', array(&$this, 'setup_theme'), 20); // after Polylang::setup_theme
		else {
			add_filter('override_load_textdomain', array(&$this, 'mofile'), 10, 3);
			add_filter('gettext', array(&$this, 'gettext'), 10, 3);
			add_filter('gettext_with_context', array(&$this, 'gettext_with_context'), 10, 4);
		}

		add_action('init', array(&$this, 'init'));
		foreach (array('wp', 'login_init', 'admin_init') as $filter) // admin_init for ajax thanks to g100g
			add_action($filter, array(&$this, 'load_textdomains'), 5); // priority 5 for post types and taxonomies with registered with in wp hook with default priority

		// filters the WordPress locale
		add_filter('locale', array(&$this, 'get_locale'));

		// filters posts according to the language
		add_filter('pre_get_posts', array(&$this, 'pre_get_posts'));

		// filter sticky posts by current language
		add_filter('option_sticky_posts', array(&$this, 'option_sticky_posts'));

		// translates page for posts and page on front
		add_filter('option_page_for_posts', array(&$this, 'translate_page'));
		add_filter('option_page_on_front', array(&$this, 'translate_page'));
	}

	// set these filters and actions only once the current language has been defined
	function add_language_filters() {
		// modifies the language information in rss feed
		// useful if WP < 3.4
		add_filter('option_rss_language', array(&$this, 'option_rss_language'));

		// filters categories and post tags by language
		add_filter('terms_clauses', array(&$this, 'terms_clauses'), 10, 3);

		// meta in the html head section
		add_action('wp_head', array(&$this, 'wp_head'));

		// modifies the page link in case the front page is not in the default language
		add_filter('page_link', array(&$this, 'page_link'), 10, 2);

		// prevents redirection of the homepage
		add_filter('redirect_canonical', array(&$this, 'redirect_canonical'), 10, 2);

		// adds javascript at the end of the document
		if (!$GLOBALS['wp_rewrite']->using_permalinks() && PLL_SEARCH_FORM_JS)
			add_action('wp_footer', array(&$this, 'wp_print_footer_scripts'));

		// adds the language information in the search form
		// low priority in case the search form is created using the same filter as described in http://codex.wordpress.org/Function_Reference/get_search_form
		add_filter('get_search_form', array(&$this, 'get_search_form'), 99);

		// adds the language information in admin bar search form
		remove_action('admin_bar_menu', 'wp_admin_bar_search_menu', 4);
		add_action('admin_bar_menu', array(&$this, 'admin_bar_search_menu'), 4);

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

		// filters the widgets according to the current language
		add_filter('widget_display_callback', array(&$this, 'widget_display_callback'), 10, 3);

		// strings translation (must be applied before WordPress applies its default formatting filters)
		foreach (array('widget_title', 'option_blogname', 'option_blogdescription', 'option_date_format', 'option_time_format') as $filter)
			add_filter($filter, 'pll__', 1);

		// translates biography
		add_filter('get_user_metadata', array(&$this,'get_user_metadata'), 10, 4);

		// modifies the home url
		if (PLL_FILTER_HOME_URL)
			add_filter('home_url', array(&$this, 'home_url'));
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

				$k = $lang_parse[1];
				$v = $lang_parse[4];

				if ($n = count($k)) {
					// set default to 1 for any without q factor
					foreach ($v as $key => $val)
						if ($val === '') $v[$key] = 1;

					// bubble sort (need a stable sort for Android, so can't use a PHP sort function)
					if ($n > 1) {
						for ($i = 2; $i <= $n; $i++)
							for ($j = 0; $j <= $n-2; $j++)
								if ( $v[$j] < $v[$j + 1]) {
									// swap values
									$temp = $v[$j];
									$v[$j] = $v[$j + 1];
									$v[$j + 1] = $temp;
									//swap keys
									$temp = $k[$j];
									$k[$j] = $k[$j + 1];
									$k[$j + 1] = $temp;
								}
					}
					// NOTE: array_combine => PHP5
					$accept_langs = array_combine($k,$v);
				}
			}

			// looks through sorted list and use first one that matches our language list
			$listlanguages = $this->get_languages_list(array('hide_empty' => true)); // hides languages with no post
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

		// no language set for 404
		if (is_404() || current_filter() == 'login_init')
			return $this->get_preferred_language();

		if ($var = get_query_var('lang'))
			$lang = $this->get_language(reset(explode(',',$var))); // choose the first queried language

		// Ajax thanks to g100g
		elseif (isset($_REQUEST['pll_load_front']))
			$lang =  isset($_REQUEST['lang']) && $_REQUEST['lang'] ? $this->get_language($_REQUEST['lang']) : $this->get_preferred_language();

		elseif ((is_single() || is_page() || (is_attachment() && PLL_MEDIA_SUPPORT)) && ( ($var = get_queried_object_id()) || ($var = get_query_var('p')) || ($var = get_query_var('page_id')) || ($var = get_query_var('attachment_id')) ))
			$lang = $this->get_post_language($var);

		else {
			foreach ($this->taxonomies as $taxonomy) {
				if ($var = get_query_var(get_taxonomy($taxonomy)->query_var))
					$lang = $this->get_term_language($var, $taxonomy);
			}
		}
		// allows plugins to set the language
		return apply_filters('pll_get_current_language', isset($lang) ? $lang : false);
	}

	// if no language found, return the preferred one
	function pll_get_current_language($lang) {
		return !$lang ? $this->get_preferred_language() : $lang;
	}

	// sets the language of comment
	// useful to redirect to correct post comment url when adding the language to all url
	function pre_comment_on_post($post_id) {
		$this->curlang = $this->get_post_language($post_id);
		$this->add_post_term_link_filters();
	}

	// sets the language when it is always included in the url
	function setup_theme() {
		$root = $this->options['rewrite']? '' : 'language/';

		foreach ($this->get_languages_list() as $language)
			$languages[] = $language->slug;

		$languages = $GLOBALS['wp_rewrite']->using_permalinks() ?
			'#\/'.$root.'('.implode('|', $languages).')\/#' :
			'#lang=('.implode('|', $languages).')#';

		preg_match($languages, trailingslashit($_SERVER['REQUEST_URI']), $matches);

		// home is resquested
		// some PHP setups turn requests for / into /index.php in REQUEST_URI
		// thanks to Gonçalo Peres for pointing out the issue with queries unknown to WP
		// http://wordpress.org/support/topic/plugin-polylang-language-homepage-redirection-problem-and-solution-but-incomplete?replies=4#post-2729566
		if (str_replace('www.', '', home_url('/')) == trailingslashit((is_ssl() ? 'https://' : 'http://').str_replace('www.', '', $_SERVER['HTTP_HOST']).str_replace(array('index.php', '?'.$_SERVER['QUERY_STRING']), array('', ''), $_SERVER['REQUEST_URI'])))
			$this->home_requested();

		// $matches[1] is the slug of the requested language
		elseif ($matches)
			$this->curlang = $this->get_language($matches[1]);
		elseif (false === strpos($_SERVER['SCRIPT_NAME'], 'index.php')) // wp-login, wp-signup, wp-activate
			$this->curlang = $this->get_preferred_language();
		else
			$this->curlang = $this->get_language($this->options['default_lang']);

		$GLOBALS['l10n']['pll_string'] = $this->mo_import($this->curlang);
	}

	// save the default locale before we start any language manipulation
	function init() {
		$this->default_locale = get_locale();
	}

	// returns the locale based on current language
	function get_locale($locale) {
		return $this->curlang ? $this->curlang->description : $locale;
	}

	// modifies the language information in rss feed
	function option_rss_language($value) {
		return get_bloginfo_rss('language');
	}

	// saves all text domains in a table for later usage
	function mofile($bool, $domain, $mofile) {
		$this->list_textdomains[] = array ('mo' => $mofile, 'domain' => $domain);
		return true; // prevents WP loading text domains as we will load them all later
	}

	// saves post types and taxonomies labels for a later usage
	function gettext($translation, $text, $domain) {
		$this->labels[$text] =  array('domain' => $domain);
		return $translation;
	}

	// saves post types and taxonomies labels for a later usage
	function gettext_with_context($translation, $text, $context, $domain) {
		$this->labels[$text] =  array('domain' => $domain, 'context' => $context);
		return $translation;
	}

	// translates post types and taxonomies labels once the language is known
	function translate_labels($type) {
		foreach($type->labels as $key=>$label)
			if (isset($this->labels[$label]))
				$type->labels->$key = isset($this->labels[$label]['context']) ?
					_x($label, $this->labels[$label]['context'], $this->labels[$label]['domain']) :
					__($label, $this->labels[$label]['domain']);
	}

	// NOTE: I believe there are two ways for a plugin to force the WP language
	// as done by xili_language: load text domains and reinitialize wp_locale with the action 'wp'
	// as done by qtranslate: define the locale with the action 'plugins_loaded', but in this case, the language must be specified in the url.
	function load_textdomains() {
		// our override_load_textdomain filter has done its job. let's remove it before calling load_textdomain
		remove_filter('override_load_textdomain', array(&$this, 'mofile'));
		remove_filter('gettext', array(&$this, 'gettext'), 10, 3);
		remove_filter('gettext_with_context', array(&$this, 'gettext_with_context'), 10, 4);

		// check there is at least one language defined and sets the current language
		if ($this->get_languages_list() && $this->curlang = $this->get_current_language()) {

			// set a cookie to remember the language. check headers have not been sent to avoid ugly error
			if (!headers_sent() && (!isset($_COOKIE['wordpress_polylang']) || $_COOKIE['wordpress_polylang'] != $this->curlang->slug))
				setcookie('wordpress_polylang', $this->curlang->slug, time() + 31536000 /* 1 year */, COOKIEPATH, COOKIE_DOMAIN);

			// set all our language filters and actions
			$this->add_language_filters();

			if (!($this->options['force_lang'] && $GLOBALS['wp_rewrite']->using_permalinks() && PLL_LANG_EARLY)) {
				if ($this->curlang->description != 'en_US') {
					// now we can load text domains with the right language
					$new_locale = get_locale();
					foreach ($this->list_textdomains as $textdomain)
						load_textdomain( $textdomain['domain'], str_replace($this->default_locale, $new_locale, $textdomain['mo']));

					// reinitializes wp_locale for weekdays and months, as well as for text direction
					unset($GLOBALS['wp_locale']);
					$GLOBALS['wp_locale'] = new WP_Locale();				
					$GLOBALS['wp_locale']->text_direction = get_metadata('term', $this->curlang->term_id, '_rtl', true) ? 'rtl' : 'ltr';

					// translate labels of post types and taxonomies
					foreach ($GLOBALS['wp_taxonomies'] as $tax)
						$this->translate_labels($tax);
					foreach ($GLOBALS['wp_post_types'] as $pt)
						$this->translate_labels($pt);
				}

				// and finally load user defined strings
				$GLOBALS['l10n']['pll_string'] = $this->mo_import($this->curlang);
			}
		}

		else {
			// can't work so load the text domains with WordPress default language
			foreach ($this->list_textdomains as $textdomain)
				load_textdomain($textdomain['domain'], $textdomain['mo']);
		}
	}

	// special actions when home page is requested
	// optionally redirects to the home page in the preferred language
	function home_requested($query = false) {
		// need this filter to get the right url when adding language code to all urls
		if ($this->options['force_lang'] && $GLOBALS['wp_rewrite']->using_permalinks())
			add_filter('_get_page_link', array(&$this, 'post_link'), 10, 2);

		if ($this->options['hide_default'] && isset($_COOKIE['wordpress_polylang']))
			$this->curlang = $this->get_language($this->options['default_lang']);
		else
			$this->curlang = $this->get_preferred_language(); // sets the language according to browser preference or default language

		// we are already on the right page
		if ($this->options['default_lang'] == $this->curlang->slug && $this->options['hide_default']) {
			if ($this->page_on_front && $link_id = $this->get_post($this->page_on_front, $this->curlang))
				$query ? $query->set('page_id', $link_id) : set_query_var('page_id', $link_id);				
			else
				$query ? $query->set('lang', $this->curlang->slug) : set_query_var('lang', $this->curlang->slug);
		}
		// redirect to the home page in the right language
		// test to avoid crash if get_home_url returns something wrong
		// FIXME why this happens? http://wordpress.org/support/topic/polylang-crashes-1
		elseif (is_string($redirect = $this->get_home_url($this->curlang))) {
			wp_redirect($redirect);
			exit;
		}
	}

	// filters posts according to the language
	function pre_get_posts($query) {
		// don't make anything if no language has been defined yet
		// $this->post_types & $this->taxonomies are defined only once the action 'wp_loaded' has been fired
		// honor suppress_filters
		if (!$this->get_languages_list() || !did_action('wp_loaded') || $query->get('suppress_filters'))
			return $query;

		$qv = $query->query_vars;

		// users may want to display content in a different language than the current one by setting it explicitely in the query
		if (!$this->first_query && $this->curlang && isset($qv['lang']) && $qv['lang'])
			return $query;

		$this->first_query = false;

		// detect our exclude pages query and returns to avoid conflicts
		// this test should be sufficient
		if (isset($qv['tax_query'][0]['taxonomy']) && $qv['tax_query'][0]['taxonomy'] == 'language' &&
			isset($qv['tax_query'][0]['operator']) && $qv['tax_query'][0]['operator'] == 'NOT IN')
			return $query;

		// special case for wp-signup.php & wp-activate.php
		if (is_home() && false === strpos($_SERVER['SCRIPT_NAME'], 'index.php')) {
			$this->curlang = $this->get_preferred_language();
			return $query;
		}

		// homepage is requested, let's set the language
		// take care to avoid posts page for which is_home = 1
		if (!$this->curlang && empty($query->query) && (is_home() || (is_page() && $qv['page_id'] == $this->page_on_front)))
			$this->home_requested($query);

		// redirect the language page to the homepage
		if ($this->options['redirect_lang'] && is_tax('language') && $this->page_on_front && (count($query->query) == 1 || (is_paged() && count($query->query) == 2))) {
			$qv['page_id'] = $this->get_post($this->page_on_front, $this->get_language(get_query_var('lang')));
			$query->parse_query($qv);
			return $query;
		}

		// sets is_home on translated home page when it displays posts
		// is_home must be true on page 2, 3... too
		if (!$this->page_on_front && is_tax('language') && (count($query->query) == 1 || (is_paged() && count($query->query) == 2))) {
			$query->is_home = true;
			$query->is_tax = false;
			$this->curlang = $this->get_language(get_query_var('lang')); // sets the language now otherwise it will be too late to filter sticky posts !
		}

		// sets the language for posts page in case the front page displays a static page
		if ($this->page_for_posts) {
			// If permalinks are used, WordPress does set and use $query->queried_object_id and sets $query->query_vars['page_id'] to 0
			// and does set and use $query->query_vars['page_id'] if permalinks are not used :(
			if (isset($qv['pagename']) && $qv['pagename'] && isset($query->queried_object_id))
				$page_id = $query->queried_object_id;
			elseif (isset($qv['page_id']))
				$page_id = $qv['page_id'];

			if (isset($page_id) && $page_id && $this->get_post($page_id, $this->get_post_language($this->page_for_posts)) == $this->page_for_posts) {
				$this->page_for_posts = $page_id;
				$this->curlang = $this->get_post_language($page_id);
				$query->set('lang', $this->curlang->slug);
				$query->is_page = false;
				$query->is_singular = false;
				$query->is_home = true;
				$query->is_posts_page = true;
			}
		}

		$is_post_type = isset($qv['post_type']) && (in_array($qv['post_type'], $this->post_types) ||
			 (is_array($qv['post_type']) && array_intersect($qv['post_type'], $this->post_types)) );

		// FIXME to generalize as I probably forget things
		$is_archive = (count($query->query) == 1 && isset($qv['paged']) && $qv['paged']) ||
			(isset($qv['m']) && $qv['m']) ||
			(isset($qv['author']) && $qv['author']) ||
			(isset($qv['post_type']) && is_post_type_archive() && $is_post_type);

		// sets 404 when the language is not set for archives needing the language in the url
		if (!$this->options['hide_default'] && !isset($qv['lang']) && !$GLOBALS['wp_rewrite']->using_permalinks() && $is_archive)
			$query->set_404();

		// sets the language in case we hide the default language
		if ($this->options['hide_default'] && !isset($qv['lang']) && ($is_archive || is_search() || (count($query->query) == 1 && isset($qv['feed']) && $qv['feed']) ))
			$query->set('lang', $this->options['default_lang']);

		// allow filtering recent posts and secondary queries by the current language
		// take care not to break queries for non visible post types such as nav_menu_items, attachments...
		if (/*$query->is_home && */$this->curlang && (!isset($qv['post_type']) || $is_post_type ))
			$query->set('lang', $this->curlang->slug);

		// remove pages query when the language is set unless we do a search
		// FIXME is only search broken by this ?
		if (isset($qv['lang']) && $qv['lang'] && !isset($qv['post_type']) && !is_search())
			$query->set('post_type', 'post');

		// unset the is_archive flag for language pages to prevent loading the archive template
		// keep archive flag for comment feed otherwise the language filter does not work
		if (isset($qv['lang']) && $qv['lang'] && !is_comment_feed() &&
			!is_post_type_archive() && !is_date() && !is_author() && !is_category() && !is_tag() && !is_tax('post_format'))
			$query->is_archive = false;

		// unset the is_tax flag for authors pages and post types archives
		// FIXME Probably I should do this for other cases
		if (isset($qv['lang']) && $qv['lang'] && (is_author() || is_post_type_archive() || is_date() || is_search())) {
			$query->is_tax = false;
			unset($query->queried_object);
		}

		// sets a language for theme preview
		if (is_preview() && is_front_page()) {
			$this->curlang = $this->get_current_language();
			$query->set('lang', $this->curlang->slug);
		}

		// to avoid conflict beetwen taxonomies
		if (isset($query->tax_query->queries))
			foreach ($query->tax_query->queries as $tax)
				if (in_array($tax['taxonomy'], $this->taxonomies))
					unset ($query->query_vars['lang']);

		return $query;
	}

	// filter sticky posts by current language
	function option_sticky_posts($posts) {
		if ($this->curlang && !empty($posts)) {
			foreach ($posts as $key=>$post_id) {
				if ($this->get_post_language($post_id)->term_id != $this->curlang->term_id)
					unset($posts[$key]);
			}
		}
		return $posts;
	}

	// filters categories and post tags by language when needed
	function terms_clauses($clauses, $taxonomies, $args) {
		// does nothing except on taxonomies which are filterable
		foreach ($taxonomies as $tax) {
			if (!in_array($tax, $this->taxonomies))
				return $clauses;
		}

		// adds our clauses to filter by current language
		return $this->_terms_clauses($clauses, $this->curlang);
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
		if ($this->options['redirect_lang'] && $this->page_on_front && $lang = $this->get_post_language($id)) {
			if (!isset($this->posts[$lang->slug][$this->page_on_front]))
				$this->posts[$lang->slug][$this->page_on_front] = $this->get_post($this->page_on_front, $lang);
			if ($id == $this->posts[$lang->slug][$this->page_on_front])
				return $this->options['hide_default'] && $lang->slug == $this->options['default_lang'] ? trailingslashit($this->home) : get_term_link($lang, 'language');
		}

		if ($this->page_on_front && $this->options['hide_default']) {
			if (!isset($this->posts[$this->options['default_lang']][$this->page_on_front]))
				$this->posts[$this->options['default_lang']][$this->page_on_front] = $this->get_post($this->page_on_front, $this->options['default_lang']);
			if ($id == $this->posts[$this->options['default_lang']][$this->page_on_front])
				return trailingslashit($this->home);
		}

		return _get_page_link($id);
	}

	// prevents redirection of the homepage when using page on front
	function redirect_canonical($redirect_url, $requested_url) {
		return $requested_url == home_url('/') || strpos($requested_url, $this->page_link('', get_option('page_on_front'))) !== false ? false : $redirect_url;
	}

	// adds some javascript workaround knowing it's not perfect...
	function wp_print_footer_scripts() {
		// modifies the search form since filtering get_search_form won't work if the template uses searchform.php or the search form is hardcoded
		// don't use directly e[0] just in case there is somewhere else an element named 's'
		// check before if the hidden input has not already been introduced by get_search_form (FIXME: is there a way to improve this ?
		// thanks to AndyDeGroo for improving the code for compatility with old browsers
		// http://wordpress.org/support/topic/development-of-polylang-version-08?replies=6#post-2645559

		$lang = esc_js($this->curlang->slug);
		$js = "//<![CDATA[
		e = document.getElementsByName('s');
		for (i = 0; i < e.length; i++) {
			if (e[i].tagName.toUpperCase() == 'INPUT') {
				s = e[i].parentNode.parentNode.children;
				l = 0;
				for (j = 0; j < s.length; j++) {
					if (s[j].name == 'lang') {
						l = 1;
					}
				}
				if ( l == 0) {
					var ih = document.createElement('input');
					ih.type = 'hidden';
					ih.name = 'lang';
					ih.value = '$lang';
					e[i].parentNode.appendChild(ih);
				}
			}
		}
		//]]>";
		echo "<script type='text/javascript'>" .$js. "</script>";
	}

	// adds the language information in the search form
	// does not work if searchform.php is used or if the search form is hardcoded in another template file
	function get_search_form($form) {
		if ($form)
			$form = $GLOBALS['wp_rewrite']->using_permalinks() ?
				str_replace(trailingslashit($this->home), $this->get_home_url($this->curlang, true), $form) :
				str_replace('</form>', '<input type="hidden" name="lang" value="'.esc_attr($this->curlang->slug).'" /></form>', $form);

		return $form;
	}

	// rewrites the admin bar search form to include the language
	function admin_bar_search_menu($wp_admin_bar) {
		global $wp_rewrite;

		$title = sprintf('<form action="%s" method="get" id="adminbarsearch">
			<input class="adminbar-input" name="s" id="adminbar-search" tabindex="10" type="text" value="" maxlength="150" />
			<input type="submit" class="adminbar-button" value="%s"/>%s</form>',
			$wp_rewrite->using_permalinks() ? $this->get_home_url($this->curlang, true) : esc_url($this->home),
			__('Search'),
			$wp_rewrite->using_permalinks() ? '' : sprintf('<input type="hidden" name="lang" value="%s" />', esc_attr($this->curlang->slug))
		);

		$wp_admin_bar->add_menu(array(
			'parent' => 'top-secondary',
			'id'     => 'search',
			'title'  => $title,
			'meta'   => array('class' => 'admin-bar-search', 'tabindex' => -1)
		));
	}

	// excludes pages which are not in the current language for wp_list_pages
	// useful for the pages widget
	function wp_list_pages_excludes($pages) {
		return array_merge($pages, $this->exclude_pages($this->curlang->term_id));
	}

	// filters the comments according to the current language mainly for the recent comments widget
	function comments_clauses($clauses, $comment_query) {
		return $this->_comments_clauses($clauses, $this->curlang);
	}

	// modifies the sql request for wp_get_archives an get_adjacent_post to filter by the current language
	function posts_join($sql) {
		global $wpdb;
		return $sql . $wpdb->prepare(" INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID");
	}

	// modifies the sql request for wp_get_archives and get_adjacent_post to filter by the current language
	function posts_where($sql) {
		global $wpdb;
		$id = $this->curlang->term_taxonomy_id;
		return $sql . $wpdb->prepare(" AND pll_tr.term_taxonomy_id IN ($id)");
	}

	// modifies the author and date links to add the language parameter (as well as feed link)
	function archive_link($link) {
		return $this->add_language_to_link($link, $this->curlang);
	}

	// returns the url of the translation (if exists) of the current page
	function get_translation_url($language) {
		if (isset($this->translation_url[$language->slug]))
			return $this->translation_url[$language->slug];

		global $wp_query, $wp_rewrite;
		$qv = $wp_query->query;
		$hide = $this->options['default_lang'] == $language->slug && $this->options['hide_default'];

		// post and attachment
		if (is_single() && (PLL_MEDIA_SUPPORT || !is_attachment()) && $id = $this->get_post($wp_query->queried_object_id, $language))
			$url = get_permalink($id);

		// page for posts
		elseif (get_option('show_on_front') == 'page' && isset($wp_query->queried_object_id) && $wp_query->queried_object_id &&
			$wp_query->queried_object_id == $this->page_for_posts && ($id = $this->get_post($this->page_for_posts, $language)))
			$url = get_permalink($id);

		elseif (is_page() && $id = $this->get_post($wp_query->queried_object_id, $language))
			$url = $hide && $id == $this->get_post($this->page_on_front, $language) ? $this->home : get_page_link($id);

		elseif (!is_tax('post_format') && !is_tax('language') && (is_category() || is_tag() || is_tax()) ) {
			$term = get_queried_object();
			$lang = $this->get_term_language($term->term_id);

			if (!$lang || $language->slug == $lang->slug)
				$url = get_term_link($term, $term->taxonomy); // self link
			elseif ($link_id = $this->get_translation('term', $term->term_id, $language))
				$url = get_term_link(get_term($link_id, $term->taxonomy), $term->taxonomy);
		}

		// don't test if there are existing translations before creating the url as it would be very expensive in sql queries
		elseif (is_archive()) {
			if ($wp_rewrite->using_permalinks()) {
				$filters = array('author_link', 'post_type_archive_link', 'year_link', 'month_link', 'day_link');

				// prevents filtering links by current language
				remove_filter('term_link', array(&$this, 'term_link')); // for post format
				foreach ($filters as $filter)
					remove_filter($filter, array(&$this, 'archive_link'));

				if (is_author())
					$url = $this->add_language_to_link(get_author_posts_url(0, $qv['author_name']), $language);

				if (is_year())
					$url = $this->add_language_to_link(get_year_link($qv['year']), $language);

				if (is_month())
					$url = $this->add_language_to_link(get_month_link($qv['year'], $qv['monthnum']), $language);

				if (is_day())
					$url = $this->add_language_to_link(get_day_link($qv['year'], $qv['monthnum'], $qv['day']), $language);

				if (is_post_type_archive())
					$url = $this->add_language_to_link(get_post_type_archive_link($qv['post_type']), $language);

				if (is_tax('post_format'))
					$url = $this->add_language_to_link(get_post_format_link($qv['post_format']), $language);

				// put our language filters again
				add_filter('term_link', array(&$this, 'term_link'), 10, 3);
				foreach ($filters as $filter)
					add_filter($filter, array(&$this, 'archive_link'));
			}
			else {
				$url = $hide ? remove_query_arg('lang') : add_query_arg('lang', $language->slug);
				$url = remove_query_arg('paged', $url);
			}
		}

		elseif (is_search()) {
			if ($wp_rewrite->using_permalinks())
				$url = $this->add_language_to_link($this->home.'/?'.$_SERVER['QUERY_STRING'], $language);
			else {
				$url = add_query_arg('lang', $language->slug);
				$url = remove_query_arg('paged', $url);
			}
		}

		elseif (is_home() || is_tax('language') )
			$url = $this->get_home_url($language);

		return $this->translation_url[$language->slug] = (isset($url) ? $url : null);
	}

	// filters the nav menus according to the current language when called from get_nav_menu_locations()
	// mainly for Artisteer generated themes and themes which test has_nav_menu
	function nav_menu_locations($menus) {
		$menu_lang = get_option('polylang_nav_menus');
		foreach(get_registered_nav_menus() as $location => $menu) {
			if (isset($menu_lang[$location][$this->curlang->slug]))
				$menus[$location] = $menu_lang[$location][$this->curlang->slug];
		}
		return $menus;
	}

	// filters the nav menus according to the current language when called from wp_nav_menus
	function wp_nav_menu_args($args) {
		if (/*!$args['menu'] &&*/ $args['theme_location']) {
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

	// filters the widgets according to the current language
	function widget_display_callback($instance, $widget, $args) {
		$widget_lang = get_option('polylang_widgets');
		// don't display if a language filter is set and this is not the current one
		return isset($widget_lang[$widget->id]) && $widget_lang[$widget->id] && $widget_lang[$widget->id] != $this->curlang->slug ? false : $instance;
	}

	// translates biography
	function get_user_metadata($null, $id, $meta_key, $single) {
		return $meta_key == 'description' ? get_user_meta($id, 'description_'.$this->curlang->slug, true) : $null;
	}

	// translates page for posts and page on front
	function translate_page($val) {
		// returns the current page if there is no translation to avoid ugly notices
		// the fonction is often called so let's store the result
		return isset($this->curlang) && $val && (isset($this->posts[$val]) || $this->posts[$val] = $this->get_post($val, $this->curlang)) ? $this->posts[$val] : $val;
	}

	// filters the home url to get the right language
	function home_url($url) {
		if (!did_action('template_redirect') || rtrim($url,'/') != $this->home)
			return $url;

		$theme = get_theme_root();
		foreach (debug_backtrace(/*!DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS*/) as $trace) {
			// search form
			if (isset($trace['file']) && strpos($trace['file'], 'searchform.php'))
				return $GLOBALS['wp_rewrite']->using_permalinks() ? $this->get_home_url($this->curlang, true) : $url;

			$ok = $trace['function'] == 'wp_nav_menu' ||
				// direct call from the theme
				(isset($trace['file']) && strpos($trace['file'], $theme) !== false &&
					in_array($trace['function'], array('home_url', 'bloginfo', 'get_bloginfo')) );

			if ($ok)
				return $this->get_home_url($this->curlang);
		}

		return $url;
	}

	// returns the home url in the right language
	function get_home_url($language = '', $is_search = false) {
		if ($language == '')
			$language = $this->curlang;

		if (isset($this->home_urls[$language->slug][$is_search]))
			return $this->home_urls[$language->slug][$is_search];

		if ($this->options['default_lang'] == $language->slug && $this->options['hide_default'])
			return $this->home_urls[$language->slug][$is_search] = trailingslashit($this->home);

		// a static page is used as front page : /!\ don't use get_page_link to avoid infinite loop
		// don't use this for search form
		if (!$is_search && $this->page_on_front && $id = $this->get_post($this->page_on_front, $language))
			return $this->home_urls[$language->slug][$is_search] = $this->page_link('', $id);

		return $this->home_urls[$language->slug][$is_search] = get_term_link($language, 'language');
	}

	// displays (or returns) the language switcher
	function the_languages($args = '') {
		$defaults = array(
			'dropdown' => 0, // display as list and not as dropdown
			'echo' => 1, // echoes the list
			'hide_if_empty' => 1, // hides languages with no posts (or pages)
			'menu' => '0', // not for nav menu
			'show_flags' => 0, // don't show flags
			'show_names' => 1, // show language names
			'display_names_as' => 'name', // valid options are slug and name
			'force_home' => 0, // tries to find a translation
			'hide_if_no_translation' => 0, // don't hide the link if there is no translation
			'hide_current' => 0, // don't hide current language
			'post_id' => null, // if not null, link to translations of post defined by post_id
		);
		extract(wp_parse_args($args, $defaults));

		if ($dropdown)
			$output = $this->dropdown_languages(array('hide_empty' => $hide_if_empty, 'selected' => $this->curlang->slug));

		else {
			$output = '';

			foreach ($this->get_languages_list(array('hide_empty' => $hide_if_empty)) as $language) {
				// hide current language
				if ($this->curlang->term_id == $language->term_id && $hide_current)
					continue;

				$url = $post_id !== null && ($tr_id = $this->get_post($post_id, $language)) ? get_permalink($tr_id) :
					$post_id === null && !$force_home ?  $this->get_translation_url($language) : null;

				$url = apply_filters('pll_the_language_link', $url, $language->slug, $language->description);

				// hide if no translation exists
				if (!isset($url) && $hide_if_no_translation)
					continue;

				$url = isset($url) ? $url : $this->get_home_url($language); // if the page is not translated, link to the home page

				$class = 'lang-item lang-item-'.esc_attr($language->term_id);
				$class .= $language->term_id == $this->curlang->term_id ? ' current-lang' : '';
				$class .= $menu ? ' menu-item' : '';

				$flag = $show_flags ? $this->get_flag($language) : '';
				$name = $show_names || !$show_flags ? esc_html($display_names_as == 'slug' ? $language->slug : $language->name) : '';

				$output .= sprintf("<li class='%s'><a hreflang='%s' href='%s'>%s</a></li>\n",
					$class, esc_attr($language->slug), esc_url($url), $show_flags && $show_names ? $flag.'&nbsp;'.$name : $flag.$name);
			}
		}

		$output = apply_filters('pll_the_languages', $output, $args);

		if(!$echo)
			return $output;
		echo $output;
	}

	// just returns the current language for API
	function current_language($args) {
		return !isset($this->curlang) ? false :
			($args == 'name' ? $this->curlang->name :
			($args == 'locale' ? $this->curlang->description :
			$this->curlang->slug));
	}
}
?>

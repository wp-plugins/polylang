<?php 
/*
Plugin Name: Polylang
Plugin URI: http://wordpress.org/extend/plugins/polylang/
Version: 0.1.0.12
Author: F. Demarle
Description: Adds multilingual capability to Wordpress
*/

/*  Copyright 2011  F. Demarle

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('POLYLANG_VERSION', '0.2');
define('POLYLANG_DIR', dirname(__FILE__));
require_once(POLYLANG_DIR.'/base.php');
require_once(POLYLANG_DIR.'/admin.php');
require_once(POLYLANG_DIR.'/widget.php');

class Polylang extends Polylang_Base {
	var $curlang;
	var $default_locale;
	var $list_textdomains = array();
	var $search_form_filter = false;

	function __construct() {

		// manages plugin activation and deactivation
		register_activation_hook( __FILE__, array(&$this, 'activate') );
		register_deactivation_hook( __FILE__, array(&$this, 'deactivate') );

		// plugin and widget initialization
		add_action('init', array(&$this, 'init'));
		add_action('widgets_init', array(&$this, 'widgets_init'));

		// rewrite rules
		add_filter('rewrite_rules_array', array(&$this, 'rewrite_rules_array' ));

		// filters categories and post tags by language
		add_filter('terms_clauses', array(&$this, 'terms_clauses'), 10, 3);

		if (is_admin()) 
			new Polylang_Admin();
		else {
			// text domain management
			add_filter('locale', array(&$this, 'get_locale'));
			add_filter('override_load_textdomain', array(&$this, 'mofile'), 10, 3);
			add_action('wp', array(&$this, 'load_textdomains'));

			// filters posts according to the language
			add_filter('pre_get_posts', array(&$this, 'pre_get_posts')); 

			// meta in the html head section
			remove_action('wp_head', 'rel_canonical');
			add_action('wp_head', array(&$this, 'wp_head'));

			// prevents redirection of the homepage
			add_filter('redirect_canonical', array(&$this, 'redirect_canonical'), 10, 2);

			// adds javascript at the end of the document
			add_action('wp_print_footer_scripts', array(&$this, 'wp_print_footer_scripts'));

			// adds the language information in the search form
			add_filter('get_search_form', array(&$this, 'get_search_form'));

			// filters the pages according to the current language
			add_filter('get_pages', array(&$this, 'get_pages'), 10, 2);

			// filters the comments according to the current language 
			add_filter('comments_clauses', array(&$this, 'comments_clauses'));

			// rewrites feed links to filter them by language 
			add_filter('feed_link', array(&$this, 'feed_link'), 10, 2);

			// rewrites archives links to filter them by language 
			add_filter('getarchives_join', array(&$this, 'posts_join'));
			add_filter('getarchives_where', array(&$this, 'posts_where'));
			add_filter('get_archives_link', array(&$this, 'get_archives_link'));

			// rewrites next and previous post links to filter them by language 
			add_filter('get_previous_post_join', array(&$this, 'posts_join'));
			add_filter('get_next_post_join', array(&$this, 'posts_join'));
			add_filter('get_previous_post_where', array(&$this, 'posts_where'));
			add_filter('get_next_post_where', array(&$this, 'posts_where'));

			// filters the nav menus according to current language			
			add_filter('wp_nav_menu_args', array(&$this, 'wp_nav_menu_args'));

			// allows a new value for the 'show' parameter to display the homepage url according to the current language
			add_filter('bloginfo_url', array(&$this, 'bloginfo_url'), 10, 2);

			// Template tags
	 		add_action('the_languages', array(&$this, 'the_languages'));
		}
	}

	// plugin activation
	function activate() {
		// create the termmeta table - not provided by WP by default - if it does not already exists
		// uses exactly the same model as other meta tables to be able to use access functions provided by WP 
		global $wpdb;
		$charset_collate = '';  
		if ( ! empty($wpdb->charset) )
		  $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
		  $charset_collate .= " COLLATE $wpdb->collate";

		$table = $wpdb->prefix . 'termmeta';
		
		$tables = $wpdb->get_results("show tables like '$table'");
		if (!count($tables))
		  $wpdb->query("CREATE TABLE $table (
		    meta_id bigint(20) unsigned NOT NULL auto_increment,
		    term_id bigint(20) unsigned NOT NULL default '0',
		    meta_key varchar(255) default NULL,
		    meta_value longtext,
		    PRIMARY KEY  (meta_id),
		    KEY term_id (term_id),
		    KEY meta_key (meta_key)
		  ) $charset_collate;");

		// codex tells to use the init action to call register_taxonomy but I need it now for my rewrite rules
		register_taxonomy('language', array('post', 'page'), array('label' => false, 'query_var'=>'lang')); 

		// defines default values for options in case this is the first installation
		$options = get_option('polylang');
		if (!$options) {
			$options['browser'] = 1; // default language for the front page is set by browser preference
			$options['rewrite'] = 0; // do not remove /language/ in permalinks
		}
		$options['version'] = POLYLANG_VERSION; // do not manage versions yet but prepare for it
		update_option('polylang', $options);

		// add our rewrite rules
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	// plugin deactivation
	function deactivate() {
		// delete our rewrite rules
		remove_filter('rewrite_rules_array', array(&$this,'rewrite_rules_array' ));
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	// some initialization
	function init() {
		global $wpdb;
		$wpdb->termmeta = $wpdb->prefix . 'termmeta'; // registers the termmeta table in wpdb

		// registers the language taxonomy
		// codex: use the init action to call this function
		register_taxonomy('language', 'post', array(
			'label' => false,
			'query_var'=>'lang',
			'update_count_callback' => '_update_post_term_count'));

		// optionaly removes 'language' in permalinks so that we get http://www.myblog/en/ instead of http://www.myblog/language/en/
		// the simple line of code is inspired by the WP No Category Base plugin : http://wordpresssupplies.com/wordpress-plugins/no-category-base/
		$options = get_option('polylang');
		if ($options['rewrite']) {		
			global $wp_rewrite;
			$wp_rewrite->extra_permastructs['language'][0] = '%language%';
		}

		$this->default_locale = get_locale(); // save the default locale before we start any language manipulation
		load_plugin_textdomain('polylang', false, dirname(plugin_basename( __FILE__ ))); // plugin i18n
	}

	// registers our widget
	function widgets_init() {
		register_widget('Polylang_Widget');
	}

	// rewrites rules if pretty permalinks are used
	function rewrite_rules_array($rules) {
		$options = get_option('polylang');
		$newrules = array();
		$listlanguages = $this->get_languages_list();

		// modifies the rules created by WordPress when '/language/' is removed in permalinks
		if ($options['rewrite']) {					
			foreach ($listlanguages as $language) {
				$newrules[$language->slug.'/feed/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?lang='.$language->slug.'&feed=$matches[1]';
				$newrules[$language->slug.'/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?lang='.$language->slug.'&feed=$matches[1]';
				$newrules[$language->slug.'/page/?([0-9]{1,})/?$'] = 'index.php?lang='.$language->slug.'&paged=$matches[1]';
				$newrules[$language->slug.'/?$'] = 'index.php?lang='.$language->slug;
			}
			unset($rules['([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$']); // => index.php?lang=$matches[1]&feed=$matches[2]
			unset($rules['([^/]+)/(feed|rdf|rss|rss2|atom)/?$']); // => index.php?lang=$matches[1]&feed=$matches[2]
			unset($rules['([^/]+)/page/?([0-9]{1,})/?$']); // => index.php?lang=$matches[1]&paged=$matches[2]
			unset($rules['([^/]+)/?$']); // => index.php?lang=$matches[1]
		}

		$options['rewrite'] ? $base = '' : $base = 'language/';			

		// rewrite rules for comments feed filtered by language
		foreach ($listlanguages as $language) {
			$newrules[$base.$language->slug.'/comments/feed/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?lang='.$language->slug.'&feed=$matches[1]&withcomments=1';
			$newrules[$base.$language->slug.'/comments/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?lang='.$language->slug.'&feed=$matches[1]&withcomments=1';
		}
		unset($rules['comments/feed/(feed|rdf|rss|rss2|atom)/?$']); // => index.php?&feed=$matches[1]&withcomments=1
		unset($rules['comments/(feed|rdf|rss|rss2|atom)/?$']); // => index.php?&feed=$matches[1]&withcomments=1

		// rewrite rules for archives filtered by language
		foreach ($rules as $key => $rule) {
			$is_archive = strpos($rule, 'year=') && !(
				strpos($rule, 'p=') ||
				strpos($rule, 'name=') ||
				strpos($rule, 'page=') ||
				strpos($rule, 'cpage=') );

			if ($is_archive) {
				foreach ($listlanguages as $language)
					$newrules[$base.$language->slug.'/'.$key] = str_replace('?', '?lang='.$language->slug.'&', $rule);

				unset($rules[$key]); // now useless
			}
		}

		return $newrules + $rules;
	}

	// filters categories and post tags by language when needed (both in admin panels and frontend)
	function terms_clauses($clauses, $taxonomies, $args) {
		// does nothing except on categories and post tags
		if ( !in_array('category', $taxonomies) && !in_array('post_tag', $taxonomies) )
			return $clauses;

		if (is_admin()) {
			$screen = get_current_screen(); //FIXME breaks a user's admin -> use pagenow instead ? Or simply test the function.

			// NOTE: $screen is not defined in the tag cloud of the Edit Post panel ($pagenow set to admin-ajax.php)
			if (isset($screen))
				// does nothing in the Categories, Post tags, Languages an Posts* admin panels
				if ($screen->base == 'edit-tags' || $screen->base == 'toplevel_page_mlang' || $screen->base == 'edit')
					return $clauses;

				// *FIXME I want all categories in the dropdown list and only the ones in the right language in the inline edit
				// It seems that I need javascript to get the post_id as inline edit data are manipulated in inline-edit-post.js

			$this->curlang = $this->get_current_language();
		}

		// adds our clauses to filter by current language
		if ($this->curlang) {
			global $wpdb;
			$value = $this->curlang->term_id;
			$clauses['join'] .= " INNER JOIN $wpdb->termmeta AS tm ON t.term_id = tm.term_id";
			$clauses['where'] .= " AND tm.meta_key = '_language' AND tm.meta_value = $value";
		}
		return $clauses;
	}

	// returns the language according to browser preference or the default language
	function get_preferred_language() {
		// check first is the user was already browsing this site
		if (isset($_COOKIE['wordpress_polylang']))
			return $this->get_language($_COOKIE['wordpress_polylang']);

		// sets the browsing language according to the browser preferences
		// code adapted from http://www.thefutureoftheweb.com/blog/use-accept-language-header 
		$options = get_option('polylang');
		if ($options['browser']) {
			$accept_langs = array();

			if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
				// break up string into pieces (languages and q factors)
				preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

				if (count($lang_parse[1])) {
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
			$pref_lang = $this->get_language($options['default_lang']);

		return $pref_lang;
	}

	// returns the current language
	function get_current_language() {	
		global $post_ID;

		if($this->curlang)
			return $this->curlang;

		if (is_404())
			return $this->get_preferred_language();

		if (is_admin()) {
			if (isset($post_ID)) 
				$lang = $this->get_post_language($post_ID);

			// trick to get the post number in the Post Tags metabox in the Edit Post screen (as $post_ID not defined)
			$qvars = $this->get_referer_vars();
			if (isset($qvars['post']))
				$lang = $this->get_post_language($qvars['post']);
		}
		else {
			$var = get_query_var('lang');
			if ($var)
				$lang = $this->get_language($var);
			else {
				$var = get_queried_object_id();	
				if ($var && is_single() || is_page())
					$lang = $this->get_post_language($var);	
				else {
					$var = get_query_var('cat');
					if ($var)
							$lang = $this->get_term_language($var);
					else {
						$var = get_query_var('tag_id');
						if ($var)
							$lang = $this->get_term_language($var);
					}
				}
			}
		}

		return (isset($lang)) ? $lang : NULL;
	}

	// returns the locale based on current language
	function get_locale($locale) {
		if ($this->curlang)
			$locale = $this->curlang->description;
		return $locale;
	}

	// saves all text domains in a table for later usage
	function mofile( $bool, $domain, $mofile) {
		$this->list_textdomains[] = array ('mo' => $mofile, 'domain' => $domain);
		return true; // prevents WP loading text domains as we will load them all later
	}

	// NOTE: I believe there are two ways for a plugin to force the WP language 
	// as done by xili_language and here : load text domains and reinitialize wp_locale with the action 'wp'
	// as done by qtranslate : define the locale with the action 'plugins_loaded', but in this case, the language must be specified in the url.	
	function load_textdomains() {	
		// sets the current language and set a cookie to remember it
		if ($this->curlang = $this->get_current_language())
			setcookie('wordpress_polylang', $this->curlang->slug, time() + 31536000 /* 1 year */, COOKIEPATH, COOKIE_DOMAIN);			

		// our override_load_textdomain has done its job. let's remove it before calling load_textdomain
		remove_filter('override_load_textdomain', array(&$this, 'mofile'));

		// now we can load text domains with the right language		
		$new_locale = get_locale();
		foreach ($this->list_textdomains as $textdomain)
			load_textdomain( $textdomain['domain'], str_replace($this->default_locale, $new_locale, $textdomain['mo']));

		global $wp_locale;
		$wp_locale->init(); // reinitializes wp_locale for weekdays and months
	}

	// filters posts according to the language
	function pre_get_posts($query) {

		if (empty($query->query)) {

			// sets the language according to browser preference or default language
			$this->curlang = $this->get_preferred_language();

			// redirect to the right translated page in case a static page is used as front page
			$post_id = get_option('page_on_front');
			if (isset($this->curlang) && $post_id) {
				// a static page is used as front page
				$language = $this->get_post_language($post_id);
				if ($language->slug != $this->curlang->slug) {
					// but the one defined in the "Reading Settings" panel is not in the right language, so let's redirect to the right one
					$link_id = $this->get_translated_post($post_id, $this->curlang);
					if ($link_id)	{			
						$url = _get_page_link($link_id);
						wp_redirect($url);
						exit;
					}
				}
			}
		}

		$qvars = $query->query_vars;

		// filters recent posts to the current language
		// FIXME if $qvars['post_type'] == 'nav_menu_item', setting lang breaks custom menus.
		// since to get nav_menu_items, get_post is used and no language is linked to nav_menu_items
		// (even if the object behind the nav_menu_item is linked to a language)
		if ($query->is_home && $this->curlang && !isset($qvars['post_type'])) 
			$query->set('lang', $this->curlang->slug);

		// remove pages from archives when the language is set
		if (isset($qvars['m']) && isset($qvars['lang']))
			$query->set('post_type', 'post');	
	}

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
		$listlanguages = $this->get_languages_list();
		foreach ($listlanguages as $language) {
			if ($language->slug != $this->curlang->slug) {
				$url = $this->get_translation_url($language);
				if ($url)
					echo "<link hreflang='$language->slug' href='$url' rel='alternate' />\n";
			}
		}		
	}

	// prevents redirection of the homepage
	function redirect_canonical($redirect_url, $requested_url) {
		if($requested_url == _get_page_link(get_option('page_on_front')))
			return false;
		return $redirect_url;
	}

	// adds some javascript workaround knowing it's not perfect...
	function wp_print_footer_scripts() {
		if ($this->curlang) {
			$js = "<script type='text/javascript'>";

			// modifies links to the homepage to filter by the right language
			// since filtering home_url breaks things in the core and filtering bloginfo_url is not enough if the template uses home_url()
			$url = rtrim(home_url(), '/');

			if ($page = get_option('page_on_front'))
				$newurl = _get_page_link($this->get_post($page, $this->curlang));
			else
				$newurl = get_term_link($this->curlang->slug, 'language');

			$js .= "var e = document.getElementsByTagName('a');
			for (var i = 0; i < e.length; i++) {
    		var href = e[i].href;
				if (href == '$url' || href == '$url/') {
					e[i].href = '$newurl';
				}
			}";

			// modifies the search form since filtering get_search_form won't work if the template uses searchform.php
			// don't use directly e[0] just in case there is somewhere else an element named 's'
			// check before if the hidden input has not already been introduced by get_search_form
			if (!$this->search_form_filter) {  
				$lang = $this->curlang->slug;
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

			$js .= "</script>";
			echo $js;
		}
	}

	// adds the language information in the search form
	// FIXME does not work if searchform.php is used
	function get_search_form($form) {
		if ($form) {
			$this->search_form_filter = true;
			$form = str_replace('</form>', '<input type="hidden" name="lang" value="'.$this->curlang->slug.'" /></form>', $form);
		}
		return $form;
	}

	// filters the list of pages according to the current language
	// FIXME: it seems that there is currently no way to filter before the database query (3.2) -> lot of sql queries
	// cannot play with widget_pages_args filter either as it seems that get_pages does not query taxonomies :(
	// should try to improve this...
	function get_pages($pages, $r) {
		if (isset($this->curlang)) {
			foreach ($pages as $key => $page) {
				$lang = $this->get_post_language($page->ID);
				if (!$lang || $this->curlang->slug != $lang->slug)
					unset($pages[$key]);
			}
		}		
		return $pages;
	}

	// filters the comments according to the current language 
	function comments_clauses($clauses) {
		if ($this->curlang) {
			global $wpdb;
			$value = $this->curlang->term_id;
			$clauses['join'] .= " INNER JOIN $wpdb->term_relationships AS tr ON tr.object_id = ID";
			$clauses['join'] .= " INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
			$clauses['where'] .= " AND tt.term_id = $value";
		}
		return $clauses;
	}

	// Modifies the feed link to add the language parameter
	// FIXME should be an option to do this ?
	function feed_link($url, $feed) {
		global $wp_rewrite;
		$options = get_option('polylang');

		if ($this->curlang) {
			if ($wp_rewrite->using_permalinks()) {
				$home = home_url();
				$options['rewrite'] ? $base = '/' : $base = '/language/';
				$url = str_replace($home, $home.$base.$this->curlang->slug, $url);
			}
			else {
				if ($feed)
					$url = home_url('?lang='.$this->curlang->slug.'&feed='.$feed);
			}
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
			$lang_id = $this->curlang->term_id;
			$sql .= " AND term_taxonomy_id = $lang_id";
		}
		return $sql;
	}

	// modifies the archives link to add the language parameter
	function get_archives_link($link_html) {
		if ($this->curlang) {
			global $wp_rewrite;
			$options = get_option('polylang');
			$home = home_url();
			if ($wp_rewrite->using_permalinks()) {
				$options['rewrite'] ? $base = '/' : $base = '/language/';
				$link_html = str_replace($home, $home.$base.$this->curlang->slug, $link_html);
			}
			else
				$link_html = str_replace($home.'/?', $home.'/?lang='.$this->curlang->slug.'&amp;', $link_html);
		}
		return $link_html;
	}

	// returns the url of the translation (if exists) of the current page 
	function get_translation_url($language) {

		if ( is_single()) {
			$id = $this->get_post(get_the_ID(), $language);
			if ($id)
				$url = get_permalink($id);
		}

		elseif ( is_page() ) {
			$id = $this->get_post(get_the_ID(), $language);
			if ($id)
				$url = _get_page_link($id);
		}

		elseif ( is_category() || is_tag() ) {
			$term = get_queried_object();
			$term_id = $term->term_id;
			$taxonomy = $term->taxonomy;
			$lang = $this->get_term_language($term_id);

			if ($language->slug == $lang->slug)
				$url = get_term_link($term->slug, $taxonomy); // self link
			else {			
				$link_id = $this->get_translated_term($term_id, $language);
				if ($link_id) {
					$term = get_term($link_id,$taxonomy);	// We need the slug for get_term_link		
					$url = get_term_link($term->slug, $taxonomy);
				}				
			}
		}

		// FIXME for date links, if I simply modify the lang query, there is a risk that there is no posts (if all are not translated at this date)
		// do nothing for now
		elseif ( is_year() ) {
		}

		elseif ( is_month() ) {
		}

		elseif (is_home() || is_tax('language') )
			$url = get_term_link($language->slug, 'language');

		return isset($url) ? $url : null;
	}

	// filters the nav menus according to current language			
	function wp_nav_menu_args($args) {
		if (!$args['menu'] && $args['theme_location'] && $this->curlang) {
			$menu_lang = get_option('polylang_nav_menus');
			$args['menu'] = $menu_lang[$args['theme_location']][$this->curlang->slug]; 
		}
		return $args;
	} 

	// returns the home url in the right language
	function get_home_url($language) {
		$post_id = get_option('page_on_front');
		if ($post_id) {
			// a static page is used as front page
			$id = $this->get_post($post_id, $language);
			if($id)
				$url = _get_page_link($id);
		}
		return isset($url) ? $url : get_term_link($language->slug, 'language');
	}

	// adds 'lang_url' as possible value to the 'show' parameter of bloginfo to display the home url in the correct language
	// FIXME not tested
	function bloginfo_url($output, $show) {
		if ($show == 'lang_url' && $this->curlang) {
			$url = $this->get_home_url($this->curlang);
			$output = isset($url) ? $url : home_url('/');
		}
		return $output;
	} 

	// Template tag : Displays links to the current page in other languages
	// Usage : do_action('the_languages');
	function the_languages() {
		$listlanguages = $this->get_languages_list(true); // hides languages with no posts
		$output = "<ul>\n";
		foreach ($listlanguages as $language) {
			$url = $this->get_translation_url($language);
			if (!isset($url))
				$url = $this->get_home_url($language); // if the page is not translated, link to the home page
			$output .= "<li><a href='$url'>".$language->name."</a></li>\n";	
		} 
		$output .= "</ul>\n";
		echo $output;
	}

} // class Polylang

if (class_exists("Polylang"))
	new Polylang();

?>

<?php 
/*
Plugin Name: Polylang
Plugin URI: http://wordpress.org/extend/plugins/polylang/
Version: 0.4.1
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

define('POLYLANG_VERSION', '0.4.1');

define('POLYLANG_DIR', dirname(__FILE__));
define('INC_DIR', POLYLANG_DIR.'/include');

require_once(ABSPATH . 'wp-admin/includes/template.php'); // to ensure that 'get_current_screen' is defined

require_once(INC_DIR.'/base.php');
require_once(INC_DIR.'/admin.php');
require_once(INC_DIR.'/widget.php');

class Polylang extends Polylang_Base {
	var $curlang; // current language
	var $default_locale;
	var $list_textdomains = array(); // all text domains
	var $search_form_filter = false; // did we pass our get_search_form filter ?

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

			// modifies the calendar to filter posts by language
			add_filter('get_calendar', array(&$this, 'get_calendar'));

			// rewrites next and previous post links to filter them by language 
			add_filter('get_previous_post_join', array(&$this, 'posts_join'));
			add_filter('get_next_post_join', array(&$this, 'posts_join'));
			add_filter('get_previous_post_where', array(&$this, 'posts_where'));
			add_filter('get_next_post_where', array(&$this, 'posts_where'));

			// filters the nav menus according to the current language			
			add_filter('wp_nav_menu_args', array(&$this, 'wp_nav_menu_args'));

			// filters the widgets according to the current language			
			add_filter('widget_display_callback', array(&$this, 'widget_display_callback'), 10, 3);

			// modifies the home url
			add_filter('home_url', array(&$this, 'home_url'));

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
		register_taxonomy('language', get_post_types(array('show_ui' => true)), array('label' => false, 'query_var'=>'lang')); 

		// defines default values for options in case this is the first installation
		$options = get_option('polylang');
		if (!$options) {
			$options['browser'] = 1; // default language for the front page is set by browser preference
			$options['rewrite'] = 0; // do not remove /language/ in permalinks
			$options['hide_default'] = 0; // do not remove URL language information for default language
		}
		$options['version'] = POLYLANG_VERSION;
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
		register_taxonomy('language', get_post_types(array('show_ui' => true)), array(
			'label' => false,
			'public' => false, // avoid displaying the 'like post tags text box' in the quick edit
			'query_var'=>'lang',
			'update_count_callback' => '_update_post_term_count'));

		// optionaly removes 'language' in permalinks so that we get http://www.myblog/en/ instead of http://www.myblog/language/en/
		// the simple line of code is inspired by the WP No Category Base plugin: http://wordpresssupplies.com/wordpress-plugins/no-category-base/
		global $wp_rewrite;
		$options = get_option('polylang');
		if ($options['rewrite'] && $wp_rewrite->extra_permastructs)	
			$wp_rewrite->extra_permastructs['language'][0] = '%language%';

		$this->default_locale = get_locale(); // save the default locale before we start any language manipulation
		load_plugin_textdomain('polylang', false, basename(POLYLANG_DIR).'/languages'); // plugin i18n
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
				$slug = $options['default_lang'] == $language->slug && $options['hide_default'] ? '' : $language->slug . '/';
				$newrules[$slug.'feed/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?lang='.$language->slug.'&feed=$matches[1]';
				$newrules[$slug.'(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?lang='.$language->slug.'&feed=$matches[1]';
				$newrules[$slug.'page/?([0-9]{1,})/?$'] = 'index.php?lang='.$language->slug.'&paged=$matches[1]';
				if ($slug)
					$newrules[$slug.'?$'] = 'index.php?lang='.$language->slug;
			}
			unset($rules['([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$']); // => index.php?lang=$matches[1]&feed=$matches[2]
			unset($rules['([^/]+)/(feed|rdf|rss|rss2|atom)/?$']); // => index.php?lang=$matches[1]&feed=$matches[2]
			unset($rules['([^/]+)/page/?([0-9]{1,})/?$']); // => index.php?lang=$matches[1]&paged=$matches[2]
			unset($rules['([^/]+)/?$']); // => index.php?lang=$matches[1]
		}

		$base = $options['rewrite'] ? '' : 'language/';			

		// rewrite rules for comments feed filtered by language
		foreach ($listlanguages as $language) {
			$slug = $options['default_lang'] == $language->slug && $options['hide_default'] ? '' : $base.$language->slug . '/';
			$newrules[$slug.'comments/feed/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?lang='.$language->slug.'&feed=$matches[1]&withcomments=1';
			$newrules[$slug.'comments/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?lang='.$language->slug.'&feed=$matches[1]&withcomments=1';
		}
		unset($rules['comments/feed/(feed|rdf|rss|rss2|atom)/?$']); // => index.php?&feed=$matches[1]&withcomments=1
		unset($rules['comments/(feed|rdf|rss|rss2|atom)/?$']); // => index.php?&feed=$matches[1]&withcomments=1

		// rewrite rules for archives filtered by language
		foreach ($rules as $key => $rule) {
			$is_archive = strpos($rule, 'author_name=') || strpos($rule, 'year=') && !(
				strpos($rule, 'p=') ||
				strpos($rule, 'name=') ||
				strpos($rule, 'page=') ||
				strpos($rule, 'cpage=') );

			if ($is_archive) {
				foreach ($listlanguages as $language) {
					$slug = $options['default_lang'] == $language->slug && $options['hide_default'] ? '' : $base.$language->slug . '/';
					$newrules[$slug.$key] = str_replace('?', '?lang='.$language->slug.'&', $rule);
				}
				unset($rules[$key]); // now useless
			}
		}
		return $newrules + $rules;
	}

	// filters categories and post tags by language when needed (both in admin panels and frontend)
	function terms_clauses($clauses, $taxonomies, $args) {
		// does nothing except on taxonomies which have show_ui set to 1 (includes category and post_tags)
		foreach ($taxonomies as $tax) {
			if(!get_taxonomy($tax)->show_ui)
				return $clauses;
		}

		if (is_admin()) {
			$screen = get_current_screen(); // since WP 3.1 

			// NOTE: $screen is not defined in the tag cloud of the Edit Post panel ($pagenow set to admin-ajax.php)
			if (isset($screen))
				// does nothing in the Categories, Post tags, Languages and Posts* admin panels
				if ($screen->base == 'edit-tags' || $screen->base == 'toplevel_page_mlang' || $screen->base == 'edit')
					return $clauses;

				// *FIXME I want all categories in the dropdown list and only the ones in the right language in the inline edit
				// It seems that I need javascript to get the post_id as inline edit data are manipulated in inline-edit-post.js

			$this->curlang = $this->get_current_language();
		}

		// adds our clauses to filter by current language
		if ($this->curlang) {
			global $wpdb;
			$clauses['join'] .= " INNER JOIN $wpdb->termmeta AS tm ON t.term_id = tm.term_id";
			$clauses['where'] .= $wpdb->prepare(" AND tm.meta_key = '_language' AND tm.meta_value = %d", $this->curlang->term_id);
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
			$pref_lang = $this->get_language($options['default_lang']);

		return $pref_lang;
	}

	// returns the current language
	function get_current_language() {	
		global $post_ID;

		if($this->curlang)
			return $this->curlang;

		// no language set for 404 and attachment
		if (is_404() || is_attachment())
			return $this->get_preferred_language();

		if (is_admin()) {
			if (isset($post_ID)) 
				$lang = $this->get_post_language($post_ID);

			if (isset($_POST['action']) && $_POST['action'] == 'get-tagcloud') {
				// to get the language in the Post Tags metabox in the Edit Post screen (as $post_ID not defined)
				$qvars = $this->get_referer_vars();
				if (isset($qvars['new_lang']))
					$lang= $this->get_language($qvars['new_lang']); // post has been created with 'add new' (translation)
				elseif (isset($qvars['post']))
					$lang = $this->get_post_language($qvars['post']); // edit post
			}

			// the post is created with the 'add new' (translation) link
			if (isset($_GET['new_lang']))
				$lang = $this->get_language($_GET['new_lang']);
		}
		elseif ($var = get_query_var('lang'))
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

		global $wp_locale;
		$wp_locale->init(); // reinitializes wp_locale for weekdays and months
	}

	// filters posts according to the language
	function pre_get_posts($query) {
		$options = get_option('polylang');
		$qvars = $query->query_vars;

		// detect our exclude pages query and returns to avoid conflicts
		// this test should be sufficient
		if (isset($qvars['tax_query'][0]) && isset($qvars['tax_query'][0]['taxonomy']) && isset($qvars['tax_query'][0]['operator']))
			return;

		if (empty($query->query)) {
			if ( $options['hide_default'] && isset($_COOKIE['wordpress_polylang']) )
				$this->curlang = $this->get_language($options['default_lang']);
			else
				$this->curlang = $this->get_preferred_language(); // sets the language according to browser preference or default language

			if ($options['default_lang'] == $this->curlang->slug && $options['hide_default']) {
				if (($post_id = get_option('page_on_front')) && $link_id = $this->get_post($post_id, $this->curlang))
					$query->set('page_id', $link_id);
				else
					$query->set('lang', $this->curlang->slug);
			}
			else {
				if (($post_id = get_option('page_on_front')) && $link_id = $this->get_post($post_id, $this->curlang))
					$url = _get_page_link($link_id);
				else
					$url = home_url('?lang='.$this->curlang->slug);

				wp_redirect($url);
				exit;
			}	
		}

		// FIXME to generalize as I probably forget things
		// sets the language in case we hide the default language
		if ( $options['hide_default'] && !isset($qvars['lang']) && (
			(count($query->query) == 1 && isset($qvars['paged']) && $qvars['paged']) ||
			isset($qvars['m']) && $qvars['m'] ||
			isset($qvars['feed']) && $qvars['feed'] ||
			isset($qvars['author']) && $qvars['author']))
				$query->set('lang', $options['default_lang']);

		// filters recent posts to the current language
		// FIXME if $qvars['post_type'] == 'nav_menu_item', setting lang breaks custom menus.
		// since to get nav_menu_items, get_post is used and no language is linked to nav_menu_items
		if ($query->is_home && $this->curlang && !isset($qvars['post_type'])) 
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
		foreach ($this->get_languages_list() as $language) {
			if ($language->slug != $this->curlang->slug && $url = $this->get_translation_url($language))
				printf("<link hreflang='%s' href='%s' rel='alternate' />\n", esc_attr($language->slug), esc_url($url));
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
		if (isset($this->curlang)) {
			$q = array(
				'numberposts'=>-1,
				'post_type' => 'page',
				'fields' => 'ids',
				'tax_query' => array(array(
					'taxonomy'=>'language',
					'fields' => 'id',
					'terms'=>$this->curlang->term_id,
					'operator'=>'NOT IN'
				))
			);
			$pages = array_merge($pages, get_posts($q));
		}	
		return $pages;
	}

	// filters the comments according to the current language mainly for the recent comments widget
	function comments_clauses($clauses, $comment_query) {
		// first test if wp_posts.ID already available in the query
		if ($this->curlang && strpos($clauses['join'], '.ID')) {
			global $wpdb;
			$clauses['join'] .= " INNER JOIN $wpdb->term_relationships AS tr ON tr.object_id = ID";
			$clauses['join'] .= " INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
			$clauses['where'] .= $wpdb->prepare(" AND tt.term_id = %d", $this->curlang->term_id);
		}
		return $clauses;
	}

	// Modifies the feed link to add the language parameter
	function feed_link($url, $feed) {
		global $wp_rewrite;
		$options = get_option('polylang');

		if ($this->curlang) {
			if ($wp_rewrite->using_permalinks()) {
				$home = get_option('home');
				$base = $options['rewrite'] ? '/' : '/language/';
				$slug = $options['default_lang'] == $this->curlang->slug && $options['hide_default'] ? '' : $base.$this->curlang->slug;
				$url = esc_url(str_replace($home, $home.$slug, $url));
			}
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
			$sql .= $wpdb->prepare(" AND term_taxonomy_id = %d", $this->curlang->term_id);
		}
		return $sql;
	}

	// modifies the author and date links to add the language parameter
	function archive_link($link) {
		if ($this->curlang) {
			global $wp_rewrite;
			$options = get_option('polylang');
			$home = get_option('home');

			if ($wp_rewrite->using_permalinks()) {
				$base = $options['rewrite'] ? '/' : '/language/';
				$slug = $options['default_lang'] == $this->curlang->slug && $options['hide_default'] ? '' : $base.$this->curlang->slug;
				$link = esc_url(str_replace($home, $home.$slug, $link));
			}
			else
				$link = esc_url(str_replace($home.'/?', $home.'/?lang='.$this->curlang->slug.'&amp;', $link));
		}
		return $link;
	}

	// modifies the calendar to filter posts by language
	function get_calendar() {
		require_once(INC_DIR.'/calendar.php');
		return $calendar_output;
	}

	// returns the url of the translation (if exists) of the current page 
	function get_translation_url($language) {
		global $wp_query, $wp_rewrite;
		$qvars = $wp_query->query;
		$options = get_option('polylang');
		$hide = $options['default_lang'] == $language->slug && $options['hide_default'];

		// is_single is set to 1 for attachment but no language is set
		if (is_single() && !is_attachment() && $id = $this->get_post(get_the_ID(), $language))
			$url = get_permalink($id);

		elseif (is_page() && $id = $this->get_post(get_the_ID(), $language))
			$url = $hide && $id == $this->get_post(get_option('page_on_front'), $language) ?
				get_option('home') :
				_get_page_link($id);

		elseif ( !is_tax ('language') && (is_category() || is_tag() || is_tax () ) ) {
			$term = get_queried_object();
			$lang = $this->get_term_language($term->term_id);
			$taxonomy = $term->taxonomy;

			if ($language->slug == $lang->slug)
				$url = get_term_link($term, $taxonomy); // self link
			elseif ($link_id = $this->get_translated_term($term->term_id, $language))
				$url = get_term_link(get_term($link_id, $taxonomy), $taxonomy);
		}

		// don't test if there are existing translations before creating the url as it would be very expensive in sql queries
		elseif(is_archive()) {
			if ($wp_rewrite->using_permalinks()) {
				$base = $options['rewrite'] ? '/' : '/language/';
				$base = $hide ? '' : $base.$language->slug;
				$base = get_option('home').$base.'/';

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
			$url = $hide ? get_option('home') : get_term_link($language, 'language');

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

	// filters the home url to get the right language
	function home_url($url) {
		if ( !(did_action('template_redirect') && rtrim($url,'/') == rtrim(get_option('home'),'/') && $this->curlang) )
			return $url;

		// don't like this but at least for WP_Widget_Categories::widget, it seems to be the only solution
		// FIXME are there other exceptions ?
		foreach (debug_backtrace() as $trace) {
			$exceptions = $trace['function'] == 'get_pagenum_link' ||
				$trace['function'] == 'get_author_posts_url' ||
				($trace['function'] == 'widget' && $trace['class'] == 'WP_Widget_Categories');
			if ($exceptions)
				return $url;
		}

		return $this->get_home_url($this->curlang);
	}

	// returns the home url in the right language
	function get_home_url($language) {
		$options = get_option('polylang');
		if ($options['default_lang'] == $language->slug && $options['hide_default'])
			return trailingslashit(get_option('home'));

		// a static page is used as front page
		if (($post_id = get_option('page_on_front')) && $id = $this->get_post($post_id, $language))
			$url = _get_page_link($id);

		return isset($url) ? $url : get_term_link($language, 'language');
	}

	// adds 'lang_url' as possible value to the 'show' parameter of bloginfo to display the home url in the correct language
	// FIXME not tested
	function bloginfo_url($output, $show) {
		if ($show == 'lang_url' && $this->curlang) {
			$url = $this->get_home_url($this->curlang);
			$output = isset($url) ? $url : get_option('home');
		}
		return $output;
	} 

	// Template tag: Displays links to the current page in other languages
	// Usage: do_action('the_languages');
	function the_languages($args = '') {
		$defaults = array(
			'dropdown' => 0, // display as list and not as dropdown
			'show_names' => 1, // show language names
			'show_flags' => 0, // don't show flags
			'hide_if_empty' => 1 // hides languages with no posts (or pages)
		);
		extract(wp_parse_args($args, $defaults));

		$listlanguages = $this->get_languages_list($hide_if_empty);
		$output = $dropdown ? '<select name="lang_choice" id="lang_choice">' : "<ul>\n";

		foreach ($listlanguages as $language) {
			if ($dropdown) {
				$output .= sprintf(
					"<option value='%s'%s>%s</option>\n",
					esc_attr($language->slug),
					$language->slug == $this->curlang->slug ? ' selected="selected"' : '',
					esc_attr($language->name) // FIXME flag does not work for the dropdown list
				);
			}
			else {
				$url = $this->get_translation_url($language);
				$url = isset($url) ? $url : $this->get_home_url($language); // if the page is not translated, link to the home page

				$class = 'lang-item lang-item-'.esc_attr($language->term_id);
				$class .= $language->slug == $this->curlang->slug ? ' current-lang' : '';

				$flag = $show_flags && (
					file_exists(POLYLANG_DIR.($file = '/local_flags/'.$language->description.'.png')) ||
					file_exists(POLYLANG_DIR.($file = '/flags/'.$language->description.'.png')) ) ? 
					'<img src="'.esc_url(WP_PLUGIN_URL.'/polylang'.$file).'" alt="'.esc_attr($language->name).'" />' : '';

				$name = $show_names || !$show_flags ? esc_attr($language->name) : '';

				$output .= '<li class="'.$class.'"><a href="'.esc_url($url).'">'.($show_flags && $show_names ? $flag.'&nbsp;'.$name : $flag.$name)."</a></li>\n";	
			}
		}

		$output .= $dropdown ? '</select>' : "</ul>\n";
		echo $output;
	}

} // class Polylang

if (class_exists("Polylang"))
	new Polylang();

?>

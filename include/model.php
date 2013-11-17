<?php

/*
 * setups the language and translations model based on WordPress taxonomies
 *
 * @since 1.2
 */
class PLL_Model {
	public $options;
	public $post_types = array(), $taxonomies = array(); // post types & taxonomies to filter by language
	private $languages; // used to cache the list of languages

	/*
	 * constructor: registers custom taxonomies and setups filters and actions
	 *
	 * @since 1.2
	 *
	 * @param array $options Polylang options
	 */
	public function __construct(&$options) {
		$this->options = &$options;

		// register our taxonomies as soon as possible
		// this is early registration, not ready for rewrite rules as wp_rewrite will be setup later
		// FIXME should I supply an 'update_count_callback' for taxonomies other than 'language' (currently not needed by PLL)?
		foreach (array('language', 'term_language', 'post_translations', 'term_translations') as $tax)
			register_taxonomy($tax,
				false !== strpos($tax, 'term_') ? 'term' : null ,
				array('label' => false, 'public' => false, 'query_var' => false, 'rewrite' => false, '_pll' => true)
			);

		add_filter('get_terms', array(&$this, '_prime_terms_cache'), 10, 2);
		add_filter('wp_get_object_terms', array(&$this, 'wp_get_object_terms'), 10, 3);

		add_action('edited_term_taxonomy', array(&$this, 'clean_languages_cache'), 10, 2);

		// registers completely the language taxonomy
		add_action('setup_theme', array(&$this, 'register_taxonomy'), 1);

		// setups post types and taxonomies to translate
		add_action('registered_post_type', array(&$this, 'registered_post_type'));
		add_action('registered_taxonomy', array(&$this, 'registered_taxonomy'));

		// just in case someone would like to display the language description ;-)
		add_filter('language_description', create_function('$v', "return '';"));
	}

	/*
	 * cache language and translations when terms are queried by get_terms
	 *
	 * @since 1.2
	 *
	 * @param array $terms queried terms
	 * @param array $taxonomies queried taxonomies
	 * @return array unmodified $terms
	 */
	public function _prime_terms_cache($terms, $taxonomies) {
		foreach ($terms as $term) {
			if (is_object($term)) {
				if (in_array($term->taxonomy, $this->taxonomies))
					$term_ids[] = $term->term_id;
			}
			elseif (array_intersect($taxonomies, $this->taxonomies))
				$term_ids[] = $term;
		}

		if (!empty($term_ids))
			update_object_term_cache(array_unique($term_ids), 'term'); // adds language and translation of terms to cache
		return $terms;
	}

	/*
	 * when terms are found for posts, add their language and translations to cache
	 *
	 * @since 1.2
	 *
	 * @param array $terms terms found
	 * @param array $object_ids not used
	 * @param array $taxonomies terms taxonomies
	 * @return array unmodified $terms
	 */
	public function wp_get_object_terms($terms, $object_ids, $taxonomies) {
		$taxonomies = explode("', '", trim($taxonomies, "'"));
		if (!in_array('term_translations', $taxonomies))
			$this->_prime_terms_cache($terms, $taxonomies);
		return $terms;
	}

	/*
	 * wrap wp_get_object_terms to cache it and return only one object
	 * inspired by the function get_the_terms
	 *
	 * @since 1.2
	 *
	 * @param int $object_id post_id or term_id
	 * @param string $taxonomy Polylang taxonomy depending if we are looking for a post (or term) language (or translation)
	 * @return bool|object the term associated to the object in the requested taxonomy if exists, false otherwise
	 */
	protected function get_object_term($object_id, $taxonomy) {
		$term = get_object_term_cache($object_id, $taxonomy);

		if ( false === $term ) {
			// query language and translations at the same time
			$taxonomies = (false !== strpos($taxonomy, 'term_')) ?
				array('term_language', 'term_translations') :
				array('language', 'post_translations');

			foreach (wp_get_object_terms($object_id, $taxonomies) as $t) {
				wp_cache_add($object_id, array($t), $t->taxonomy . '_relationships'); // store it the way WP wants it
				if ($t->taxonomy == $taxonomy)
					$term = $t;
			}
		}
		else
			$term = reset($term);

		return empty($term) ? false : $term;
	}

	/*
	 * returns the list of available languages
	 * caches the list in a db transient (except flags)
	 * caches the list (with flags) in the private property $languages
	 *
	 * list of parameters accepted in $args:
	 *
	 * hide_empty => hides languages with no posts if set to true (defaults to false)
	 * fields     => return only that field if set (see PLL_Language for a list of fields)
	 *
	 * @since 0.1
	 *
	 * @param array $args
	 * @return array|string|int list of PLL_Language objects or PLL_Language object properties
	 */
	public function get_languages_list($args = array()) {
		if (empty($this->languages)) {
			if (false === ($languages = get_transient('pll_languages_list'))) {
				$languages = get_terms('language', array('hide_empty' => false, 'orderby'=> 'term_group'));
				$languages = empty($languages) || is_wp_error($languages) ? array() : $languages;

				$term_languages = get_terms('term_language', array('hide_empty' => false));
				$term_languages = empty($term_languages) || is_wp_error($term_languages) ?
					array() : array_combine(wp_list_pluck($term_languages, 'name'), $term_languages);

				if (!empty($languages) && !empty($term_languages)) {
					array_walk($languages, create_function('&$v, $k, $term_languages', '$v = new PLL_Language($v, $term_languages[$v->name]);'), $term_languages);
					set_transient('pll_languages_list', $languages);
				}
				else
					$languages = array(); // in case something went wrong
			}

			// add flags (not in db cache as they may be different on frontend and admin)
			foreach ($languages as $lang)
				$lang->set_flag();

			$this->languages = $languages;
		}

		$args = wp_parse_args($args, array('hide_empty' => false));
		extract($args);

		// remove empty languages if requested
		$languages = array_filter($this->languages, create_function('$v', sprintf('return $v->count || !%d;', $hide_empty)));

		return empty($fields) ? $languages : wp_list_pluck($languages, $fields);
	}

	/*
	 * cleans language cache
	 * can be called directly with no parameter
	 * called by the 'edited_term_taxonomy' filter with 2 parameters when count needs to be updated
	 *
	 * @since 1.2
	 *
	 * @param int $term not used
	 * @param string $taxonomy taxonomy name
	 */
	public function clean_languages_cache($term = 0, $taxonomy = null) {
		if (empty($taxonomy->name) || 'language' == $taxonomy->name) {
			delete_transient('pll_languages_list');
			$this->languages = array();
		}
	}

	/*
	 * returns the language by its term_id, tl_term_id, slug or locale
	 *
	 * @since 0.1
	 *
	 * @param int|string term_id, tl_term_id, slug or locale of the queried language
	 * @return object|bool PLL_Language object, false if no language found
	 */
	public function get_language($value) {
		static $language;

		if (is_object($value))
			return $this->get_language($value->term_id); // will force cast to PLL_Language

		if (empty($language[$value])) {
			foreach ($this->get_languages_list() as $lang)
				$language[$lang->term_id] = $language[$lang->tl_term_id] = $language[$lang->slug] = $language[$lang->locale] = $lang;
		}

		return empty($language[$value]) ? false : $language[$value];
	}

	/*
	 * saves translations for posts or terms
	 *
	 * @since 0.5

	 * @param string $type either 'post' or 'term'
	 * @param int $id post id or term id
	 * @param array $translations: an associative array of translations with language code as key and translation id as value
	 */
	public function save_translations($type, $id, $translations) {
		if (($lang = call_user_func(array(&$this, 'get_'.$type.'_language'), $id)) && isset($translations) && is_array($translations)) {
			// first unlink this object
			foreach ($this->get_translations($type, $id) as $object_id)
				$this->delete_translation($type, $object_id);

			$translations = array_merge(array($lang->slug => $id), $translations); // make sure this object is in tranlations
			$translations = array_diff($translations, array(0)); // don't keep non translated languages

			// don't create a translation group for untranslated posts as it is useless
			// but we need one for terms to allow relationships remap when importing from a WXR file
			if ('term' == $type || count($translations) > 1) {
				$terms = wp_get_object_terms($translations, $type . '_translations');
				$term = array_pop($terms);

				// create a new term if necessary
				empty($term) ?
					wp_insert_term($group = uniqid('pll_'), $type . '_translations', array('description' => serialize($translations))) :
					wp_update_term($group = (int) $term->term_id, $type . '_translations', array('description' => serialize($translations)));

				// link all translations to the new term
				foreach($translations as $p)
					wp_set_object_terms($p, $group, $type . '_translations');
			}
		}
	}

	/*
	 * deletes a translation of a post or term
	 *
	 * @since 0.5
	 *
	 * @param string $type either 'post' or 'term'
	 * @param int $id post id or term id
	 */
	public function delete_translation($type, $id) {
		$term = $this->get_object_term($id, $type . '_translations');

		if (!empty($term)) {
			$translations = unserialize($term->description);

			if (is_array($translations)) {
				$slug = array_search($id, $translations);
				unset($translations[$slug]);

				empty($translations) || 1 == count($translations) ?
					wp_delete_term((int) $term->term_id, $type . '_translations') :
					wp_update_term((int) $term->term_id, $type . '_translations', array('description' => serialize($translations)));

				wp_set_object_terms($id, null, $type . '_translations');
			}
		}
	}

	/*
	 * returns the id of the translation of a post or term
	 *
	 * @since 0.5
	 *
	 * @param string $type either 'post' or 'term'
	 * @param int $id post id or term id
	 * @param object|string $lang object or slug
	 * @return bool|int post id or term id of the translation, flase if there is none
	 */
	public function get_translation($type, $id, $lang) {
		$translations = $this->get_translations($type, $id);
		$slug = $this->get_language($lang)->slug;
		return isset($translations[$slug]) ? (int) $translations[$slug] : false;
	}

	/*
	 * returns an array of translations of a post or term
	 *
	 * @since 0.5
	 *
	 * @param string $type either 'post' or 'term'
	 * @param int $id post id or term id
	 * @return array an associative array of translations with language code as key and translation id as value
	 */
	public function get_translations($type, $id) {
		$type = ($type == 'post' || in_array($type, $this->post_types)) ? 'post' : (($type == 'term' || in_array($type, $this->taxonomies)) ? 'term' : false);
		return $type && ($term = $this->get_object_term($id, $type . '_translations')) && !empty($term) ? unserialize($term->description) : array();
	}

	/*
	 * store the post language in the database
	 *
	 * @since 0.6
	 *
	 * @param int $post_id post id
	 * @param int|string|object language (term_id or slug or object)
	 */
	public function set_post_language($post_id, $lang) {
		wp_set_post_terms($post_id, $lang ? $this->get_language($lang)->slug : '', 'language' );
	}

	/*
	 * returns the language of a post
	 *
	 * @since 0.1
	 *
	 * @param int $post_id post id
	 * @return bool|object PLL_Language object, false if no language is associated to that post
	 */
	public function get_post_language($post_id) {
		$lang = $this->get_object_term($post_id, 'language' );
		return ($lang) ? $this->get_language($lang) : false;
	}

	/*
	 * among the post and its translations, returns the id of the post which is in $lang
	 *
	 * @since 0.1
	 *
	 * @param int $post_id post id
	 * @param int|string|object language (term_id or slug or object)
	 * @return bool|int the translation post id if exists, otherwise the post id, false if the post has no language
	 */
	public function get_post($post_id, $lang) {
		$post_lang = $this->get_post_language($post_id); // FIXME is this necessary?
		if (!$lang || !$post_lang)
			return false;

		$lang = $this->get_language($lang);
		return $post_lang->term_id == $lang->term_id ? $post_id : $this->get_translation('post', $post_id, $lang);
	}

	/*
	 * stores the term language in the database
	 *
	 * @since 0.6
	 *
	 * @param int $term_id term id
	 * @param int|string|object language (term_id or slug or object)
	 */
	public function set_term_language($term_id, $lang) {
		wp_set_object_terms($term_id, $lang ? $this->get_language($lang)->tl_term_id : '', 'term_language');
	}

	/*
	 * removes the term language in database
	 *
	 * @since 0.5
	 *
	 * @param int $term_id term id
	 */
	public function delete_term_language($term_id) {
		wp_delete_object_term_relationships($term_id, 'term_language');
	}

	/*
	 * returns the language of a term
	 *
	 * @since 0.1
	 *
	 * @param int|string $value term id or term slug
	 * @param string $taxonomy optional taxonomy needed when the term slug is passed as first parameter
	 * @return bool|object PLL_Language object, false if no language is associated to that term
	 */
	public function get_term_language($value, $taxonomy = '') {
		if (is_numeric($value))
			$term_id = $value;

		// get_term_by still not cached in WP 3.5.1 but internally, the function is always called by term_id
		elseif (is_string($value) && $taxonomy)
			$term_id = get_term_by('slug', $value , $taxonomy)->term_id;

		$lang = $this->get_object_term($term_id, 'term_language');

		// switch to PLL_Language
		return ($lang) ? $this->get_language($lang->term_id) : false;
	}

	/*
	 * among the term and its translations, returns the id of the term which is in $lang
	 *
	 * @since 0.1
	 *
	 * @param int $term_id term id
	 * @param int|string|object language (term_id or slug or object)
	 * @return bool|int the translation term id if exists, otherwise the term id, false if the term has no language
	 */
	public function get_term($term_id, $lang) {
		$lg = $this->get_term_language($term_id); // FIXME is this necessary?
		if (!$lang || !$lg)
			return false;

		$lang = $this->get_language($lang);
		return $lg->term_id == $lang->term_id ? $term_id : $this->get_translation('term', $term_id, $lang);
	}

	/*
	 * a join clause to add to sql queries when filtering by language is needed directly in query
	 *
	 * @since 1.2
	 *
	 * @param string $type either 'post' or 'term'
	 * @return string join clause
	 */
	public function join_clause($type) {
		global $wpdb;
		return " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = " . ('term' == $type ? "t.term_id" : "ID");
	}

	/*
	 * a where clause to add to sql queries when filtering by language is needed directly in query
	 *
	 * @since 1.2
	 *
	 * @param object|array|string $lang a PLL_Language object or a comma separated list of languag slug or an array of language slugs
	 * @param string $type either 'post' or 'term'
	 * @return string where clause
	 */
	public function where_clause($lang, $type) {
		global $wpdb;
		$tt_id = 'term' == $type ? 'tl_term_taxonomy_id' : 'term_taxonomy_id';

		// $lang is an object
		// generally the case if the query is coming from Polylang
		if (is_object($lang))
			return $wpdb->prepare(" AND pll_tr.term_taxonomy_id = %d", $lang->$tt_id);

		// $lang is a comma separated list of slugs (or an array of slugs)
		// generally the case is the query is coming from outside with 'lang' parameter
		$slugs = is_array($lang) ? $lang : explode(',', $lang);
		foreach ($slugs as $slug)
			$languages[] = (int) $this->get_language($slug)->$tt_id;

		return " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")";
	}

	/*
	 * adds clauses to comments query to filter them by language - used in both frontend and admin
	 *
	 * @since 1.2
	 *
	 * @param array $clauses the list of sql clauses in comments query
	 * @param object $lang PLL_Language object
	 * @return array modifed list of clauses
	 */
	public function comments_clauses($clauses, $lang) {
		global $wpdb;
		if (!empty($lang)) {
			// if this clause is not already added by WP
			if (!strpos($clauses['join'], '.ID'))
				$clauses['join'] .= " JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID";

			$clauses['join'] .= $this->join_clause('post');
			$clauses['where'] .= $this->where_clause($lang, 'post');
		}
		return $clauses;
	}

	/*
	 * adds terms clauses to get_terms to filter them by languages - used in both frontend and admin
	 *
	 * @since 1.2
	 *
	 * @param array $clauses the list of sql clauses in terms query
	 * @param object $lang PLL_Language object
	 * @return array modifed list of clauses
	 */
	public function terms_clauses($clauses, $lang) {
		if (!empty($lang)) {
			$clauses['join'] .= $this->join_clause('term');
			$clauses['where'] .= $this->where_clause($lang, 'term');
		}
		return $clauses;
	}

	/*
	 * register the language taxonomy
	 *
	 * @since 1.2
	 */
	public function register_taxonomy() {
		// registers the language taxonomy
		// object types will be set later once all custom post types are registered
		register_taxonomy('language', $this->post_types, array(
			'labels' => array(
				'name' => __('Languages', 'polylang'),
				'singular_name' => __('Language', 'polylang'),
				'all_items' => __('All languages', 'polylang'),
			),
			'public' => false, // avoid displaying the 'like post tags text box' in the quick edit
			'query_var' => 'lang',
			'update_count_callback' => '_update_post_term_count',
			'_pll' => true // polylang taxonomy
		));
	}

	/*
	 * post types that need to be translated
	 *
	 * @since 1.2
	 *
	 * @return array array of post types names for which Polylang manages languages and translations
	 */
	protected function get_post_types() {
		$post_types = array('post' => 'post', 'page' => 'page');
		if (!empty($this->options['media_support']))
			$post_types['attachement'] = 'attachment';

		if (is_array($this->options['post_types']))
			$post_types = array_merge($post_types,  $this->options['post_types']);

		return apply_filters('pll_get_post_types', $post_types , false);
	}

	/*
	 * returns valid registered post types that need to be translated
	 *
	 * @since 1.2
	 *
	 * @return array array of registered post type names for which Polylang manages languages and translations
	 */
	public function get_translated_post_types() {
		return $this->post_types = array_intersect($this->get_post_types(), get_post_types());
	}

	/*
	 * check if registered post type must be translated
	 *
	 * @since 1.2
	 *
	 * @param string $post_type post type name
	 */
	public function registered_post_type($post_type) {
		if (in_array($post_type, $this->get_post_types())) {
			$this->post_types[$post_type] = $post_type;
			register_taxonomy_for_object_type('language', $post_type);
			register_taxonomy_for_object_type('post_translations', $post_type);
		}
	}

	/*
	 * returns true if Polylang manages languages and translations for this post type
	 *
	 * @since 1.2
	 *
	 * @param string|array $post_type post type name or array of post type names
	 */
	public function is_translated_post_type($post_type) {
		return (is_array($post_type) && array_intersect($post_type, $this->post_types) || in_array($post_type, $this->post_types));
	}

	/*
	 * taxonomies that need to be translated
	 *
	 * @since 1.2
	 *
	 * @return array array of taxonomy names for which Polylang manages languages and translations
	 */
	protected function get_taxonomies() {
		$taxonomies = array('category' => 'category', 'post_tag' => 'post_tag');

		if (is_array($this->options['taxonomies']))
			$taxonomies = array_merge($taxonomies, $this->options['taxonomies']);

		return apply_filters('pll_get_taxonomies', $taxonomies, false);
	}

	/*
	 * return valid registered taxonomies that need to be translated
	 *
	 * @since 1.2
	 *
	 * @return array array of registered taxonomy names for which Polylang manages languages and translations
	 */
	public function get_translated_taxonomies() {
		return $this->taxonomies = array_intersect($this->get_taxonomies(), get_taxonomies());
	}

	/*
	 * check if registered post type must be translated
	 *
	 * @since 1.2
	 *
	 * @param string $taxonomy taxonomy name
	 */
	public function registered_taxonomy($taxonomy) {
		if (in_array($taxonomy, $this->get_taxonomies()))
			$this->taxonomies[$taxonomy] = $taxonomy;
	}

	/*
	 * returns true if Polylang manages languages and translations for this post type
	 *
	 * @since 1.2
	 *
	 * @param string|array $tax taxonomy name or array of taxonomy names
	 */
	public function is_translated_taxonomy($tax) {
		return (is_array($tax) && array_intersect($tax, $this->taxonomies) || in_array($tax, $this->taxonomies));
	}

	/*
	 * it is possible to have several terms with the same name in the same taxonomy (one per language)
	 * but the native get_term_by will return only one term
	 * so here the function adds the language parameter
	 *
	 * @since 1.2
	 *
	 * @param string $field currently the only possibility is 'name'
	 * @param string $value the term name
	 * @param string $taxonomy taxonomy name
	 * @param string|object $language the language slug or object
	 * @return null|int the term_id of the found term
	 */
	public function get_term_by($field, $value, $taxonomy, $language) {
		global $wpdb;

		if ('name' != $field)
			return NULL;

		return $wpdb->get_row("SELECT t.*, tt.* FROM $wpdb->terms AS t"
			. " INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id"
			. $this->join_clause('term')
			. $wpdb->prepare(" WHERE tt.taxonomy = %s AND t.name = %s", $taxonomy, $value)
			. $this->where_clause($this->get_language($language), 'term')
		);
	}

	/*
	 * gets the number of posts per language in a date, author or post type archive
	 *
	 * @since 1.2
	 *
	 * @param object lang
	 * @param array $args WP_Query arguments (accepted: post_type, m, year, monthnum, day, author, author_name)
	 * @return int
	 */
	public function count_posts($lang, $args = array()) {
		global $wpdb;

		$q = wp_parse_args($args, array('post_type' => 'post'));

		$cache_key = md5(serialize($q));
		$counts = wp_cache_get($cache_key, 'pll_count_posts');

		if (false === $counts) {
			$where = " WHERE post_status = 'publish'";
			$where .= $wpdb->prepare(" AND {$wpdb->posts}.post_type = %s", $q['post_type']);

			if (!empty($q['m'])) {
				$q['m'] = '' . preg_replace('|[^0-9]|', '', $q['m']);
				$where .= $wpdb->prepare(" AND YEAR({$wpdb->posts}.post_date) = %d", substr($q['m'], 0, 4));
				if ( strlen($q['m']) > 5 )
					$where .= $wpdb->prepare(" AND MONTH({$wpdb->posts}.post_date) = %d", substr($q['m'], 4, 2));
				if ( strlen($q['m']) > 7 )
					$where .= $wpdb->prepare(" AND  DAYOFMONTH({$wpdb->posts}.post_date) = %d", substr($q['m'], 6, 2));
			}

			if (!empty($q['year']))
				$where .= $wpdb->prepare(" AND YEAR({$wpdb->posts}.post_date) = %d", $q['year']);

			if (!empty($q['monthnum']))
				$where .= $wpdb->prepare(" AND MONTH({$wpdb->posts}.post_date) = %d", $q['monthnum']);

			if (!empty($q['day']))
				$where .= $wpdb->prepare(" AND  DAYOFMONTH({$wpdb->posts}.post_date) = %d", $q['day']);

			if (!empty($q['author_name'])) {
				$author = get_user_by('slug',  sanitize_title_for_query($q['author_name']));
				if ($author)
					$q['author'] = $author->ID;
			}

			if (!empty($q['author']))
				$where .= $wpdb->prepare(" AND {$wpdb->posts}.post_author = %d", $q['author']);

			$select = "SELECT pll_tr.term_taxonomy_id, COUNT(*) AS num_posts FROM {$wpdb->posts}";
			$join = $this->join_clause('post');
			$where .= $this->where_clause($this->get_languages_list(), 'post');
			$groupby = " GROUP BY pll_tr.term_taxonomy_id";

			$res = $wpdb->get_results($select . $join . $where . $groupby, ARRAY_A);
			foreach ((array) $res as $row)
				$counts[$row['term_taxonomy_id']] = $row['num_posts'];

			wp_cache_set($cache_key, $counts, 'pll_count_posts');
		}

		return empty($counts[$lang->term_taxonomy_id]) ? 0 : $counts[$lang->term_taxonomy_id];
	}
}

<?php

/*
 * links model for use when the language code is added in url as a directory
 * for example mysite.com/en/something
 * implements the "links_model interface"
 *
 * @since 1.2
 */
class PLL_Links_Directory {
	public $model, $options;
	protected $home, $rewrite_rules = array();
	protected $always_rewrite = array('date', 'root', 'comments', 'search', 'author', 'post_format', 'language');

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $model PLL_Model instance
	 */
	public function __construct($model) {
		$this->model = &$model;
		$this->options = &$model->options;
		$this->home = get_option('home');

		add_action ('setup_theme', array(&$this, 'add_permastruct'));

		// make sure to prepare rewrite rules when flushing
		add_action ('pre_option_rewrite_rules', array(&$this, 'prepare_rewrite_rules'));
	}

	/*
	 * adds the language code in url
	 * links_model interface
	 *
	 * @since 1.2
	 *
	 * @param string $url url to modify
	 * @param object $lang language
	 * @return string modified url
	 */
	public function add_language_to_link($url, $lang) {
		if (!empty($lang)) {
			global $wp_rewrite;

			$base = $this->options['rewrite'] ? '' : 'language/';
			$slug = $this->options['default_lang'] == $lang->slug && $this->options['hide_default'] ? '' : $base.$lang->slug.'/';
			return str_replace($this->home.'/'.$wp_rewrite->root, $this->home.'/'.$wp_rewrite->root.$slug, $url);
		}
		return $url;
	}

	/*
	 * returns the url without language code
	 * links_model interface
	 *
	 * @since 1.2
	 *
	 * @param string $url url to modify
	 * @return string modified url
	 */
	function remove_language_from_link($url) {
		foreach ($this->model->get_languages_list() as $language)
			if (!$this->options['hide_default'] || $this->options['default_lang'] != $language->slug)
				$languages[] = $language->slug;

		if (!empty($languages)) {
			global $wp_rewrite;
			$pattern = '#' . ($this->options['rewrite'] ? '' : 'language\/') . '('.implode('|', $languages).')\/#';
			$url = preg_replace($pattern, $wp_rewrite->root, $url);
		}
		return $url;
	}

	/*
	 * returns the link to the first page
	 * links_model interface
	 *
	 * @since 1.2
	 *
	 * @param string $url url to modify
	 * @return string modified url
	 */
	function remove_paged_from_link($url) {
		return preg_replace('#\/page\/[0-9]+\/#', '/', $url);
	}

	/*
	 * returns the language based on language code in url
	 * links_model interface
	 *
	 * @since 1.2
	 *
	 * @return string language slug
	 */
	public function get_language_from_url() {
		$root = $this->options['rewrite'] ? '' : 'language/';
		$pattern = '#\/'.$root.'('.implode('|', $this->model->get_languages_list(array('fields' => 'slug'))).')\/#';
		return preg_match($pattern, trailingslashit($_SERVER['REQUEST_URI']), $matches) ? $matches[1] : ''; // $matches[1] is the slug of the requested language
	}

	/*
	 * optionaly removes 'language' in permalinks so that we get http://www.myblog/en/ instead of http://www.myblog/language/en/
	 *
	 * @since 1.2
	 */
	function add_permastruct() {
		// language information always in front of the uri ('with_front' => false)
		// the 3rd parameter structure has been modified in WP 3.4
		// backward compatibility WP < 3.4
		add_permastruct('language', $this->options['rewrite'] ? '%language%' : 'language/%language%',
			version_compare($GLOBALS['wp_version'], '3.4' , '<') ? false : array('with_front' => false));
	}

	/*
	 * prepares rewrite rules filters
	 *
	 * @since 0.8.1
	 *
	 * @param array $pre not used
	 * @return unmodified $pre
	 */
	public function prepare_rewrite_rules($pre) {
		//clean filters which could have been set in a previous call (may occur when changing Polylang settings)
		foreach ($this->rewrite_rules as $type)
			remove_filter($type . '_rewrite_rules', array(&$this, 'rewrite_rules'));

		// don't modify the rules if there is no languages created yet
		if ($this->model->get_languages_list()) {
			// make sure we have the right post types and taxonomies
			$types = array_values(array_merge($this->model->get_translated_post_types(), $this->model->get_translated_taxonomies()));
			$types = array_merge($this->always_rewrite, $types);
			$this->rewrite_rules = apply_filters('pll_rewrite_rules', $types); // allow plugins to add rewrite rules to the language filter

			foreach ($this->rewrite_rules as $type)
				add_filter($type . '_rewrite_rules', array(&$this, 'rewrite_rules'));

			add_filter('rewrite_rules_array', array(&$this, 'rewrite_rules')); // needed for post type archives
		}
		return $pre;
	}

	/*
	 * the rewrite rules !
	 * always make sure the default language is at the end in case the language information is hidden for default language
	 * thanks to brbrbr http://wordpress.org/support/topic/plugin-polylang-rewrite-rules-not-correct
	 *
	 * @since 0.8.1
	 *
	 * @param array $rules rewrite rules
	 * @return array modified rewrite rules
	 */
	public function rewrite_rules($rules) {
		$filter = str_replace('_rewrite_rules', '', current_filter());

		// suppress the rules created by WordPress for our taxonomy
		if ($filter == 'language')
			return array();

		global $wp_rewrite;
		$newrules = array();

		$languages = $this->model->get_languages_list(array('fields' => 'slug'));
		if ($this->options['hide_default'])
			$languages = array_diff($languages, array($this->options['default_lang']));

		if (!empty($languages))
			$slug = $wp_rewrite->root . ($this->options['rewrite'] ? '' : 'language/') . '('.implode('|', $languages).')/';

		// for custom post type archives
		$cpts = array_intersect($this->model->post_types, get_post_types(array('_builtin' => false)));
		$cpts = $cpts ? '#post_type=('.implode('|', $cpts).')#' : '';

		foreach ($rules as $key => $rule) {
			// we don't need the lang parameter for post types and taxonomies
			// moreover adding it would create issues for pages and taxonomies
			if ($this->options['force_lang'] && in_array($filter, array_merge($this->model->post_types, $this->model->taxonomies))) {
				if (isset($slug))
					$newrules[$slug.str_replace($wp_rewrite->root, '', $key)] = str_replace(
						array('[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '[1]'),
						array('[9]', '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]'),
						$rule
					); // hopefully it is sufficient!

				if ($this->options['hide_default']) {
					$newrules[$key] = $rules[$key];
					// unset only if we hide the code for the default language as check_language_code_in_url will do its job in other cases
					unset($rules[$key]);
				}
			}

			// rewrite rules filtered by language
			elseif (in_array($filter, $this->always_rewrite) || ($cpts && preg_match($cpts, $rule) && !strpos($rule, 'name=')) || ($filter != 'rewrite_rules_array' && $this->options['force_lang'])) {
				if (isset($slug))
					$newrules[$slug.str_replace($wp_rewrite->root, '', $key)] = str_replace(
						array('[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '[1]', '?'),
						array('[9]', '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '?lang=$matches[1]&'),
						$rule
					); // should be enough!

				if ($this->options['hide_default'])
					$newrules[$key] = str_replace('?', '?lang='.$this->options['default_lang'].'&', $rule);

				unset($rules[$key]); // now useless
			}
		}

		// the home rewrite rule
		if ($filter == 'root' && isset($slug))
			$newrules[$slug.'?$'] = $wp_rewrite->index.'?lang=$matches[1]';

		return $newrules + $rules;
	}
}

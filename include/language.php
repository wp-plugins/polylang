<?php

/*
 * a language object is made of two terms in 'language' and 'term_language' taxonomies
 * manipulating only one object per language instead of two terms should make things easier
 *
 * properties:
 * term_id             => id of term in 'language' taxonomy
 * name                => language name. Ex: English
 * slug                => language code used in url. Ex: en
 * term_group          => order of the language when displayed in a list of languages
 * term_taxonomy_id    => term taxonomy id in 'language' taxonomy
 * taxonomy            => 'language'
 * description         => language locale for backward compatibility
 * parent              => 0 / not used
 * count               => number of posts and pages in that language
 * tl_term_id          => id of the term in 'term_language' taxonomy
 * tl_term_taxonomy_id => term taxonomy id in 'term_language' taxonomy
 * tl_count            => number of terms in that language (not used by Polylang)
 * locale              => language locale. Ex: en_US
 * is_rtl              => 1 if the language is rtl
 * flag_url            => url of the flag
 * flag                => html img of the flag
 * custom_flag_url     => url of the custom flag if exists, internal use only, moves to flag_url on frontend
 * custom_flag         => html img of the custom flag if exists, internal use only, moves to flag on frontend
 * home_url            => home url in this language
 * search_url          => home url to use in search forms
 * host                => host of this language
 * mo_id               => id of the post storing strings translations
 *
 * @since 1.2
 */
class PLL_Language {
	public $term_id, $name, $slug, $term_group, $term_taxonomy_id, $taxonomy, $description, $parent, $count;
	public $tl_term_id, $tl_term_taxonomy_id, $tl_count;
	public $locale, $is_rtl;
	public $flag_url, $flag;
	public $home_url, $search_url;
	public $host, $mo_id;

	/*
	 * constructor: builds a language object given its two corresponding terms in language and term_language taxonomies
	 *
	 * @since 1.2
	 *
	 * @param object|array $language 'language' term or language object properties stored as an array
	 * @param object $term_language corresponding 'term_language' term
	 */
	public function __construct($language, $term_language = null) {
		// build the object from all properties stored as an array
		if (empty($term_language)) {
			foreach ($language as $prop => $value)
				$this->$prop = $value;
		}
		
		// build the object from taxonomies
		else {
			foreach ($language as $prop => $value)
				$this->$prop = in_array($prop, array('term_id', 'term_taxonomy_id', 'count')) ? (int) $language->$prop : $language->$prop;

			// although it would be convenient here, don't assume the term is shared between taxonomies as it may not be the case in future
			// http://make.wordpress.org/core/2013/07/28/potential-roadmap-for-taxonomy-meta-and-post-relationships/
			$this->tl_term_id = (int) $term_language->term_id;
			$this->tl_term_taxonomy_id = (int) $term_language->term_taxonomy_id;
			$this->tl_count = (int) $term_language->count;

			$description = maybe_unserialize($language->description);
			$this->locale = $description['locale'];
			$this->is_rtl = $description['rtl'];

			$this->description = &$this->locale; // backward compatibility with Polylang < 1.2

			$this->mo_id = PLL_MO::get_id($this);
			$this->set_flag();
		}
	}

	/*
	 * sets flag_url and flag properties
	 *
	 * @since 1.2
	 */
	public function set_flag() {
		$flags['']['url'] = '';

		// Polylang builtin flags
		if (file_exists(POLYLANG_DIR.($file = '/flags/'.$this->locale.'.png'))) {
			$flags['']['url'] = $flags['']['src'] = POLYLANG_URL.$file;

			// if base64 encoded flags are preferred
			if (!defined('PLL_ENCODED_FLAGS') || PLL_ENCODED_FLAGS)
				$flags['']['src'] = 'data:image/png;base64,' . base64_encode(file_get_contents(POLYLANG_DIR.$file));
		}

		// custom flags ?
		if (file_exists(PLL_LOCAL_DIR.($file = '/'.$this->locale.'.png')) || file_exists(PLL_LOCAL_DIR.($file = '/'.$this->locale.'.jpg')) ) {
			$flags['custom_']['url'] = $flags['custom_']['src'] = PLL_LOCAL_URL.$file;
		}

		foreach($flags as $key => $flag) {
			$this->{$key . 'flag_url'} = empty($flag['url']) ? '' : esc_url($flag['url']);

			$this->{$key . 'flag'} = apply_filters('pll_get_flag', empty($flag['src']) ? '' :
				sprintf(
					'<img src="%s" title="%s" alt="%s" />',
					$flag['src'],
					esc_attr(apply_filters('pll_flag_title', $this->name, $this->slug, $this->locale)),
					esc_attr($this->name)
				),
				$this->slug
			);
		}
	}

	/*
	 * replace flag by custom flag
	 *
	 * @since 1.7
	 */
	public function set_custom_flag() {
		// overwrite with custom flags on frontend only
		if (!empty($this->custom_flag)) {
			$this->flag = $this->custom_flag;
			$this->flag_url = $this->custom_flag_url;
			unset($this->custom_flag, $this->custom_flag_url); // hide this
		}
	}

	/*
	 * updates post and term count
	 *
	 * @since 1.2
	 */
	public function update_count() {
		wp_update_term_count($this->term_taxonomy_id, 'language'); // posts count
		wp_update_term_count($this->tl_term_taxonomy_id, 'term_language'); // terms count
	}

	/*
	 * set home_url and search_url properties
	 *
	 * @since 1.3
	 */
	public function set_home_url() {
		global $polylang;

		// home url for search form (can't use the page url if a static page is used as front page)
		$this->search_url = $polylang->links_model->home_url($this);

		// add a trailing slash as done by WP on homepage (otherwise could break the search form when the permalink structure does not include one)
		// only for pretty permalinks
		if (get_option('permalink_structure'))
			$this->search_url = trailingslashit($this->search_url);

		$options = get_option('polylang');

		// a static page is used as front page
		if (!($options['hide_default'] && $this->slug == $options['default_lang']) && !$options['redirect_lang'] && 'page' == get_option('show_on_front') && ($page_on_front = get_option('page_on_front')) && $id = pll_get_post($page_on_front, $this))
			$this->home_url = _get_page_link($id); // /!\ don't use get_page_link to avoid infinite loop

		else
			$this->home_url = $this->search_url;
	}

	/*
	 * set home_url scheme
	 * this can't be cached accross pages
	 *
	 * @since 1.6.4
	 */
	public function set_home_url_scheme() {
		if (is_ssl()) {
			$this->home_url = str_replace('http://', 'https://', $this->home_url);
			$this->search_url = str_replace('http://', 'https://', $this->search_url);
		}

		else {
			$this->home_url = str_replace('https://', 'http://', $this->home_url);
			$this->search_url = str_replace('https://', 'http://', $this->search_url);
		}
	}
}

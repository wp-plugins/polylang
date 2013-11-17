<?php

/*
 * links model for use when using one domain per language
 * for example mysite.com/sth and mysite.fr/qqch
 * implements the "links_model interface"
 *
 * @since 1.2
 */
class PLL_Links_Domain {
	public $model, $options;
	protected $home;

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

		// returns the correct language link
		add_filter('term_link', array(&$this, 'term_link'), 10, 3);
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
		if (!empty($lang))
			$url = $this->options['default_lang'] == $lang->slug ? $url : str_replace($this->home, $this->options['domains'][$lang->slug], $url);
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
	public function remove_language_from_link($url) {
		if (!empty($this->options['domains']))
			$url = preg_replace('#^('.implode('|', $this->options['domains']).')#', $this->home , $url);
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
	public function remove_paged_from_link($url) {
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
		foreach ($this->options['domains'] as $key => $domain)
			if ($domain == (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'])
				return $key;
	}

	/*
	 * returns the correct language link
	 *
	 * @since 1.2
	 *
	 * @param string $link term link
	 * @param object $term term
	 * @param string $tax taxonomy name
	 * @return string language home link or unmodified term link
	 */
	function term_link($link, $term, $tax) {
		return 'language' == $tax ? $this->options['domains'][$term->slug] : $link;
	}
}

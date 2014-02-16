<?php

/*
 * links model for use when the language code is added in url as a subdomain
 * for example en.mysite.com/something
 * implements the "links_model interface"
 *
 * @since 1.2
 */
class PLL_Links_Subdomain {
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
			$url = $this->options['default_lang'] == $lang->slug && $this->options['hide_default'] ? $url : str_replace('://', '://'.$lang->slug.'.', $url);
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
		foreach ($this->model->get_languages_list() as $language)
			if (!$this->options['hide_default'] || $this->options['default_lang'] != $language->slug)
				$languages[] = $language->slug;

		if (!empty($languages))
			$url = preg_replace('#:\/\/'  . '('.implode('|', $languages).')\.#', '://' , $url);

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
		$pattern = '#('.implode('|', $this->model->get_languages_list(array('fields' => 'slug'))).')\.#';
		return preg_match($pattern, trailingslashit($_SERVER['HTTP_HOST']), $matches) ? $matches[1] : ''; // $matches[1] is the slug of the requested language
	}

	/*
	 * returns the home url
	 * links_model interface
	 *
	 * @since 1.3.1
	 *
	 * @param object $lang PLL_Language object
	 * @return string
	 */
	public function home_url($lang) {
		return $this->add_language_to_link($this->home, $lang);
	}
}

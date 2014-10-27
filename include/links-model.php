<?php

/*
 * links model abstract class
 *
 * @since 1.5
 */
abstract class PLL_Links_Model {
	public $model, $options;
	public $home; // used to avoid several calls to get_option('home')
	public $using_permalinks;

	/*
	 * constructor
	 *
	 * @since 1.5
	 *
	 * @param object $model PLL_Model instance
	 */
	public function __construct(&$model) {
		$this->model = &$model;
		$this->options = &$model->options;

		$this->home = get_option('home');
	}

	/*
	 * changes the language code in url
	 *
	 * @since 1.5
	 *
	 * @param string $url url to modify
	 * @param object $lang language
	 * @return string modified url
	 */
	public function switch_language_in_link($url, $lang) {
		$url = $this->remove_language_from_link($url);
		return $this->add_language_to_link($url, $lang);
	}

	/*
	 * get hosts managed on the website
	 *
	 * @since 1.5
	 *
	 * @return array list of hosts
	 */
	public function get_hosts() {
		return array(parse_url($this->home, PHP_URL_HOST));
	}
}

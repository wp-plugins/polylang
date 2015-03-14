<?php

/*
 * Choose the language when the language code is added to all urls
 * The language is set in plugins_loaded with priority 1 as done by WPML
 * Some actions have to be delayed to wait for $wp_rewrite availibility
 *
 * @since 1.2
 */
class PLL_Choose_Lang_Url extends PLL_Choose_lang {
	protected $index = 'index.php'; // need this before $wp_rewrite is created, also harcoded in wp-includes/rewrite.php

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $polylang
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);

		if (!did_action('pll_language_defined'))
			$this->set_language_from_url();
	}

	/*
	 * finds the language according to information found in the url
	 *
	 * @since 1.2
	 */
	public function set_language_from_url() {
		$host = str_replace('www.', '', parse_url($this->links_model->home, PHP_URL_HOST));
		$home_path = parse_url($this->links_model->home, PHP_URL_PATH);

		$requested_host = str_replace('www.', '', $_SERVER['HTTP_HOST']);
		$requested_uri = rtrim(str_replace($this->index, '', $_SERVER['REQUEST_URI']), '/'); // some PHP setups turn requests for / into /index.php in REQUEST_URI

		// home is resquested
		if ($requested_host == $host && $requested_uri == $home_path && empty($_SERVER['QUERY_STRING'])) {
			$this->home_language();
			add_action('setup_theme', array(&$this, 'home_requested'));
		}

		// take care to post & page preview http://wordpress.org/support/topic/static-frontpage-url-parameter-url-language-information
		elseif (isset($_GET['preview']) && ( (isset($_GET['p']) && $id = $_GET['p']) || (isset($_GET['page_id']) && $id = $_GET['page_id']) ))
			$curlang = ($lg = $this->model->get_post_language($id)) ? $lg : $this->model->get_language($this->options['default_lang']);

		// take care to (unattached) attachments
		elseif (isset($_GET['attachment_id']) && $id = $_GET['attachment_id'])
				$curlang = ($lg = $this->model->get_post_language($id)) ? $lg : $this->get_preferred_language();

		elseif ($slug = $this->links_model->get_language_from_url())
			$curlang = $this->model->get_language($slug);

		elseif ($this->options['hide_default'])
			$curlang = $this->model->get_language($this->options['default_lang']);

		// if no language found, check_language_code_in_url will attempt to find one and redirect to the correct url
		// otherwise 404 will be fired in the preferred language
		$this->set_language(empty($curlang) ? $this->get_preferred_language() : $curlang);
	}
}

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
		// home is resquested
		// some PHP setups turn requests for / into /index.php in REQUEST_URI
		// thanks to Gonçalo Peres for pointing out the issue with queries unknown to WP
		// http://wordpress.org/support/topic/plugin-polylang-language-homepage-redirection-problem-and-solution-but-incomplete?replies=4#post-2729566
		if (str_replace('www.', '', home_url('/')) == trailingslashit((is_ssl() ? 'https://' : 'http://').str_replace('www.', '', $_SERVER['HTTP_HOST']).str_replace(array($this->index, '?'.$_SERVER['QUERY_STRING']), array('', ''), $_SERVER['REQUEST_URI']))) {
			// take care to post & page preview http://wordpress.org/support/topic/static-frontpage-url-parameter-url-language-information
			if (isset($_GET['preview']) && ( (isset($_GET['p']) && $id = $_GET['p']) || (isset($_GET['page_id']) && $id = $_GET['page_id']) ))
				$curlang = ($lg = $this->model->get_post_language($id)) ? $lg : $this->model->get_language($this->options['default_lang']);

			// take care to (unattached) attachments
			elseif (isset($_GET['attachment_id']) && $id = $_GET['attachment_id'])
				$curlang = ($lg = $this->model->get_post_language($id)) ? $lg : $this->get_preferred_language();

			else {
				$this->home_language();
				add_action('setup_theme', array(&$this, 'home_requested'));
			}
		}

		elseif ($slug = $this->links_model->get_language_from_url())
			$curlang = $this->model->get_language($slug);

		elseif ($this->options['hide_default'])
			$curlang = $this->model->get_language($this->options['default_lang']);

		// if no language found, check_language_code_in_url will attempt to find one and redirect to the correct url
		// otherwise 404 will be fired in the preferred language
		$this->set_language(empty($curlang) ? $this->get_preferred_language() : $curlang);
	}
}

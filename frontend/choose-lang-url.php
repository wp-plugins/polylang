<?php

/*
 * Choose the language when the language code is added to all urls
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
	 * @param object $links instance of PLL_Frontend_Links
	 */
	public function __construct(&$links) {
		parent::__construct($links);

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

		add_action('wp', array(&$this, 'check_language_code_in_url')); // before Wordpress redirect_canonical

		// if no language found, check_language_code_in_url will attempt to find one and redirect to the correct url
		// otherwise 404 will be fired in the preferred language
		$this->set_language(empty($curlang) ? $this->get_preferred_language() : $curlang);
	}

	/*
	 * if the language code is not in agreement with the language od the content
	 * redirects incoming links to the proper URL
	 *
	 * @since 0.9.6
	 */
	public function check_language_code_in_url() {
		if (is_single() || is_page()) {
			global $post;
			if (isset($post->ID) && in_array($post->post_type, $this->model->post_types))
				$language = $this->model->get_post_language((int)$post->ID);
		}
		elseif (is_category() || is_tag() || is_tax()) {
			$obj = $GLOBALS['wp_query']->get_queried_object();
			if (in_array($obj->taxonomy, $this->model->taxonomies))
				$language = $this->model->get_term_language((int)$obj->term_id);
		}

		// the language is not correctly set so let's redirect to the correct url for this object
		if (isset($language) && (empty($this->curlang) || $language->slug != $this->curlang->slug)) {
			$root = $this->options['rewrite'] ? '/' : '/language/';
			foreach ($this->model->get_languages_list() as $lang)
				$languages[] = $root . $lang->slug;

			$requested_url  = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace($languages, '', $_SERVER['REQUEST_URI']);
			$redirect_url = $this->links_model->add_language_to_link($requested_url, $language);
			wp_redirect($redirect_url, 301);
			exit;
		}
	}
}

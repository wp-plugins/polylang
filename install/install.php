<?php

/*
 * Polylang activation / de-activation class
 *
 * @since 1.7
 */
class PLL_Install extends PLL_Install_Base {

	/*
	 * plugin activation for multisite
	 *
	 * @since 0.1
	 */
	public function activate($networkwide) {
		global $wp_version;

		Polylang::define_constants();

		load_plugin_textdomain('polylang', false, basename(POLYLANG_DIR).'/languages'); // plugin i18n

		if (version_compare($wp_version, PLL_MIN_WP_VERSION , '<'))
			die (sprintf('<p style = "font-family: sans-serif; font-size: 12px; color: #333; margin: -5px">%s</p>',
				sprintf(__('You are using WordPress %s. Polylang requires at least WordPress %s.', 'polylang'),
					esc_html($wp_version),
					PLL_MIN_WP_VERSION
				)
			));

		$this->do_for_all_blogs('activate', $networkwide);
	}

	/*
	 * plugin activation
	 *
	 * @since 0.5
	 */
	protected function _activate() {
		global $polylang;

		if ($options = get_option('polylang')) {
			// plugin upgrade
			if (version_compare($options['version'], POLYLANG_VERSION, '<')) {
				$upgrade = new PLL_Upgrade($options);
				$upgrade->upgrade_at_activation();
			}
		}
		// defines default values for options in case this is the first installation
		else {
			$options = array(
				'browser'       => 1, // default language for the front page is set by browser preference
				'rewrite'       => 1, // remove /language/ in permalinks (was the opposite before 0.7.2)
				'hide_default'  => 0, // do not remove URL language information for default language
				'force_lang'    => 1, // add URL language information (was 0 before 1.7)
				'redirect_lang' => 0, // do not redirect the language page to the homepage
				'media_support' => 1, // support languages and translation for media by default
				'sync'          => array(), // synchronisation is disabled by default (was the opposite before 1.2)
				'post_types'    => array_values(get_post_types(array('_builtin' => false, 'show_ui => true'))),
				'taxonomies'    => array_values(get_taxonomies(array('_builtin' => false, 'show_ui => true'))),
				'domains'       => array(),
				'version'       => POLYLANG_VERSION,
			);

			update_option('polylang', $options);
		}

		// always provide a global $polylang object and add our rewrite rules if needed
		$polylang = new StdClass();
		$polylang->options = &$options;
		$polylang->model = new PLL_Admin_Model($options);
		$polylang->links_model = $polylang->model->get_links_model();
		do_action('pll_init');

		// don't use flush_rewrite_rules at network activation. See #32471
		// thanks to RavanH for the trick. See https://polylang.wordpress.com/2015/06/10/polylang-1-7-6-and-multisite/
		delete_option('rewrite_rules');
	}

	/*
	 * plugin deactivation
	 *
	 * @since 0.5
	 */
	protected function _deactivate() {
		delete_option('rewrite_rules'); // don't use flush_rewrite_rules at network activation. See #32471
	}
}

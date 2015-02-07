<?php

/*
 * setup miscellaneous admin filters as well as filters common to admin and frontend
 *
 * @since 1.2
 */
class PLL_Admin_Filters extends PLL_Filters {

	/*
	 * constructor: setups filters and actions
	 *
	 * @since 1.2
	 *
	 * @param object $polylang
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);

		// widgets languages filter
		add_action('in_widget_form', array(&$this, 'in_widget_form'), 10, 3);
		add_filter('widget_update_callback', array(&$this, 'widget_update_callback'), 10, 4);

		// language management for users
		add_action('personal_options_update', array(&$this, 'personal_options_update'));
		add_action('edit_user_profile_update', array(&$this, 'personal_options_update'));
		add_action('personal_options', array(&$this, 'personal_options'));

		// ugrades languages files after a core upgrade (timing is important)
		// backward compatibility WP < 4.0 *AND* Polylang < 1.6
		add_action( '_core_updated_successfully', array(&$this, 'upgrade_languages'), 1); // since WP 3.3

		// upgrades plugins and themes translations files
		add_filter('themes_update_check_locales', array(&$this, 'update_check_locales'));
		add_filter('plugins_update_check_locales', array(&$this, 'update_check_locales'));

		// checks if chosen page on front is translated
		add_filter('pre_update_option_page_on_front', array(&$this, 'update_page_on_front'), 10, 2);
	}

	/*
	 * modifies the widgets forms to add our language dropdwown list
	 *
	 * @since 0.3
	 *
	 * @param object $widget
	 */
	public function in_widget_form($widget, $return, $instance) {
		$dropdown = new PLL_Walker_Dropdown();
		printf('<p><label for="%1$s">%2$s %3$s</label></p>',
			esc_attr( $widget->id.'_lang_choice'),
			__('The widget is displayed for:', 'polylang'),
			$dropdown->walk(
				array_merge(
					array((object) array('slug' => 0, 'name' => __('All languages', 'polylang'))),
					$this->model->get_languages_list()
				),
				array(
					'name'        => $widget->id.'_lang_choice',
					'class'       => 'tags-input',
					'selected'    => empty($instance['pll_lang']) ? '' : $instance['pll_lang']
				)
			)
		);
	}

	/*
	 * called when widget options are saved
	 * saves the language associated to the widget
	 *
	 * @since 0.3
	 *
	 * @param array $instance widget options
	 * @param array $new_instance not used
	 * @param array $old_instance not used
	 * @param object $widget WP_Widget object
	 * @return array widget options
	 */
	public function widget_update_callback($instance, $new_instance, $old_instance, $widget) {
		if (!empty($_POST[$widget->id.'_lang_choice']))
			$instance['pll_lang'] = $_POST[$widget->id.'_lang_choice'];
		else
			unset($instance['pll_lang']);

		return $instance;
	}

	/*
	 * updates language user preference set in user profile
	 *
	 * @since 0.4
	 *
	 * @param int $user_id
	 */
	public function personal_options_update($user_id) {
		// admin language
		$user_lang = in_array($_POST['user_lang'], $this->model->get_languages_list(array('fields' => 'locale'))) ? $_POST['user_lang'] : 0;
		update_user_meta($user_id, 'user_lang', $_POST['user_lang']);

		// biography translations
		foreach ($this->model->get_languages_list() as $lang) {
			$meta = $lang->slug == $this->options['default_lang'] ? 'description' : 'description_'.$lang->slug;
			$description = empty($_POST['description_'.$lang->slug]) ? '' : trim($_POST['description_'.$lang->slug]);
			$description = apply_filters('pre_user_description', $description); // applies WP default filter wp_filter_kses
			update_user_meta($user_id, $meta, $description);
		}
	}

	/*
	 * form for language user preference in user profile
	 *
	 * @since 0.4
	 *
	 * @param object $profileuser
	 */
	public function personal_options($profileuser) {
		$dropdown = new PLL_Walker_Dropdown();
		printf('
			<tr>
				<th><label for="user_lang">%s</label></th>
				<td>%s</td>
			</tr>',
			__('Admin language', 'polylang'),
			$dropdown->walk(
				array_merge(
					array((object) array('locale' => 0, 'name' => __('Wordpress default', 'polylang'))),
					$this->model->get_languages_list()
				),
				array(
					'name'        => 'user_lang',
					'value'       => 'locale',
					'selected'    => get_user_meta($profileuser->ID, 'user_lang', true),
				)
			)
		);

		// hidden informations to modify the biography form with js
		foreach ($this->model->get_languages_list() as $lang) {
			$meta = $lang->slug == $this->options['default_lang'] ? 'description' : 'description_'.$lang->slug;
			$description = apply_filters('user_description', get_user_meta($profileuser->ID, $meta, true)); // applies WP default filter wp_kses_data

			printf('<input type="hidden" class="biography" name="%s-%s" value="%s" />',
				esc_attr($lang->slug),
				esc_attr($lang->name),
				esc_attr($description)
			);
		}
	}

	/*
	 * ugprades languages files after a core upgrade
	 *
	 * @since 0.6
	 *
	 * @param string $version new WP version
	 */
	public function upgrade_languages($version) {
		// backward compatibility WP < 4.0
		if (version_compare($version, '4.0', '<')) {
			apply_filters('update_feedback', __('Upgrading language files&#8230;', 'polylang'));
			foreach ($this->model->get_languages_list() as $language)
				if (!empty($_POST['locale']) && $language->locale != $_POST['locale']) // do not (re)update the language files of a localized WordPress
					PLL_Admin::download_mo($language->locale, $version);
		}

		// backward compatibility WP < 4.0 *AND* Polylang < 1.6
		// $GLOBALS['wp_version'] is the old WP version
		elseif (version_compare($GLOBALS['wp_version'], '4.0', '<')) {
			apply_filters('update_feedback', __('Upgrading language files&#8230;', 'polylang'));
			PLL_Upgrade::download_language_packs();
		}
	}

	/*
	 * allows to update translations files for plugins and themes
	 *
	 * @since 1.6
	 *
	 * @param array $locale not used
	 * @return array list of locales to update
	 */
	function update_check_locales($locales) {
		return $this->model->get_languages_list(array('fields' => 'locale'));
	}

	/*
	 * prevents choosing an untranslated static front page
	 * displays an error message
	 *
	 * @since 1.6
	 *
	 * @param int $page_id new page on front page id
	 * @param int $old_id old page on front page_id
	 * @return int
	 */
	public function update_page_on_front($page_id, $old_id) {
		if ($page_id) {
			$translations = count($this->model->get_translations('post', $page_id));
			$languages = count($this->model->get_languages_list());

			if ($languages > 1 && $translations != $languages) {
				$page_id = $old_id;
				add_settings_error('reading', 'pll_page_on_front_error', __('The chosen static front page must be translated in all languages.', 'polylang'));
			}
		}

		return $page_id;
	}
}

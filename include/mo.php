<?php

/*
 * manages strings translations storage
 *
 * @since 1.2
 */
class PLL_MO extends MO {

	/*
	 * registers the polylang_mo custom post type
	 *
	 * @since 1.2
	 */
	public function __construct() {
		register_post_type('polylang_mo', array('rewrite' => false, 'query_var' => false, '_pll' => true));
	}

	/*
	 * writes a PLL_MO object into a custom post
	 *
	 * @since 1.2
	 *
	 * @param object $lang the language in which we want to export strings
	 */
	public function export_to_db($lang) {
		$this->add_entry($this->make_entry('', '')); // empty string translation, just in case

		// would be convenient to store the whole object but it would take a huge space in DB
		// so let's keep only the strings in an array
		$strings = array();
		foreach ($this->entries as $entry)
			$strings[] = array($entry->singular, $this->translate($entry->singular));

		$lang_id = is_object($lang) ? $lang->term_id : $lang;
		$post = get_page_by_title('polylang_mo_' . $lang_id, ARRAY_A, 'polylang_mo'); // wp_insert_post wants an array
		$post['post_title'] = 'polylang_mo_' . $lang_id;
		// json_encode would take less space but is slower to decode
		// wp_insert_post expects slashed data
		$post['post_content'] = addslashes(serialize($strings));
		$post['post_status'] = 'publish';
		$post['post_type'] = 'polylang_mo';
		wp_insert_post($post);
	}

	/*
	 * reads a PLL_MO object from a custom post
	 *
	 * @since 1.2
	 *
	 * @param object $lang the language in which we want to get strings
	 */
	public function import_from_db($lang) {
		$lang_id = is_object($lang) ? $lang->term_id : $lang;
		$post = get_page_by_title('polylang_mo_' . $lang_id, OBJECT, 'polylang_mo');
		if (!empty($post)) {
			$strings = unserialize($post->post_content);
			if (is_array($strings)) {
				foreach ($strings as $msg)
					$this->add_entry($this->make_entry($msg[0], $msg[1]));
			}
		}
	}
}

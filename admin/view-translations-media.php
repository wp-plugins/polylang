<?php

// backward compatibility WP < 3.5
if (version_compare($GLOBALS['wp_version'], '3.5', '<')) {?>
<table class="widefat media-translations">
	<thead><tr><?php
		echo '<th class="tr-language-column">'.__('Language', 'polylang').'</th>';
		echo '<th class="tr-edit-column">'.__('Translation', 'polylang').'</th>';?>
	</tr></thead>

	<tbody><?php
		foreach ($this->model->get_languages_list() as $language) {
			if ($language->term_id == $lang->term_id)
				continue;?>

			<tr><td class="tr-language-column"><?php echo esc_html($language->name);?></td><?php
			// the translation exists
			if (($translation_id = $this->model->get_translation('post', $post_id, $language)) && $translation_id != $post_id) {
				printf(
					'<td class="tr-edit-column"><input type="hidden" name="media_tr_lang[%s]" value="%d" /><a href="%s">%s</a></td>',
 					esc_attr($language->slug),
					esc_attr($translation_id),
					esc_url(admin_url(sprintf('media.php?attachment_id=%d&action=edit', $translation_id))),
					__('Edit','polylang')
				);
			}

			// no translation
			else
				printf(
					'<td class="tr-edit-column"><a href="%1$s">%2$s</a></td>',
					esc_url(admin_url(sprintf('admin.php?action=translate_media&from_media=%d&new_lang=%s', $post_id, $language->slug))),
					__('Add new','polylang')
				);?>
			</tr><?php
		} // foreach ?>
	</tbody>
</table><?php
}

else { // WP 3.5+ ?>
<p><em><?php _e('Translations', 'polylang');?></em></p>
<table>
	<thead><tr>
		<th><?php _e('Language', 'polylang');?></th>
		<th><?php  _e('Translation', 'polylang');?></th>
	</tr></thead>

	<tbody><?php
		foreach ($this->model->get_languages_list() as $language) {
			if ($language->term_id == $lang->term_id)
				continue;?>

			<tr><td><?php echo esc_html($language->name);?></td><?php
			// the translation exists
			if (($translation_id = $this->model->get_translation('post', $post_id, $language)) && $translation_id != $post_id) {
				printf(
					'<td><input type="hidden" name="media_tr_lang[%s]" value="%d" /><a href="%s">%s</a></td>',
 					esc_attr($language->slug),
					esc_attr($translation_id),
					esc_url(admin_url(sprintf('post.php?post=%d&action=edit', $translation_id))),
					__('Edit','polylang')
				);
			}

			// no translation
			else
				printf('<td><a href="%1$s">%2$s</a></td>',
					esc_url(admin_url(sprintf('admin.php?action=translate_media&from_media=%d&new_lang=%s', $post_id, $language->slug))),
					__('Add new','polylang')
				);?>
			</tr><?php
		} // foreach ?>
	</tbody>
</table><?php
}

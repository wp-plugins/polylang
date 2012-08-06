<table class="widefat media-translations">
	<thead><tr><?php
		echo '<th class="tr-language-column">'.__('Language', 'polylang').'</th>';
		echo '<th class="tr-edit-column">'.__('Translation', 'polylang').'</th>';?>
	</tr></thead>
	<tbody><?php
		foreach ($this->get_languages_list() as $language) {
			if ($language->term_id == $lang->term_id)
				continue;?>

			<tr><td class="tr-language-column"><?php echo esc_html($language->name);?></td><?php
			// the translation exists
			if (($translation_id = $this->get_translation('post', $post_id, $language)) && $translation_id != $post_id) {
				printf('<td class="tr-edit-column"><input type="hidden" name="media_tr_lang[%s]" value="%d" /><a href="%s">%s</a></td>',
 					esc_attr($language->slug),
					esc_attr($translation_id),
					esc_url(admin_url(sprintf('media.php?attachment_id=%d&action=edit', $translation_id))),
					__('Edit','polylang')
				);
			}

			// no translation
			else
				printf('<td class="tr-edit-column"><a href="%1$s">%2$s</a></td>',
					esc_url(admin_url('admin.php?action=translate_media&from_media=' . $post_id . '&new_lang=' . $language->slug)),
					__('Add new','polylang')
				);?>
			</tr><?php
		} // foreach ?>
	</tbody>
</table>

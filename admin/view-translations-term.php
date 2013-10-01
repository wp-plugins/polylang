<?php
// displays the translations fields

if (isset($term_id)) {
	// edit term form?>
	<th scope="row"><?php _e('Translations', 'polylang');?></th>
	<td><?php
}
else {
	// add term form?>
	<label><?php _e('Translations', 'polylang');?></label><?php
}?>
<table class="widefat term-translations">
	<thead><tr><?php
		echo '<th class="tr-language-column">'.__('Language', 'polylang').'</th>';
		echo '<th>'.__('Translation', 'polylang').'</th>';
		if (isset($term_id))
			echo '<th class="tr-edit-column">'.__('Edit', 'polylang').'</th>';?>
	</tr></thead>
	<tbody>
		<?php foreach ($this->model->get_languages_list() as $language) {
			if ($language->term_id == $lang->term_id)
				continue;

			// look for any existing translation in this language
			$translation = 0;
			if (isset($term_id) && $translation_id = $this->model->get_translation('term', $term_id, $language))
				$translation = get_term($translation_id, $taxonomy);
			if (isset($_GET['from_tag']) && $translation_id = $this->model->get_term($_GET['from_tag'], $language))
				$translation = get_term($translation_id, $taxonomy);?>

			<tr><td class="tr-language-column"><?php echo esc_html($language->name);?></td><?php

			// no translation exists in this language
			if (!$translation) {
				// look for untranslated terms in this language
				$translations = $this->get_terms_not_translated($taxonomy, $language, $lang);
				if (!empty($translations)) { ?>
					<td>
						<?php printf('<select name="term_tr_lang[%1$s]" id="tr_lang_%1$s">', esc_attr($language->slug)); ?>
							<option value="0"></option><?php
							foreach ($translations as $translation)
								printf('<option value="%s">%s</option>', esc_attr($translation->term_id), esc_html($translation->name));?>
						</select>
					</td><?php
				}
				else
					echo '<td>'.__('No untranslated term', 'polylang').'</td>';

				// do not display the add new link in add term form ($term_id not set !!!)
				if (isset($term_id))
					printf(
						'<td class="tr-edit-column"><a href="%1$s">%2$s</a></td>',
						esc_url(admin_url(sprintf(
							'edit-tags.php?taxonomy=%1$s&from_tag=%2$d&new_lang=%3$s',
							$taxonomy,
							$term_id,
							$language->slug
						))),
						__('Add new','polylang')
					);
			}

			// a translation exists
			else {
				printf(
					'<td><input type="hidden" name="term_tr_lang[%s]" value="%d" />%s</td>',
					esc_attr($language->slug),
					esc_attr($translation->term_id),
					esc_html($translation->name)
				);
				if (isset($term_id))
					printf(
						'<td class="tr-edit-column"><a href="%1$s">%2$s</a></td>',
						esc_url(admin_url(sprintf(
							'edit-tags.php?action=edit&amp;taxonomy=%1$s&tag_ID=%2$d',
							$taxonomy,
							$translation->term_id
						))),
						__('Edit','polylang')
					);
			} ?>
			</tr><?php
		} // foreach ?>
	</tbody>
</table><?php

if (isset($term_id)) {
	// edit term form?>
	</td><?php
}

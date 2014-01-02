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
	<?php foreach ($this->model->get_languages_list() as $language) {
		if ($language->term_id == $lang->term_id)
			continue;

		$translations = $this->get_terms_not_translated($taxonomy, $language, $lang);

		// look for any existing translation in this language
		$translation = 0;
		if (isset($term_id) && $translation_id = $this->model->get_translation('term', $term_id, $language))
			$translation = get_term($translation_id, $taxonomy);
		if (isset($_GET['from_tag']) && $translation_id = $this->model->get_term($_GET['from_tag'], $language))
			$translation = get_term($translation_id, $taxonomy);

		$link = '';

		if ($translation) {
			foreach ($translations as $key => $term) {
				if ($term->term_id == $translation->term_id)
					unset($translations[$key]);
			}

			array_unshift($translations, $translation);

			$link = $this->edit_translation_link($translation->term_id, $taxonomy, $post_type);
		}

		elseif (isset($term_id)) { // do not display the add new link in add term form ($term_id not set !!!)
			$link = $this->add_new_translation_link($term_id, $taxonomy, $post_type, $language);
		} ?>

		<tr><?php
			if (isset($term_id)) { ?>
				<td class = "pll-language-column"><?php echo esc_html($language->name); ?></td>
				<td class = "pll-edit-column"><?php echo $link;?></td><?php
			}
			else { ?>
				<td class = "pll-language-column"><?php echo $language->flag ? $language->flag : esc_html($language->slug); ?></td><?php
			} ?>
			<td class = "pll-translation-column"><?php
				printf('<select name="term_tr_lang[%1$s]" id="tr_lang_%1$s"><option value="">%2$s</option>%3$s</select>',
					esc_attr($language->slug),
					__('None'),
					walk_category_dropdown_tree($translations, 0, array(
						'selected' => empty($translation) ? 0 : $translation->term_id,
						'show_count' => 0
					))
				); ?>
			</td>
		</tr><?php
	} // foreach ?>
</table><?php

if (isset($term_id)) {
	// edit term form?>
	</td><?php
}

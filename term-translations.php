<?php
// displays the translations fields
// FIXME inline CSS

if (isset($term_id)) { // edit term form?>
	<th scope="row" valign="top"><?php _e('Translations', 'polylang');?></th>
	<td><?php
}
else { // add term form?>
	<label><?php _e('Translations', 'polylang');?></label><?php
}
?>
<table style="width: 100%">
	<thead><tr><?php
		$style = 'style="padding: 0px; font-weight: bold; text-align: left"';
		foreach (array(__('Language', 'polylang'), __('Translation', 'polylang'), __('Edit', 'polylang')) as $title)
			printf('<th %s>%s</th>', $style, $title);?>
	</tr></thead>
	<tbody>
		<?php foreach ($listlanguages as $language) {
			$translation = 0;

			// look for any existing translation in this language
			if ($language != $lang) {
				if (isset($term_id) && $translation_id = $this->get_translated_term($term_id, $language))
					$translation = get_term($translation_id, $taxonomy);
				if (isset($_GET['from_tag']) && isset($_GET['from_lang'])) {
					if ($_GET['from_lang'] == $language->slug)
						$translation = get_term($_GET['from_tag'], $taxonomy);
					elseif ($translation_id = $this->get_translated_term($_GET['from_tag'], $language))
						$translation = get_term($translation_id, $taxonomy);
				}?>

				<tr><td style="padding: 0px"><?php echo $language->name;?></td><?php

				// no translation exits in this language
				if (!$translation) {
					$translations = $this->get_terms_not_translated($taxonomy, $language, $lang);
					if (!empty($translations)) { ?>
						<td style="padding: 0px">
							<select name="_lang-<?php echo $language->slug;?>" id="_lang-<?php echo $language->slug;?>" style="width: 15em">
								<option value="0"></option><?php
								foreach ($translations as $translation) { ?>
									<option value="<?php echo $translation->term_id;?>"><?php echo $translation->name;?></option><?php
								} ?>
							</select>
						</td><?php
					} 
					else { ?>
						<td style="padding: 0px">
						</td><?php
					} ?>
					<td style="padding: 0px"><?php
						// do not display the add new link in add term form ($term_id not set !!!)
						if (isset($term_id)) 
							printf('<a href="edit-tags.php?taxonomy=%s&amp;from_tag=%s&amp;from_lang=%s&amp;new_lang=%s">%s</a>',
								$taxonomy, $term_id, $lang->slug, $language->slug, __('Add new','polylang')) ?>
					</td><?php
				}

				// a translation exists
				else { ?>
					<td style="padding: 0px"><?php echo $translation->name; ?></td>									
					<td style="padding: 0px">
						<?php printf('<a href="edit-tags.php?action=edit&amp;taxonomy=%s&amp;tag_ID=%s">%s</a>', $taxonomy, $translation->term_id, __('Edit','polylang')) ?>
					</td><?php
				} ?>
				</tr><?php
			} // if (!$value)
		} // foreach ?>
	</tbody>
</table>
<?php if (isset($term_id)) { // edit term form?>
</td><?php
}

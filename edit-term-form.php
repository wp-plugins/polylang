<?php
// adds languages related fields in Edit Category and Edit Tag admin panels
// unfortunately can't reuse add-term-for.php as WordPress uses here a table instead of div :(

// displays the language select list
?>
<tr class="form-field">
	<th scope="row" valign="top"><label for="lang_choice"><?php _e('Language', 'polylang');?></label></th>
	<td><select name="lang_choice" id="lang_choice">
		<option value="-1"></option> <?php
		foreach ($listlanguages as $language) {
			printf("<option value='%s' %s>%s</option>\n", $language->term_id, $language == $lang ? 'selected="selected"' : '', $language->name);
		} ?>
	</select><br />
	<span class="description"><?php _e('Sets the language', 'polylang');?></span></td>
</tr> 
		
<?php
// displays the translations fields
// a table in the WP table is quite ugly but why WordPress does use a table instead of div as in the add term form ?
// FIXME inline CSS

if ($lang) { // do not display translation fields if term language is not set ?>		
<tr class="form-field">
	<th scope="row" valign="top"><?php _e('Translations', 'polylang');?></th>
	<td><table>
		<thead><tr>
			<th style="padding: 0px; font-weight: bold"><?php _e('Language', 'polylang');?></th>
			<th style="padding: 0px; font-weight: bold"><?php _e('Translation', 'polylang');?></th>
			<th style="padding: 0px; font-weight: bold"><?php  _e('Edit', 'polylang');?></th>
		</tr></thead>
		<tbody>
			<?php foreach ($listlanguages as $language) {
				if ($language != $lang) {
					$value = $this->get_translated_term($term_id, $language);?>
					<tr><td style="padding: 0px"><?php echo $language->name;?></td>
					<?php if (!$value) { 
						$translations = $this->get_terms_not_translated($taxonomy, $language, $lang);
						if (!empty($translations)) { ?>
							<td style="padding: 0px">
								<select name="_lang-<?php echo $language->slug;?>" id="_lang-<?php echo $language->slug;?>">
									<option value="-1"></option> <?php
									foreach ($translations as $translation) { ?>
										<option value="<?php echo $translation->term_id;?>"><?php echo $translation->name;?></option> <?php
									} ?>
								</select>
							</td> <?php
						} 
						else { ?>
							<td style="padding: 0px">
							</td> <?php
						} ?>
						<td style="padding: 0px">
							<?php printf('<a href="edit-tags.php?taxonomy=%s&amp;from_tag=%s&amp;from_lang=%s&amp;new_lang=%s">%s</a>',
								$taxonomy, $term_id, $lang->slug, $language->slug, __('Add new','polylang')) ?>
						</td> <?php
					}
					else { ?>
						<td style="padding: 0px"><?php
							$translation_id = $this->get_translated_term($term_id, $language);
							$translation = get_term_by('id', $translation_id, $taxonomy);
							echo $translation->name; ?>
						</td>									
						<td style="padding: 0px">
							<?php printf('<a href="edit-tags.php?action=edit&amp;taxonomy=%S&amp;tag_ID=%s">%s</a>', $taxonomy, $value, __('Edit','polylang')) ?>
						</td> <?php
					} ?>
					</tr><?php
				} // if (!$value)
			} // foreach ?>
		</tbody>
	</table></td>
</tr> <?php
} // if ($lang) ?>

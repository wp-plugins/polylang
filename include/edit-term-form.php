<?php
// adds languages related fields in Edit Category and Edit Tag admin panels
// unfortunately can't reuse add-term-for.php as WordPress uses here a table instead of div :(
?>
<tr class="form-field">
	<th scope="row" valign="top"><label for="term_lang_choice"><?php _e('Language', 'polylang');?></label></th>
	<td><select name="term_lang_choice" id="term_lang_choice"><?php
		if (PLL_DISPLAY_ALL) // for those who want undefined language
			echo '<option value="0"></option>';
		foreach ($listlanguages as $language) {
			printf(
				"<option value='%s'%s>%s</option>\n",
				esc_attr($language->term_id),
				$language == $lang ? ' selected="selected"' : '',
				esc_html($language->name)
			);
		} ?>
	</select><br />
	<span class="description"><?php _e('Sets the language', 'polylang');?></span></td>
</tr> 
		
<tr id="term-translations" class="form-field"><?php
// do not display translation fields if term language is not set (possible if PLL_DISPLAY_ALL == true)
if ($lang)
	include(PLL_INC.'/term-translations.php');?>
</tr>

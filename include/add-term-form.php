<?php
// adds a language select list in the Categories and Post tags admin panels
?>
<div class="form-field">
	<label for="term_lang_choice"><?php _e('Language', 'polylang');?></label>
	<select name="term_lang_choice" id="term_lang_choice"><?php
		if (PLL_DISPLAY_ALL) // for those who want undefined language
			echo '<option value="0"></option>';
		foreach ($listlanguages as $language) {
			printf("<option value='%d'%s>%s</option>\n",
				esc_attr($language->term_id),
				$language->slug == $lang->slug ? ' selected="selected"' : '',
				esc_html($language->name)
			);
		} ?>
	</select>
	<p><?php _e('Sets the language', 'polylang');?></p>
</div>
<div id="term-translations" class="form-field"><?php
include(PLL_INC.'/term-translations.php'); // adds translation fields ?>
</div>

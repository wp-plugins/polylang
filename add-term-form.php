<?php // adds a language select list in the Categories and Post tags admin panels ?>
<div class="form-field">
	<label for="term_lang_choice"><?php _e('Language', 'polylang');?></label>
	<select name="term_lang_choice" id="term_lang_choice">
		<option value="0"></option><?php
		foreach ($listlanguages as $language) {
			printf("<option value='%s'%s>%s</option>\n", esc_attr($language->term_id), $language == $lang ? ' selected="selected"' : '', esc_attr($language->name));
		} ?>
	</select>
	<p><?php _e('Sets the language', 'polylang');?></p>
</div>
<div id="term-translations" class="form-field"><?php
// adds translation field if we already know the language
if (isset($_GET['from_tag']) && isset($_GET['from_lang']) && isset($_GET['new_lang']))
	include(POLYLANG_DIR.'/term-translations.php');?>
</div>

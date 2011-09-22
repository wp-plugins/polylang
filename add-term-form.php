<?php // adds a language select list in the Categories and Post tags admin panels ?>
<div class="form-field">
	<label for="lang_choice"><?php _e('Language', 'polylang');?></label>
	<select name="lang_choice" id="lang_choice">
		<option value="-1"></option> <?php
		foreach ($listlanguages as $language) {
			printf("<option value='%s' %s>%s</option>\n", $language->term_id, $language == $lang ? 'selected="selected"' : '', $language->name);
		} ?>
	</select>
	<p><?php _e('Sets the language', 'polylang');?></p>
</div>

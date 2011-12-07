<?php
// allowing to choose the post's language 
// NOTE: the class "tags-input" allows to include the field in the autosave $_POST (see autosave.js)
?>
<p><em><?php $post_type == 'page' ? _e('Page\'s language:', 'polylang') : _e('Post\'s language:', 'polylang');?></em></p>
<p>
<select name="post_lang_choice" id="post_lang_choice"><?php
	if (PLL_DISPLAY_ALL) // for those who want undefined language
		echo '<option value="0"></option>';
	foreach ($listlanguages as $language) {
		printf(
			"<option value='%s'%s>%s</option>\n",
			esc_attr($language->slug),
			$language == $lang ? ' selected="selected"' : '',
			esc_html($language->name)
		);
	} ?>
	</select><br />
</p>
<div id="post-translations"><?php
include(PLL_INC.'/post-translations.php'); // allowing to determine the linked posts ?>
</div>

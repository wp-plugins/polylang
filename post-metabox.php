<?php // allowing to choose the post's language 
// NOTE: the class "tags-input" allows to include the field in the autosave $_POST (see autosave.js)?>
<p><em><?php $post_type == 'page' ? _e('Page\'s language:', 'polylang') : _e('Post\'s language:', 'polylang');?></em></p>
<p>
<select name="post_lang_choice" id="post_lang_choice" class="tags-input">
	<option value=""></option> <?php
	foreach ($listlanguages as $language) {
		printf("<option value='%s'%s>%s</option>\n", $language->slug, $language == $lang ? ' selected="selected"' : '', $language->name);
	} ?>
	</select><br />
</p>
<div id="post-translations">
<?php // allowing to determine the linked posts
if (isset($lang))
	include(POLYLANG_DIR.'/post-translations.php');
?>
</div>

<?php // allowing to choose the post's language ?>
<p><em><?php $post_type == 'page' ? _e('Page\'s language:', 'polylang') : _e('Post\'s language:', 'polylang');?></em></p>
<p><?php
	$listlanguages = $this->get_languages_list();
	foreach ($listlanguages as $language) { 
		printf('<input type="radio" name="lang_choice" value="%s" id="%s" %s />', $language->slug, $language->slug, $language == $lang ? 'checked="checked"' : '');
		printf('<label for %s"> %s</label><br />', $language->slug, $language->name);
	} ?>
</p>

<?php // allowing to determine the linked posts
// FIXME inline CSS ! ?>
<p><em><?php _e('ID of posts in other languages:', 'polylang');?></em></p>
<table style="width: 100%; text-align: left; font-size: 11px; margin: 0 6px;">
	<thead><tr>
		<th><?php _e('Language', 'polylang');?></th>
		<th><?php _e('Post ID', 'polylang');?></th>
		<th><?php  _e('Edit', 'polylang');?></th>
	</tr></thead>

	<tbody>
	<?php foreach ($listlanguages as $language) {
		if ($language != $lang) { 
			$value = $this->get_translated_post($post_ID, $language); 
			if (isset($_GET['from_post']))
				$value = $this->get_post($_GET['from_post'], $language); ?>			
			<tr>
			<td style="font-size: 11px;"><?php echo $language->name;?></td>
			<td><input name="<?php echo $language->slug;?>" id="<?php echo $language->slug;?>" type="text" value="<?php echo $value;?>" size="6"/></td><?php
			if ($lang) {				
				$link = $value ? 
					sprintf('<a href="post.php?action=edit&amp;post=%s">%s</a>', $value, __('Edit','polylang')) :
					sprintf('<a href="post-new.php?from_post=%s&amp;new_lang=%s">%s</a>',$post_ID, $language->slug, __('Add new','polylang')); ?>
				<td style="font-size: 11px;"><?php echo $link ?><td><?php
			}?>
			</tr><?php
		} 
	}	?>
	</tbody>
</table>

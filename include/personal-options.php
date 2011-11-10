<?php // allows each user to choose the admin language ?>
<tr>
	<th><label for='user_lang'><?php _e('Language', 'polylang');?></label></th>
	<td>
		<select name="user_lang" id="user_lang">
			<option value="0"></option><?php
			$listlanguages = $this->get_languages_list();
			foreach ($listlanguages as $language) {
				printf(
					"<option value='%s'%s>%s</option>\n",
					esc_attr($language->description),
					get_user_meta($profileuser->ID, 'user_lang', true) == $language->description ? ' selected="selected"' : '',
					esc_attr($language->name)
				);
			} ?>
		</select>
	</td>
</tr>

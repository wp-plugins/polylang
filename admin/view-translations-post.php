<?php
// displays the translations fields
?>

<p><strong><?php _e('Translations', 'polylang');?></strong></p>

<table>
	<?php foreach ($this->model->get_languages_list() as $language) {
		if ($language->term_id == $lang->term_id)
			continue;

		$posts = $this->get_posts_not_translated($post_type, $language, $lang);

		$value = $this->model->get_translation('post', $post_ID, $language);
		if (!$value || $value == $post_ID) // $value == $post_ID happens if the post has been (auto)saved before changing the language
			$value = '';
		if (isset($_GET['from_post']))
			$value = $this->model->get_post($_GET['from_post'], $language);

		if ($value) {
			$selected = get_post($value);

			// move the current post on top of the list
			foreach ($posts as $key => $post) {
				if ($post->ID == $selected->ID)
					unset($posts[$key]);
			}

			array_unshift($posts, $selected);

			$link = $this->edit_translation_link($value);
		}

		else {
			$link = $this->add_new_translation_link($post_ID, $post_type, $language);
		} ?>

		<tr>
			<td class = "pll-language-column"><?php echo $language->flag ? $language->flag : esc_html($language->slug); ?></td>
			<td class = "pll-edit-column"><?php echo $link;?></td>
			<td class = "pll-translation-column"><?php
				printf('<select name="post_tr_lang[%1$s]" id="tr_lang_%1$s" class="tags-input"><option value="">%2$s</option>%3$s</select>',
					esc_attr($language->slug),
					__('None'),
					walk_page_dropdown_tree($posts, 0, array('selected' => $value))
				); ?>
			</td>
		</tr><?php
	}?>
</table>

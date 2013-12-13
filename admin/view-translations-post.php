<?php
// displays the translations fields
?>

<p><em><?php $post_type == 'page' ? _e('ID of pages in other languages:', 'polylang') : _e('ID of posts in other languages:', 'polylang');?></em></p>
<table>
	<thead>
		<tr>
			<th><?php _e('Language', 'polylang');?></th>
			<th><?php $post_type == 'page' ? _e('Page ID', 'polylang') : _e('Post ID', 'polylang');?></th>
			<th><?php  _e('Edit', 'polylang');?></th>
		</tr>
	</thead>

	<tbody>
	<?php foreach ($this->model->get_languages_list() as $language) {
		if ($language->term_id != $lang->term_id) {
			$value = $this->model->get_translation('post', $post_ID, $language);
			if (!$value || $value == $post_ID) // $value == $post_ID happens if the post has been (auto)saved before changing the language
				$value = '';
			if (isset($_GET['from_post']))
				$value = $this->model->get_post($_GET['from_post'], $language); ?>
			<tr>
			<td><?php echo esc_html($language->name);?></td><?php
			printf(
				'<td><input name="post_tr_lang[%1$s]" id="tr_lang_%1$s" class="tags-input" type="text" value="%2$s" size="6"/></td>',
				esc_attr($language->slug),
				esc_attr($value)
			);

			if ($lang) {
				if ($value) {
					$link = sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url(get_edit_post_link($value)),
						__('Edit','polylang')
					);
				}
				else {
					$args = array(
						'post_type' => $post_type,
						'from_post' => $post_ID,
						'new_lang'  => $language->slug
					);

					$link = sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url(add_query_arg($args, admin_url('post-new.php'))),
						__('Add new','polylang')
					);
				} ?>
				<td><?php echo $link ?><td><?php
			}?>
			</tr><?php
		}
	}	?>
	</tbody>
</table>

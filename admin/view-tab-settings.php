<?php
// displays the settings tab in Polylang settings

$content_with_no_languages = $this->model->get_objects_with_no_lang() && $this->options['default_lang'];
$page_on_front = 'page' == get_option('show_on_front') ? get_option('page_on_front') : 0; ?>

<form id="options-lang" method="post" action="admin.php?page=mlang&amp;tab=settings&amp;noheader=true" class="validate">
<?php wp_nonce_field('options-lang', '_wpnonce_options-lang');?>
<input type="hidden" name="pll_action" value="options" />

<table class="form-table">
	<tr>
		<th <?php echo $content_with_no_languages ? 'rowspan=2' : ''; ?>>
			<label for='default_lang'><?php _e('Default language', 'polylang');?></label>
		</th>
		<td><?php
			$dropdown = new PLL_Walker_Dropdown;
			echo $dropdown->walk($listlanguages, array('name' => 'default_lang', 'selected' => $this->options['default_lang']));?>
		</td>
	</tr><?php

	// posts or terms without language set
	if ($content_with_no_languages) {?>
		<tr>
			<td>
				<label style="color: red"><?php
					printf(
						'<input name="fill_languages" type="checkbox" value="1" /> %s',
						__('There are posts, pages, categories or tags without language set. Do you want to set them all to default language ?', 'polylang')
					);?>
				</label>
			</td>
		</tr><?php
	}?>

	<tr>
		<th rowspan = <?php echo ($page_on_front ? 3 : 2) + $this->links_model->using_permalinks; ?>><?php _e('URL modifications', 'polylang') ?></th>
		<td><fieldset id='pll-force-lang'>
			<label><?php
				printf(
					'<input name="force_lang" type="radio" value="0" %s /> %s',
					$this->options['force_lang'] ? '' : 'checked="checked"',
					__('The language is set from content', 'polylang')
				);?>
			</label>
			<p class="description"><?php _e('Posts, pages, categories and tags urls are not modified.', 'polylang');?></p>
			<label><?php
				printf(
					'<input name="force_lang" type="radio" value="1" %s/> %s',
					1 == $this->options['force_lang'] ? 'checked="checked"' : '',
					$this->links_model->using_permalinks ? __('The language is set from the directory name in pretty permalinks', 'polylang') : __('The language is set from the code in the URL', 'polylang')
				);?>
			</label>
			<p class="description"><?php echo __('Example:', 'polylang') . ' <code>'.esc_html(home_url($this->links_model->using_permalinks ? 'en/my-post/' : '?lang=en&p=1')).'</code>';?></p>
			<label><?php
				printf(
					'<input name="force_lang" type="radio" value="2" %s %s/> %s',
					$this->links_model->using_permalinks ? '' : 'disabled="disabled"',
					2 == $this->options['force_lang'] ? 'checked="checked"' : '',
					__('The language is set from the subdomain name in pretty permalinks', 'polylang')
				);?>
			</label>
			<p class="description"><?php echo __('Example:', 'polylang') . ' <code>'.esc_html(str_replace(array('://', 'www.'), array('://en.', ''), home_url('my-post/'))).'</code>';?></p>
			<label><?php
				printf(
					'<input name="force_lang" type="radio" value="3" %s %s/> %s',
					$this->links_model->using_permalinks ? '' : 'disabled="disabled"',
					3 == $this->options['force_lang'] ? 'checked="checked"' : '',
					__('The language is set from different domains', 'polylang')
				);?>
			</label>
			<table id="pll-domains-table" <?php echo 3 == $this->options['force_lang'] ? '' : 'style="display: none;"'; ?>><?php
				foreach ($listlanguages as  $lg) {
					printf(
						'<tr><td><label for="pll-domain[%1$s]">%2$s</label></td>' .
						'<td><input name="domains[%1$s]" id="pll-domain[%1$s]" type="text" value="%3$s" size="40" aria-required="true" /></td></tr>',
						esc_attr($lg->slug),
						esc_attr($lg->name),
						esc_url(isset($this->options['domains'][$lg->slug]) ? $this->options['domains'][$lg->slug] : ($lg->slug == $this->options['default_lang'] ? $this->links_model->home : ''))
					);
				}?>
			</table>
		</fieldset></td>
	</tr>

	<tr>
		<td id="pll-hide-default" <?php echo 3 > $this->options['force_lang'] ? '' : 'style="display: none;"'; ?>><fieldset>
			<label><?php
				printf(
					'<input name="hide_default" type="checkbox" value="1" %s /> %s',
					$this->options['hide_default'] ? 'checked="checked"' :'',
					__('Hide URL language information for default language', 'polylang')
				);?>
			</label>
		</fieldset></td>
	</tr><?php

	if ($this->links_model->using_permalinks) { ?>
		<tr>
			<td id="pll-rewrite" <?php echo 2 > $this->options['force_lang'] ? '' : 'style="display: none;"'; ?>><fieldset>
				<label><?php
					printf(
						'<input name="rewrite" type="radio" value="1" %s %s/> %s',
						$this->links_model->using_permalinks ? '' : 'disabled="disabled"',
						$this->options['rewrite'] ? 'checked="checked"' : '',
						__('Remove /language/ in pretty permalinks', 'polylang')
					);?>
				</label>
				<p class="description"><?php echo __('Example:', 'polylang') . ' <code>'.esc_html(home_url('en/')).'</code>';?></p>
				<label><?php
					printf(
						'<input name="rewrite" type="radio" value="0" %s %s/> %s',
						$this->links_model->using_permalinks ? '' : 'disabled="disabled"',
						$this->options['rewrite'] ? '' : 'checked="checked"',
						 __('Keep /language/ in pretty permalinks', 'polylang')
					);?>
				</label>
				<p class="description"><?php echo __('Example:', 'polylang') . ' <code>'.esc_html(home_url('language/en/')).'</code>';?></p>
			</fieldset></td>
		</tr><?php
	}

	if ($page_on_front) { ?>
		<tr>
			<td><fieldset>
				<label><?php
					printf(
						'<input name="redirect_lang" type="checkbox" value="1" %s/> %s',
						$this->options['redirect_lang'] ? 'checked="checked"' :'',
						__('The front page url contains the language code instead of the page name or page id', 'polylang')
					);?>
				</label>
				<p class="description"><?php
					// that's nice to display the right home urls but don't forget that the page on front may have no language yet
					$lang = $this->model->get_post_language($page_on_front);
					$lang = $lang ? $lang : $this->model->get_language($this->options['default_lang']);
					printf(
						__('Example: %s instead of %s', 'polylang'),
						'<code>' . esc_html($this->links_model->home_url($lang)) . '</code>',
						'<code>' . esc_html(_get_page_link($page_on_front)) . '</code>'
					); ?>
				</p>
			</fieldset></td>
		</tr><?php
	} ?>

	<tr id="pll-detect-browser" <?php echo 3 > $this->options['force_lang'] ? '' : 'style="display: none;"'; ?>>
		<th><?php _e('Detect browser language', 'polylang');?></th>
		<td>
			<label><?php
				printf(
					'<input name="browser" type="checkbox" value="1" %s /> %s',
					$this->options['browser'] ? 'checked="checked"' :'',
					__('When the front page is visited, set the language according to the browser preference', 'polylang')
				);?>
			</label>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php _e('Media', 'polylang') ?></th>
		<td>
			<label><?php
				printf(
					'<input name="media_support" type="checkbox" value="1" %s /> %s',
					$this->options['media_support'] ? 'checked="checked"' :'',
					__('Activate languages and translations for media', 'polylang')
				);?>
			</label>
		</td>
	</tr><?php

	if (!empty($post_types)) {?>
		<tr>
			<th scope="row"><?php _e('Custom post types', 'polylang') ?></th>
			<td>
				<ul class="pll_inline_block"><?php
					foreach ($post_types as $post_type) {
						$pt = get_post_type_object($post_type);
						printf(
							'<li><label><input name="post_types[%s]" type="checkbox" value="1" %s /> %s</label></li>',
							esc_attr($post_type),
							in_array($post_type, $this->options['post_types']) ? 'checked="checked"' :'',
							esc_html($pt->labels->name)
						);
					}?>
				</ul>
				<p class="description"><?php _e('Activate languages and translations for custom post types.', 'polylang');?></p>
			</td>
		</tr><?php
	}

	if (!empty($taxonomies)) {?>
		<tr>
			<th scope="row"><?php _e('Custom taxonomies', 'polylang') ?></th>
			<td>
				<ul class="pll_inline_block"><?php
					foreach ($taxonomies as $taxonomy) {
						$tax = get_taxonomy($taxonomy);
						printf(
							'<li><label><input name="taxonomies[%s]" type="checkbox" value="1" %s /> %s</label></li>',
							esc_attr($taxonomy),
							in_array($taxonomy, $this->options['taxonomies']) ? 'checked="checked"' :'',
							esc_html($tax->labels->name)
						);
					}?>
				</ul>
				<p class="description"><?php _e('Activate languages and translations for custom taxonomies.', 'polylang');?></p>
			</td>
		</tr><?php
	}?>

	<tr>
		<th scope="row"><?php _e('Synchronization', 'polylang') ?></th>
		<td>
			<ul class="pll_inline_block"><?php
				foreach (self::list_metas_to_sync() as $key => $str)
					printf(
						'<li><label><input name="sync[%s]" type="checkbox" value="1" %s /> %s</label></li>',
						esc_attr($key),
						in_array($key, $this->options['sync']) ? 'checked="checked"' :'',
						esc_html($str)
					);?>
			</ul>
			<p class="description"><?php _e('The synchronization options allow to maintain exact same values (or translations in the case of taxonomies and page parent) of meta content between the translations of a post or page.', 'polylang');?></p>
		</td>
	</tr>

</table>

<?php submit_button(); // since WP 3.1 ?>

</form>

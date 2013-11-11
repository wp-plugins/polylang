<?php
// displays the Languages admin panel
?>
<div class="wrap">
<?php screen_icon('options-general'); ?>
<h2 class="nav-tab-wrapper"><?php
// display tabs
foreach ($tabs as $key=>$name)
	printf(
		'<a href="options-general.php?page=mlang&amp;tab=%s" class="nav-tab %s">%s</a>',
		$key,
		$key == $active_tab ? 'nav-tab-active' : '',
		$name
	);?>
</h2><?php

switch($active_tab) {

// Languages tab
case 'lang':

if (isset($_GET['error'])) {?>
<div id="message" class="error fade"><p><?php echo $errors[$_GET['error']]; ?></p></div><?php
}?>

<div id="col-container">
	<div id="col-right">
		<div class="col-wrap"><?php
			// displays the language list in a table
			$list_table->display();?>
			<div class="metabox-holder"><?php
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
				do_meta_boxes('settings_page_mlang', 'normal', array());?>
			</div>
		</div><!-- col-wrap -->
	</div><!-- col-right -->

	<div id="col-left">
		<div class="col-wrap">

			<div class="form-wrap">
				<h3><?php echo $action=='edit' ? __('Edit language', 'polylang') :	__('Add new language', 'polylang'); ?></h3><?php

				// displays the add (or edit) language form
				// adds noheader=true in the action url to allow using wp_redirect when processing the form ?>
				<form id="add-lang" method="post" action="admin.php?page=mlang&amp;noheader=true" class="validate">
				<?php wp_nonce_field('add-lang', '_wpnonce_add-lang');

				if ($action=='edit') {?>
					<input type="hidden" name="pll_action" value="update" />
					<input type="hidden" name="lang_id" value="<?php echo esc_attr($edit_lang->term_id);?>" /><?php
				}
				else { ?>
					<input type="hidden" name="pll_action" value="add" /><?php
				}?>

				<div class="form-field">
					<label for="lang_list"><?php _e('Choose a language', 'polylang');?></label>
					<select name="lang_list" id="lang_list">
						<option value=""></option><?php
						include(PLL_ADMIN_INC.'/languages.php');
						foreach ($languages as $lg) {
							printf(
								'<option value="%1$s-%2$s-%3$s">%4$s - %2$s</option>'."\n",
								esc_attr($lg[0]),
								esc_attr($lg[1]),
								isset($lg[3]) ? '1' : '0',
								esc_html($lg[2])
							);
						} ?>
					</select>
					<p><?php _e('You can choose a language in the list or directly edit it below.', 'polylang');?></p>
				</div>

				<div class="form-field form-required">
					<label for="lang_name"><?php _e('Full name', 'polylang');?></label>
					<input name="name" id="lang_name" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->name);?>" size="40" aria-required="true" />
					<p><?php _e('The name is how it is displayed on your site (for example: English).', 'polylang');?></p>
				</div>

				<div class="form-field form-required">
					<label for="lang_locale"><?php _e('Locale', 'polylang');?></label><?php
					printf(
						'<input name="locale" id="lang_locale" type="text" value="%s" size="40" aria-required="true" />',
						$action=='edit' ? esc_attr($edit_lang->locale) : ''
					);?>
					<p><?php _e('Wordpress Locale for the language (for example: en_US). You will need to install the .mo file for this language.', 'polylang');?></p>
				</div>

				<div class="form-field">
					<label for="lang_slug"><?php _e('Language code', 'polylang');?></label>
					<input name="slug" id="lang_slug" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->slug);?>" size="40"/>
					<p><?php _e('2-letters ISO 639-1 language code (for example: en)', 'polylang');?></p>
				</div>

				<div class="form-field"><fieldset>
					<legend><?php _e('Text direction', 'polylang');?></legend><?php
					printf(
						'<label><input name="rtl" type="radio" value="0" %s /> %s</label>',
						$action == 'edit' && $edit_lang->is_rtl ? '' : 'checked="checked"',
						__('left to right', 'polylang')
					);
					printf(
						'<label><input name="rtl" type="radio" value="1" %s /> %s</label>',
						$action == 'edit' && $edit_lang->is_rtl ? 'checked="checked"' : '',
						__('right to left', 'polylang')
					);?>
					<p><?php _e('Choose the text direction for the language', 'polylang');?></p>
				</fieldset></div>

				<div class="form-field">
					<label for="lang_order"><?php _e('Order', 'polylang');?></label>
					<input name="term_group" id="lang_order" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->term_group);?>" />
					<p><?php _e('Position of the language in the language switcher', 'polylang');?></p>
				</div>

				<?php submit_button( $action == 'edit' ? __('Update') : __('Add new language', 'polylang'), 'button'); // since WP 3.1 ?>

				</form>
			</div><!-- form-wrap -->
		</div><!-- col-wrap -->
	</div><!-- col-left -->
</div><!-- col-container -->

<script type="text/javascript">
	//<![CDATA[
	jQuery(document).ready( function($) {
		// close postboxes that should be closed
		$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
		// postboxes setup
		postboxes.add_postbox_toggles('settings_page_mlang');
	});
	//]]>
</script><?php
break;

// string translations tab
case 'strings': ?>

<div class="form-wrap">
	<form id="string-translation" method="post" action="admin.php?page=mlang&amp;tab=strings&amp;noheader=true">
		<input type="hidden" name="pll_action" value="string-translation" /><?php
		$string_table->search_box(__('Search translations', 'polylang'), 'translations' );
		wp_nonce_field('string-translation', '_wpnonce_string-translation');
		$string_table->display();
		printf('<br /><label><input name="clean" type="checkbox" value="1" /> %s</label>', __('Clean strings translation database', 'polylang')); ?>
		<p><?php _e('Use this to remove unused strings from database, for example after a plugin has been uninstalled.', 'polylang');?></p><?php
		submit_button(); // since WP 3.1 ?>
	</form>
</div><?php
break;

// settings tab
case 'settings': ?>

<div class="form-wrap">
	<form id="options-lang" method="post" action="admin.php?page=mlang&amp;tab=settings&amp;noheader=true" class="validate">
	<?php wp_nonce_field('options-lang', '_wpnonce_options-lang');?>
	<input type="hidden" name="pll_action" value="options" />

	<table class="form-table">

		<tr>
			<th><label for='default_lang'><?php _e('Default language', 'polylang');?></label></th>
			<td><?php
				$dropdown = new PLL_Walker_Dropdown;
				echo $dropdown->walk($listlanguages, array('name' => 'default_lang', 'selected' => $this->options['default_lang']));?>
			</td>
		</tr><?php

		// posts or terms without language set
		if ($this->model->get_objects_with_no_lang() && $this->options['default_lang']) {?>
			<tr>
				<th></th>
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
			<th scope="row"><?php _e('URL modifications', 'polylang') ?></th>
			<td>
				<label><?php
					printf(
						'<input name="force_lang" type="radio" value="0" %s /> %s',
						$this->options['force_lang'] ? '' : 'checked="checked"',
						__('The language is set from content. Posts, pages, categories and tags urls are not modified.', 'polylang')
					);?>
				</label>
				<label><?php
					printf(
						'<input name="force_lang" type="radio" value="1" %s %s/> %s %s',
						$using_permalinks ? '' : 'disabled=1',
						$this->options['force_lang'] == 1 ? 'checked="checked"' : '',
						__('The language is set from the directory name in pretty permalinks. Example:', 'polylang'),
						'<code>'.esc_html(home_url('en/my-post/')).'</code>'
					);?>
				</label>
				<label><?php
					printf(
						'<input name="force_lang" type="radio" value="2" %s %s/> %s %s',
						$using_permalinks ? '' : 'disabled=1',
						$this->options['force_lang'] == 2 ? 'checked="checked"' : '',
						__('The language is set from the subdomain name in pretty permalinks. Example:', 'polylang'),
						'<code>'.esc_html(str_replace(array('://', 'www.'), array('://en.', ''), home_url('my-post/'))).'</code>'
					);?>
				</label>
				<label><?php
					printf(
						'<input name="force_lang" type="radio" value="3" %s %s/> %s',
						$using_permalinks ? '' : 'disabled=1',
						$this->options['force_lang'] == 3 ? 'checked="checked"' : '',
						__('The language is set from different domains:', 'polylang')
					);?>
				</label>
				<table class="pll-domains-table"><?php
					foreach ($listlanguages as  $lg) {
						printf(
							'<tr><td><label for="pll-domain[%1$s]">%2$s</label></td>' .
							'<td><input name="domains[%1$s]" id="pll-domain[%1$s]" type="text" value="%3$s" size="40" aria-required="true" %4$s /></td></tr>',
							esc_attr($lg->slug),
							esc_attr($lg->name),
							esc_url($lg->slug == $this->options['default_lang'] ? get_option('home') : (isset($this->options['domains'][$lg->slug]) ? $this->options['domains'][$lg->slug] : '')),
							!$using_permalinks || $lg->slug == $this->options['default_lang'] ? 'disabled=1' : ''
						);
					}?>
				</table>
				<br />
				<label><?php
					printf(
						'<input name="rewrite" type="radio" value="1" %s %s/> %s %s',
						$using_permalinks ? '' : 'disabled',
						$this->options['rewrite'] ? 'checked="checked"' : '',
						__('Remove /language/ in pretty permalinks. Example:', 'polylang'),
						'<code>'.esc_html(home_url('en/')).'</code>'
					);?>
				</label>
				<label><?php
					printf(
						'<input name="rewrite" type="radio" value="0" %s %s/> %s %s',
						$using_permalinks ? '' : 'disabled',
						$this->options['rewrite'] ? '' : 'checked="checked"',
						 __('Keep /language/ in pretty permalinks. Example:', 'polylang'),
						'<code>'.esc_html(home_url('language/en/')).'</code>'
					);?>
				</label>
				<br />
				<label><?php
					printf(
						'<input name="hide_default" type="checkbox" value="1" %s /> %s',
						$this->options['hide_default'] ? 'checked="checked"' :'',
						__('Hide URL language information for default language', 'polylang')
					);?>
				</label>
				<br />
				<label><?php
					printf(
						'<input name="redirect_lang" type="checkbox" value="1" %s %s/> %s',
						get_option('page_on_front') ? '' : 'disabled',
						$this->options['redirect_lang'] ? 'checked="checked"' :'',
						sprintf(
							__('When using static front page, redirect the language page (example: %s) to the front page in the right language', 'polylang'),
							'<code>'.esc_html(home_url('en/')).'</code>'
						)
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
					<p><?php _e('Activate languages and translations for custom post types.', 'polylang');?></p>
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
					<p><?php _e('Activate languages and translations for custom taxonomies.', 'polylang');?></p>
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
				<p><?php _e('The synchronization options allow to maintain exact same values (or translations in the case of taxonomies and page parent) of meta content between the translations of a post or page.', 'polylang');?></p>
			</td>
		</tr>

	</table>

	<?php submit_button(); // since WP 3.1 ?>

	</form>
</div><!-- form-wrap --><?php
break;

default:
break;
}?>

</div><!-- wrap -->

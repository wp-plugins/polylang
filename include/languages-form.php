<?php
// displays the Languages admin panel
?>
<div class="wrap">
<?php screen_icon('options-general'); ?>
<h2 class="nav-tab-wrapper"><?php
// display tabs
foreach ($tabs as $key=>$name)
	printf('<a href="options-general.php?page=mlang&tab=%s" class="nav-tab %s">%s</a>', $key, $key == $active_tab ? 'nav-tab-active' : '', $name);?>
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
				do_meta_boxes('settings_page_mlang', 'normal', array());?>
			</div>
		</div><!-- col-wrap -->
	</div><!-- col-right -->

	<div id="col-left">
		<div class="col-wrap">

			<div class="form-wrap">
				<h3><?php echo $action=='edit' ? _e('Edit language','polylang') :	_e('Add new language','polylang'); ?></h3><?php

				// displays the add (or edit) language form
				// The term fields are used as follows :
				// name -> language name (used only for display)
				// slug -> language code (ideally 2-letters ISO 639-1 language code but I currently use it only as slug so it doesn't matter)
				// description -> WordPress locale for the language. Here if something wrong is used, the .mo files will not be loaded...
				// term_group -> order

				// adds noheader=true in the action url to allow using wp_redirect when processing the form ?>
				<form id="add-lang" method="post" action="admin.php?page=mlang&amp;noheader=true" class="validate">
				<?php wp_nonce_field('add-lang', '_wpnonce_add-lang');

				if ($action=='edit') {?>
					<input type="hidden" name="action" value="update" />
					<input type="hidden" name="lang_id" value="<?php echo esc_attr($edit_lang->term_id);?>" /><?php
				}
				else { ?>
					<input type="hidden" name="action" value="add" /><?php
				}?>

				<div class="form-field">
					<label for="lang_list"><?php _e('Choose a language', 'polylang');?></label>
					<select name="lang_list" id="lang_list">
						<option value=""></option><?php
						include(PLL_INC.'/languages.php');
						foreach ($languages as $lg) {
							printf('<option value="%1$s-%2$s-%3$s">%4$s - %2$s</option>'."\n", esc_attr($lg[0]), esc_attr($lg[1]), isset($lg[3]) ? '1' : '0' , esc_html($lg[2]));
						} ?>
					</select>
					<p><?php _e('You can choose a language in the list or directly edit it below.', 'polylang');?></p>
				</div>

				<div class="form-field form-required">
					<label for="name"><?php _e('Full name', 'polylang');?></label>
					<input name="name" id="name" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->name);?>" size="40" aria-required="true" />
					<p><?php _e('The name is how it is displayed on your site (for example: English).', 'polylang');?></p>
				</div>

				<div class="form-field form-required">
					<label for="description"><?php _e('Locale', 'polylang');?></label><?php
					printf('<input name="description" id="description" type="text" value="%s" size="7" maxlength="7" aria-required="true" />',
						$action=='edit' ? esc_attr($edit_lang->description) : '');?>
					<p><?php _e('Wordpress Locale for the language (for example: en_US). You will need to install the .mo file for this language.', 'polylang');?></p>
				</div>

				<div class="form-field">
					<label for="slug"><?php _e('Language code', 'polylang');?></label>
					<input name="slug" id="slug" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->slug);?>" size="3" maxlength="3"/>
					<p><?php _e('2-letters ISO 639-1 language code (for example: en)', 'polylang');?></p>
				</div>

				<div class="form-field">
					<legend><?php _e('Text direction', 'polylang');?></legend><?php
					printf('<label><input name="rtl" type="radio" class="tog" value="0" %s /> %s</label>',
						$rtl ? '' : 'checked="checked"', __('left to right', 'polylang'));
					printf('<label><input name="rtl" type="radio" class="tog" value="1" %s /> %s</label>',
						$rtl ? 'checked="checked"' : '', __('right to left', 'polylang'));?>
					<p><?php _e('Choose the text direction for the language', 'polylang');?></p>
				</div>

				<div class="form-field">
					<label for="term_group"><?php _e('Order', 'polylang');?></label>
					<input name="term_group" id="term_group" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->term_group);?>" />
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

// menu tab
case 'menus': ?>

<div class="form-wrap">
	<form id="nav-menus-lang" method="post" action="admin.php?page=mlang&tab=menus" class="validate">
	<?php wp_nonce_field('nav-menus-lang', '_wpnonce_nav-menus-lang');?>
	<input type="hidden" name="action" value="nav-menus" /><?php

	foreach ( $locations as $location => $description ) {?>
		<h3><?php echo esc_html($description); ?></h3>
		<table class="form-table"><?php
			foreach ($listlanguages as $language) {?>
				<tr><?php printf('<th><label for="menu-lang-%1$s-%2$s">%3$s</label></th>', esc_attr($location), esc_attr($language->slug), esc_html($language->name));?>
					<td><?php printf('<select name="menu-lang[%1$s][%2$s]" id="menu-lang-%1$s-%2$s">', esc_attr($location), esc_attr($language->slug));?>
						<option value="0"></option><?php
						foreach ($menus as $menu) {
							printf(
								"<option value='%d'%s>%s</option>\n",
								esc_attr($menu->term_id),
								isset($menu_lang[$location][$language->slug]) && $menu_lang[$location][$language->slug] == $menu->term_id ? ' selected="selected"' : '',
								esc_html($menu->name)
							);
						} ?>
					</select></td>
				</tr><?php
			}?>
			<tr>
				<th><?php _e('Language switcher', 'polylang') ?></th>
				<td><?php
					foreach ($this->get_switcher_options('menu') as $key => $str)
						printf('<label><input name="menu-lang[%1$s][%2$s]" type="checkbox" value="1" %3$s /> %4$s</label>',
							esc_attr($location), esc_attr($key), isset($menu_lang[$location][$key]) && $menu_lang[$location][$key] ? 'checked="checked"' :'', esc_html($str));?>
				</td>
			</tr>
		</table><?php
	}

	submit_button(); // since WP 3.1 ?>

	</form>
</div><!-- form-wrap --><?php
break;

// string translations tab
case 'strings':

	$paged = isset($_GET['paged']) ? '&paged='.$_GET['paged'] : '';?>
	<form id="string-translation" method="post" action="<?php echo esc_url(admin_url('admin.php?page=mlang&tab=strings'.$paged.'&noheader=true'))?>" class="validate">
	<?php wp_nonce_field('string-translation', '_wpnonce_string-translation');?>
	<input type="hidden" name="action" value="string-translation" /><?php
	$string_table->display();
	submit_button(); // since WP 3.1 ?>
	</form><?php
break;

// settings tab
case 'settings': ?>

<div class="form-wrap">
	<form id="options-lang" method="post" action="admin.php?page=mlang&tab=settings" class="validate">
	<?php wp_nonce_field('options-lang', '_wpnonce_options-lang');?>
	<input type="hidden" name="action" value="options" />

	<table class="form-table">

		<tr>
			<th><label for='default_lang'><?php _e('Default language', 'polylang');?></label></th>
			<td><?php echo $this->dropdown_languages(array('name' => 'default_lang', 'selected' => $options['default_lang']));?></td>
		</tr><?php

		// posts or terms without language set
		if ($untranslated && $options['default_lang']) {?>
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
						$options['browser'] ? 'checked="checked"' :'',
						__('When the front page is visited, set the language according to the browser preference', 'polylang')
					);?>
				</label>
			</td>
		</tr>

		<tr>
			<th><?php _e('URL modifications', 'polylang') ?></th>
			<td scope="row">
				<label><?php
					printf(
						'<input name="rewrite" type="radio" value="0" %s /> %s %s',
						$options['rewrite'] ? '' : 'checked="checked"',
						 __('Keep /language/ in pretty permalinks. Example:', 'polylang'),
						'<code>'.esc_html(home_url('language/en/')).'</code>'
					);?>
				</label>
				<label><?php
					printf(
						'<input name="rewrite" type="radio" value="1" %s /> %s %s',
						$options['rewrite'] ? 'checked="checked"' : '',
						__('Remove /language/ in pretty permalinks. Example:', 'polylang'),
						'<code>'.esc_html(home_url('en/')).'</code>'
					);?>
				</label>
				<label><?php
					printf(
						'<input name="hide_default" type="checkbox" value="1" %s /> %s',
						$options['hide_default'] ? 'checked="checked"' :'',
						__('Hide URL language information for default language', 'polylang')
					);?>
				</label>
				<label><?php
					printf(
						'<input name="force_lang" type="checkbox" value="1" %s /> %s',
						$options['force_lang'] ? 'checked="checked"' :'',
						__('Add language information to all URL including posts, pages, categories and post tags (not recommended)', 'polylang')
					);?>
				</label>
				<label><?php
					printf(
						'<input name="redirect_lang" type="checkbox" value="1" %s /> %s',
						$options['redirect_lang'] ? 'checked="checked"' :'',
						sprintf(__('Redirect the language page (example: %s) to the homepage in the right language', 'polylang'), '<code>'.esc_html(home_url('en/')).'</code>')
					);?>
				</label>
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

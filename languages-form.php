<?php // displays the Languages admin panel 

// I don't use the custom taxonomy form provide by Wordpress because I want to modify the labels below the fields...
// It seems that there is currently (3.2.1) no filter to do this

// The term fields are used as follows :
// name -> language name (used only for display)
// slug -> language code (ideally 2-letters ISO 639-1 language code but I currently use it only as slug so it doesn't matter)
// description -> WordPress Locale for the language. Here if something wrong is used, the .mo files will not be loaded...

?>
<div class="wrap">
<?php screen_icon('options-general'); ?>
<h2><?php _e('Languages','polylang') ?></h2>

<div id="col-container">
<div id="col-right">
<div class="col-wrap">

<?php $list_table->display(); // displays the language list in a table ?>

</div> <!-- col-wrap -->
</div> <!-- col-right -->

<div id="col-left">
<div class="col-wrap">

<div class="form-wrap">
<h3><?php echo $action=='edit' ? _e('Edit language','polylang') :	_e('Add new language','polylang'); ?></h3>

<?php // displays the add (or edit) language form
 
// adds noheader=true in the action url to allow using wp_redirect when processing the form ?>
<form id="add-lang" method="post" action="admin.php?page=mlang&amp;noheader=true" class="validate">
<?php wp_nonce_field('add-lang', '_wpnonce_add-lang');

if ($action=='edit') {?>
	<input type="hidden" name="action" value="update" /> 
	<input type="hidden" name="lang" value="<?php echo esc_attr($edit_lang->term_id);?>" /><?php
}
else { ?>
	<input type="hidden" name="action" value="add" /><?php
}?> 

<div class="form-field form-required">
	<label for="name"><?php _e('Full name', 'polylang');?></label>
	<input name="name" id="name" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->name);?>" size="40" aria-required="true" />
	<p><?php _e('The name is how it is displayed on your site (for example: English).', 'polylang');?></p>
</div>

<div class="form-field form-required">
	<label for="description"><?php _e('Locale', 'polylang');?></label>
	<input name="description" id="description" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->description);?>" size="40" aria-required="true" />
	<p><?php _e('Wordpress Locale for the language (for example: en_US). You will need to install the .mo file for this language.', 'polylang');?></p>
</div>

<div class="form-field">
	<label for="slug"><?php _e('Language code', 'polylang');?></label>
	<input name="slug" id="slug" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->slug);?>" size="40" />
	<p><?php _e('2-letters ISO 639-1 language code (for example: en)', 'polylang');?></p>
</div>

<?php if ($action=='edit')
	submit_button(__('Update'), 'button'); // since WP 3.1
else			
	submit_button(__('Add new language', 'polylang'), 'button'); // since WP 3.1?>

</form>
</div> <!-- form-wrap -->
</div> <!-- col-wrap -->
</div> <!-- col-left -->
</div> <!-- col-container --> <?php

// displays the nav menus languages form
if (current_theme_supports( 'menus' )) { ?>

<div class="form-wrap">
<h3 id="menus"><?php _e('Menus','polylang');?></h3>

<form id="nav-menus-lang" method="post" action="admin.php?page=mlang" class="validate">
<?php wp_nonce_field('nav-menus-lang', '_wpnonce_nav-menus-lang');?>
<input type="hidden" name="action" value="nav-menus" /> 

<table class="wp-list-table widefat fixed tags" cellspacing="0" style="width: auto">
	<thead><tr>
		<th><?php _e('Theme location','Polylang') ?></th><?php
		foreach ($listlanguages as $language) {
			echo '<th>' . esc_attr($language->name) . '</th>';
		} ?>
	</tr></thead>

	<tbody>
	<?php foreach ( $locations as $location => $description ) { ?>
		<tr><td><?php echo esc_attr($description); ?></td><?php
		foreach ($listlanguages as $language) { ?>
			<td><?php
				printf(
					'<select name="menu-lang[%s][%s]" id="menu-lang-%s-%s">',
					esc_attr($location),
					esc_attr($language->slug),
					esc_attr($location),
					esc_attr($language->slug)
				);?>
				<option value="0"></option><?php
				foreach ( $menus as $menu ) {
					printf(
						"<option value='%s'%s>%s</option>\n",
						esc_attr($menu->term_id),
						$menu_lang[$location][$language->slug] == $menu->term_id ? ' selected="selected"' : '',
						esc_attr($menu->name)
					);
				} ?>
			</select></td><?php
		}?>
		</tr><?php
	}?>
	</tbody>

</table>

<?php submit_button(); // since WP 3.1 ?>

</form>
</div> <!-- form-wrap --> <?php

} // if (current_theme_supports( 'menus' ))

// displays the Polylang options form
//FIXME one inline CSS  ?>
<div class="form-wrap">
<h3><?php _e('Options','polylang');?></h3>

<form id="options-lang" method="post" action="admin.php?page=mlang" class="validate">
<?php wp_nonce_field('options-lang', '_wpnonce_options-lang');?>
<input type="hidden" name="action" value="options" /> 

<table class="form-table">

<tr>
	<th style="width: 300px;"><label><?php
		printf(
			'<input name="rewrite" type="radio" value="0" %s /> %s', 
			$options['rewrite'] ? '' : 'checked="checked"',
			 __('Keep /language/ in pretty permalinks', 'polylang')
		);?>
	</label></th>
	<td><code><?php echo home_url('language/en/'); ?></code></td>
</tr>

<tr>
	<th><label><?php
		printf(
			'<input name="rewrite" type="radio" value="1" %s /> %s', 
		$options['rewrite'] ? 'checked="checked"' : '',
		__('Remove /language/ in pretty permalinks', 'polylang')
		);?>
	</label></th>
	<td><code><?php echo home_url('en/'); ?></code></td>
</tr>

<tr>
	<th><?php _e('The front page default language is : ', 'polylang');?></th>
	<td><label><?php
		printf(
			'<input name="browser" type="checkbox" value="1" %s /> %s',
			$options['browser'] ? 'checked="checked"' :'',
			__('set by the browser preference', 'polylang')
		);?>
		</label>
		<label><?php _e('otherwise: ', 'polylang');?><select name="default_lang" id="default_lang">
			<?php $listlanguages = $this->get_languages_list();
			foreach ($listlanguages as $language) {
				printf(
					"<option value='%s'%s>%s</option>\n",
					esc_attr($language->slug),
					$options['default_lang'] == $language->slug ? ' selected="selected"' : '',
					esc_attr($language->name)
				);
			} ?>
		</select></label>
	</td>
</tr>

</table>

<?php submit_button(); // since WP 3.1 ?>

</form>
</div> <!-- form-wrap -->

</div> <!-- wrap -->

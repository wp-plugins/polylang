<?php
// displays the languages tab in Polylang settings
?>

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
					<p><?php _e('WordPress Locale for the language (for example: en_US). You will need to install the .mo file for this language.', 'polylang');?></p>
				</div>

				<div class="form-field">
					<label for="lang_slug"><?php _e('Language code', 'polylang');?></label>
					<input name="slug" id="lang_slug" type="text" value="<?php if ($action=='edit') echo esc_attr($edit_lang->slug);?>" size="40"/>
					<p><?php _e('Language code - preferably 2-letters ISO 639-1  (for example: en)', 'polylang');?></p>
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
</script>

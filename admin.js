jQuery(document).ready(function($) {

		// ajax for post metabox
		jQuery('#post_lang_choice').change( function() {
			var data = {
				action: 'post_lang_choice',
				lang: jQuery(this).attr('value'),
				post_id: jQuery('#post_ID').attr('value')
			}

			jQuery.post(ajaxurl, data , function(response) {
				jQuery("#post-translations").html(response);
			});    
		})

		// ajax for term edit
		jQuery('#term_lang_choice').change( function() {
			var data = {
				action: 'term_lang_choice',
				lang: jQuery(this).attr('value'),
				term_id: jQuery("input[name='tag_ID']").attr('value'),
				taxonomy: jQuery("input[name='taxonomy']").attr('value')
			}

			jQuery.post(ajaxurl, data, function(response) {
				jQuery("#term-translations").html(response);
			});    
		})

});

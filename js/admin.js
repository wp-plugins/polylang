jQuery(document).ready(function($) {

	// collect taxonomies - code partly copied from WordPress
	var taxonomies = new Array();
	$('.categorydiv').each( function(){
		var this_id = $(this).attr('id'), taxonomyParts, taxonomy;

		taxonomyParts = this_id.split('-');
		taxonomyParts.shift();
		taxonomy = taxonomyParts.join('-');
		taxonomies.push(taxonomy); // store the taxonomy for future use

		// add our hidden field in the new category form - for each hierarchical taxonomy
		jQuery('#' + taxonomy + '-add-submit').before($('<input />')
			.attr('type', 'hidden')
			.attr('id', taxonomy + '-lang')
			.attr('name', 'term_lang_choice')
			.attr('value', jQuery('#post_lang_choice').attr('value'))
		);
	});

	// ajax for post metabox
	jQuery('#post_lang_choice').change( function() {
		var data = {
			action: 'post_lang_choice',
			lang: jQuery(this).attr('value'),
			taxonomies: taxonomies,
			post_id: jQuery('#post_ID').attr('value')
		}

		jQuery.post(ajaxurl, data , function(response) {
			var res = wpAjax.parseAjaxResponse(response, 'ajax-response');
			$.each(res.responses, function() {
				switch(this.what) {
					case 'translations':
						jQuery('#post-translations').html(this.data); // translations fields
						break;
					case 'taxonomy':
						tax = this.data;
						jQuery('#' + tax + 'checklist').html(this.supplemental.all);
						jQuery('#' + tax + 'checklist-pop').html(this.supplemental.populars);
						jQuery('#new' + tax + '_parent').replaceWith(this.supplemental.dropdown);
						jQuery('#' + tax + '-lang').val(jQuery('#post_lang_choice').attr('value')); // hidden field
						break;
					default:
						break;
				}
			});
		});
	});

	// replace WP class by our own
	jQuery('a.tagcloud-link').addClass('polylang-tagcloud-link');
	jQuery('a.tagcloud-link').removeClass('tagcloud-link');

	// now copy paste WP code and just add the language in the $_POST variable
	jQuery('.polylang-tagcloud-link').click( function() {
		var id = $(this).attr('id');
		var tax = id.substr(id.indexOf('-')+1);

		var data = {
			action: 'get-tagcloud',
			lang: jQuery('#post_lang_choice').attr('value'),
			tax: tax
		}

		$.post(ajaxurl, data, function(r, stat) {
			if ( 0 == r || 'success' != stat )
				r = wpAjax.broken;

			r = $('<p id="tagcloud-'+tax+'" class="the-tagcloud">'+r+'</p>');
			$('a', r).click(function(){
				tagBox.flushTags( $(this).closest('.inside').children('.tagsdiv'), this);
				return false;
			});

			$('#'+id).after(r);
		});

		$(this).unbind().click(function(){
			$(this).siblings('.the-tagcloud').toggle();
			return false;
		});
		return false;
	});

	// ajax for term edit
	jQuery('#term_lang_choice').change(function() {
		var data = {
			action: 'term_lang_choice',
			lang: jQuery(this).attr('value'),
			term_id: jQuery("input[name='tag_ID']").attr('value'),
			taxonomy: jQuery("input[name='taxonomy']").attr('value')
		}

		jQuery.post(ajaxurl, data, function(response) {
			jQuery("#term-translations").html(response);
		});
	});

	// languages form
	jQuery('#lang_list').change(function() {
		value = jQuery(this).attr('value');
		slug = value.substr(0, 2);
		locale = value.substr(3, 5);
		jQuery('input[name="slug"]').val(slug);
		jQuery('input[name="description"]').val(locale);
		jQuery('input[name="name"]').val($("select option:selected").text());
	});

});

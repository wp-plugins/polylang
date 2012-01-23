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
				switch (this.what) {
					case 'translations': // translations fields
						jQuery('#post-translations').html(this.data);
						break;
					case 'taxonomy': // categories metabox for posts
						var tax = this.data;
						jQuery('#' + tax + 'checklist').html(this.supplemental.all);
						jQuery('#' + tax + 'checklist-pop').html(this.supplemental.populars);
						jQuery('#new' + tax + '_parent').replaceWith(this.supplemental.dropdown);
						jQuery('#' + tax + '-lang').val(jQuery('#post_lang_choice').attr('value')); // hidden field
						break;
					case 'pages': // parent dropdown list for pages
						jQuery('#parent_id').replaceWith(this.data);
						break;
					default:
						break;
				}
			});

			// modifies the language in the tag cloud	
			jQuery('.polylang-tagcloud-link').each(function() {
				var id = $(this).attr('id');
				pll_tagbox(id, 0); 			
			});

			// modifies the language in the tags suggestion input
			pll_suggest();
		});
	});

	// replace WP class by our own
	jQuery('a.tagcloud-link').addClass('polylang-tagcloud-link');
	jQuery('a.tagcloud-link').removeClass('tagcloud-link');

	// copy paste WP code and just call our pll_tagbox instead of tagbox.get
	jQuery('.polylang-tagcloud-link').click( function() {
		var id = $(this).attr('id');
		pll_tagbox(id, 1);		

		$(this).unbind().click(function(){
			$(this).siblings('.the-tagcloud').toggle();
			return false;
		});
		return false;
	});

	// now copy paste WP code
	// add the language in the $_POST variable
	// add an if else condition to allow modifying the tags outputed when switching the language
	function pll_tagbox(id, a) {
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

			if (a == 1)
				$('#'+id).after(r);
			else {
				v = $('.the-tagcloud').css('display');
				$('.the-tagcloud').replaceWith(r);
				$('.the-tagcloud').css('display', v);
			}
		});
	}

	// replace WP class by our own
	jQuery('input.newtag').addClass('polylang-newtag');
	jQuery('input.newtag').removeClass('newtag');
	pll_suggest();

	// now copy paste WP code
	// add the language in the $_GET variable
	// add the unbind function to allow calling the function when the language is modified
	function pll_suggest() {
		ajaxtag = $('div.ajaxtag');
		$('input.polylang-newtag', ajaxtag).unbind().blur(function() {
			if ( this.value == '' )
				$(this).parent().siblings('.taghint').css('visibility', '');
		}).focus(function(){
			$(this).parent().siblings('.taghint').css('visibility', 'hidden');
		}).keyup(function(e){
			if ( 13 == e.which ) {
				tagBox.flushTags( $(this).closest('.tagsdiv') );
				return false;
			}
		}).keypress(function(e){
			if ( 13 == e.which ) {
				e.preventDefault();
				return false;
			}
		}).each(function(){
			var lang = jQuery('#post_lang_choice').attr('value');
			var tax = $(this).closest('div.tagsdiv').attr('id');
			$(this).suggest( ajaxurl + '?action=polylang-ajax-tag-search&lang=' + lang + '&tax=' + tax, { delay: 500, minchars: 2, multiple: true, multipleSep: "," } );
		});
	}

	// ajax for term edit
	jQuery('#term_lang_choice').change(function() {
		var data = {
			action: 'term_lang_choice',
			lang: jQuery(this).attr('value'),
			term_id: jQuery("input[name='tag_ID']").attr('value'),
			taxonomy: jQuery("input[name='taxonomy']").attr('value')
		}

		jQuery.post(ajaxurl, data, function(response) {
			var res = wpAjax.parseAjaxResponse(response, 'ajax-response');
			$.each(res.responses, function() {
				switch (this.what) {
					case 'translations': // translations fields
						jQuery("#term-translations").html(this.data);
						break;
					case 'parent': // parent dropdown list for hierarchical taxonomies
						jQuery('#parent').replaceWith(this.data);
						break;
					default:
						break;
				}
			});
		});
	});

	// languages form
	// fills the fields based on dropdown list choice
	jQuery('#lang_list').change(function() {
		value = jQuery(this).attr('value').split('-');
		jQuery('input[name="slug"]').val(value[0]);
		jQuery('input[name="description"]').val(value[1]);
		jQuery('input[name="rtl"]').val([value[2]]);
		jQuery('input[name="name"]').val($("select option:selected").text());
	});

});

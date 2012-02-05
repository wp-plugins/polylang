var pll_tagBox;

// mainly copy paste of WP code
(function($){

pll_tagBox = {
	clean : function(tags) {
		return tags.replace(/\s*,\s*/g, ',').replace(/,+/g, ',').replace(/[,\s]+$/, '').replace(/^[,\s]+/, '');
	},

	parseTags : function(el) {
		var id = el.id, num = id.split('-check-num-')[1], taxbox = $(el).closest('.tagsdiv'), thetags = taxbox.find('.the-tags'), current_tags = thetags.val().split(','), new_tags = [];
		delete current_tags[num];

		$.each( current_tags, function(key, val) {
			val = $.trim(val);
			if ( val ) {
				new_tags.push(val);
			}
		});

		thetags.val( this.clean( new_tags.join(',') ) );

		this.quickClicks(taxbox);
		return false;
	},

	quickClicks : function(el) {
		var thetags = $('.the-tags', el),
			tagchecklist = $('.tagchecklist', el),
			id = $(el).attr('id'),
			current_tags, disabled;

		if ( !thetags.length )
			return;

		disabled = thetags.prop('disabled');

		current_tags = thetags.val().split(',');
		tagchecklist.empty();

		$.each( current_tags, function( key, val ) {
			var span, xbutton;

			val = $.trim( val );

			if ( ! val )
				return;

			// Create a new span, and ensure the text is properly escaped.
			span = $('<span />').text( val );

			// If tags editing isn't disabled, create the X button.
			if ( ! disabled ) {
				xbutton = $( '<a id="' + id + '-check-num-' + key + '" class="ntdelbutton">X</a>' );
				xbutton.click( function(){ pll_tagBox.parseTags(this); });
				span.prepend('&nbsp;').prepend( xbutton );
			}

			// Append the span to the tag list.
			tagchecklist.append( span );
		});
	},

	flushTags : function(el, a, f) {
		a = a || false;
		var text, tags = $('.the-tags', el), newtag = $('input.newtag', el), newtags;

		text = a ? $(a).text() : newtag.val();
		tagsval = tags.val();
		newtags = tagsval ? tagsval + ',' + text : text;

		newtags = this.clean( newtags );
		newtags = array_unique_noempty( newtags.split(',') ).join(',');
		tags.val(newtags);
		this.quickClicks(el);

		if ( !a )
			newtag.val('');
		if ( 'undefined' == typeof(f) )
			newtag.focus();

		return false;
	},

	get : function(id, a) {
		var tax = id.substr(id.indexOf('-')+1);

		// add the language in the $_POST variable
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
				pll_tagBox.flushTags( $(this).closest('.inside').children('.tagsdiv'), this);
				return false;
			});

			// add an if else condition to allow modifying the tags outputed when switching the language
			if (a == 1)
				$('#'+id).after(r);
			else {
				v = $('.the-tagcloud').css('display');
				$('.the-tagcloud').replaceWith(r);
				$('.the-tagcloud').css('display', v);
			}
		});
	},

	suggest : function() {
		ajaxtag = $('div.ajaxtag');
		// add the unbind function to allow calling the function when the language is modified
		$('input.newtag', ajaxtag).unbind().blur(function() {
			if ( this.value == '' )
	            $(this).parent().siblings('.taghint').css('visibility', '');
	    }).focus(function(){
			$(this).parent().siblings('.taghint').css('visibility', 'hidden');
		}).keyup(function(e){
			if ( 13 == e.which ) {
				pll_tagBox.flushTags( $(this).closest('.tagsdiv') );
				return false;
			}
		}).keypress(function(e){
			if ( 13 == e.which ) {
				e.preventDefault();
				return false;
			}
		}).each(function(){
			// add the language in the $_GET variable
			var lang = jQuery('#post_lang_choice').attr('value');
			var tax = $(this).closest('div.tagsdiv').attr('id');
			$(this).suggest( ajaxurl + '?action=polylang-ajax-tag-search&lang=' + lang + '&tax=' + tax, { delay: 500, minchars: 2, multiple: true, multipleSep: "," } );
		});
	},

	init : function() {
		var t = this, ajaxtag = $('div.ajaxtag');

	    $('.tagsdiv').each( function() {
	        pll_tagBox.quickClicks(this);
	    });

		$('input.tagadd', ajaxtag).click(function(){
			t.flushTags( $(this).closest('.tagsdiv') );
		});

		$('div.taghint', ajaxtag).click(function(){
			$(this).css('visibility', 'hidden').parent().siblings('.newtag').focus();
		});

		pll_tagBox.suggest();

	    // save tags on post save/publish
	    $('#post').submit(function(){
			$('div.tagsdiv').each( function() {
	        	pll_tagBox.flushTags(this, false, 1);
			});
		});

		// tag cloud
		$('a.tagcloud-link').click(function(){
			pll_tagBox.get( $(this).attr('id'), 1 );
			$(this).unbind().click(function(){
				$(this).siblings('.the-tagcloud').toggle();
				return false;
			});
			return false;
		});
	}
};

})(jQuery);

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
			jQuery('.tagcloud-link').each(function() {
				var id = $(this).attr('id');
				pll_tagBox.get(id, 0); 			
			});

			// modifies the language in the tags suggestion input
			pll_tagBox.suggest();
		});
	});

	// Tag box

	// replace WP class by our own to avoid using tagBox functions
	$('#side-sortables, #normal-sortables, #advanced-sortables').children('div.postbox').each(function(){
		if ( this.id.indexOf('tagsdiv-') === 0 ) {
			$(this).attr('id', 'pll-' + this.id);
		}
	});
				
	// copy paste WP code
	// replace tagsdiv by pll-tagsdiv and tagBox by pll_tagBox
	if ( $('#pll-tagsdiv-post_tag').length ) {
		pll_tagBox.init();
	} else {
		$('#side-sortables, #normal-sortables, #advanced-sortables').children('div.postbox').each(function(){
			if ( this.id.indexOf('pll-tagsdiv-') === 0 ) {
				pll_tagBox.init();
				return false;
			}
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

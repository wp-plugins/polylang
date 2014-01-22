// quick edit
if ('undefined' != typeof(inlineEditTax)) {
	(function($) {
		var $wp_inline_edit = inlineEditTax.edit;

		inlineEditTax.edit = function( id ) {
			$wp_inline_edit.apply( this, arguments );
			var $term_id = 0;
			if ( typeof( id ) == 'object' )
				$term_id = parseInt( this.getId( id ) );

			if ( $term_id > 0 ) {
				var $edit_row = $('#edit-' + $term_id);
				var $select = $edit_row.find(':input[name="inline_lang_choice"]');
				$select.find('option:selected').removeProp('selected');
				var lang = $('#lang_' + $term_id).html();
				$("input[name='old_lang']").val(lang);
				$select.find('option[value="'+lang+'"]').prop('selected', true);
			}
		}
	})(jQuery);
}

jQuery(document).ready(function($) {
	// ajax for changing a translation in edit-tags.php
	function change_translations() {
		var td = $(this).parent().siblings('.pll-edit-column');
		if (!td)
			return;

		td.children('a').hide();
		td.children('.spinner').show();

		var data = {
			action: 'term_translation_choice',
			lang: $('#term_lang_choice').attr('value'),
			term_id: $("input[name='tag_ID']").attr('value'),
			taxonomy: $("input[name='taxonomy']").attr('value'),
			post_type: typenow,
			value: $(this).attr('value')
		}

		$.post(ajaxurl, data , function(response) {
			var res = wpAjax.parseAjaxResponse(response, 'ajax-response');
			$.each(res.responses, function() {
				switch (this.what) {
					case 'link':
						td.children('.spinner').hide();
						td.html(this.data);
						break;
					default:
						break;
				}
			});
		});
	}

	$("select[name*='term_tr_lang']").change(change_translations);

	// ajax for changing the term's language
	$('#term_lang_choice').change(function() {
		var data = {
			action: 'term_lang_choice',
			lang: $(this).attr('value'),
			term_id: $("input[name='tag_ID']").attr('value'),
			taxonomy: $("input[name='taxonomy']").attr('value'),
			post_type: typenow
		}

		$.post(ajaxurl, data, function(response) {
			var res = wpAjax.parseAjaxResponse(response, 'ajax-response');
			$.each(res.responses, function() {
				switch (this.what) {
					case 'translations': // translations fields
						$("#term-translations").html(this.data);
						$("select[name*='term_tr_lang']").change(change_translations);
						break;
					case 'parent': // parent dropdown list for hierarchical taxonomies
						$('#parent').replaceWith(this.data);
						break;
					case 'tag_cloud': // popular items
						$('.tagcloud').replaceWith(this.data);
						break;
					default:
						break;
				}
			});
		});
	});

});

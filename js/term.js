jQuery(document).ready(function($) {
	// ajax for changing the term's language
	$('#term_lang_choice').change(function() {
		var data = {
			action: 'term_lang_choice',
			lang: $(this).attr('value'),
			term_id: $("input[name='tag_ID']").attr('value'),
			taxonomy: $("input[name='taxonomy']").attr('value')
		}

		$.post(ajaxurl, data, function(response) {
			var res = wpAjax.parseAjaxResponse(response, 'ajax-response');
			$.each(res.responses, function() {
				switch (this.what) {
					case 'translations': // translations fields
						$("#term-translations").html(this.data);
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

	// quick edit
	jQuery("a.editinline").live("click", function() {
		inlineEditTax.revert();
		var id = inlineEditTax.getId(this);
		var lang = $("input[name='lang_"+id+"']").attr('value');
		$("input[name='old_lang']").val(lang);
		$('#inline_lang_choice option:selected').removeAttr('selected');
		$('#inline_lang_choice option[value="'+lang+'"]').attr('selected', 'selected');  
	});
});

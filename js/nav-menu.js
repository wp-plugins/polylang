jQuery(document).ready(function($) {
	function pll_modify_lang() {
		$("input[value='"+pll_data.strings[4]+"'][type=text]").parent().parent().parent().each( function(){
			var item = $(this).attr('id').substring(19);
			// remove default fields we don't need
			$(this).children('.description-thin,.field-link-target,.field-description,.field-url').remove();
			h = $('<input>').attr({
					type: 'hidden',
					id: 'edit-menu-item-title-'+item,
					name: 'menu-item-title['+item+']',
					value: pll_data.strings[4]
			});
			$(this).append(h);
			ids = Array('hide_current','force_home','show_flags','show_names'); // reverse order

			// add the fields
			for(var i = 0; i < ids.length; i++) {
				p = $('<p>').attr('class', 'description');
				$(this).prepend(p);
				label = $('<label>').attr('for', 'menu-item-'+ids[i]+'-'+item).text(' '+pll_data.strings[i]);
				p.append(label);
				cb = $('<input>').attr({
					type: 'checkbox',
					id: 'edit-menu-item-'+ids[i]+'-'+item,
					name: 'menu-item-'+ids[i]+'['+item+']',
					value: 1
				});
				if (typeof(pll_data.val[item]) != 'undefined' && pll_data.val[item][ids[i]] == 1)
					cb.attr('checked', 'checked');
				label.prepend(cb);
			}
		});
	}

	$('#update-nav-menu').bind('click', function(e) {
		if ( e.target && e.target.className && -1 != e.target.className.indexOf('item-edit')) {
			pll_modify_lang();
		}
	});

});

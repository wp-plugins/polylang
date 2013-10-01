jQuery(document).ready(function($) {
	// languages form
	// fills the fields based on the language dropdown list choice
	$('#lang_list').change(function() {
		value = $(this).attr('value').split('-');
		selected = $("select option:selected").text().split(' - ');
		$('#lang_slug').val(value[0]);
		$('#lang_locale').val(value[1]);
		$('input[name="rtl"]').val([value[2]]);
		$('#lang_name').val(selected[0]);
	});
});

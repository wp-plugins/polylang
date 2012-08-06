jQuery(document).ready(function($) {
	// languages form
	// fills the fields based on the language dropdown list choice
	$('#lang_list').change(function() {
		value = $(this).attr('value').split('-');
		selected = $("select option:selected").text().split(' - ');
		$('input[name="slug"]').val(value[0]);
		$('input[name="description"]').val(value[1]);
		$('input[name="rtl"]').val([value[2]]);
		$('input[name="name"]').val(selected[0]);
	});
});

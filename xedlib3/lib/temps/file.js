$(function () {
	$('#'+fn_name+'_mass_options').hide();
	$('#'+fn_name+'_sel_folders').click(function () {
		$('.check_folder').attr('checked', $(this).attr('checked'));
		$('.check_folder').change();
	});

	$('#'+fn_name+'_sel_files').click(function () {
		$('.check_file').attr('checked', $(this).attr('checked'));
		$('.check_folder').change();
	});

	$('.check_folder,.check_file').click(checkChanged);
	$('.check_folder,.check_file').change(checkChanged);

	$('#a_toggle_upload').click(function () {
		$('#'+fn_name+'_upload').slideToggle(500);
		return false;
	});

	$('#a_toggle_create').click(function () {
		$('#'+fn_name+'_create').slideToggle(500);
		return false;
	});

	$('#a_toggle_rename').click(function () {
		$('#'+fn_name+'_rename').slideToggle(500);
		return false;
	});

	$('#a_toggle_edit').click(function () {
		$('#'+fn_name+'_edit').slideToggle(500);
		return false;
	});
});

function checkChanged()
{
	if ($('.check_folder:checked,.check_file:checked').length > 0)
		$('#'+fn_name+'_mass_options').show(500);
	else
		$('#'+fn_name+'_mass_options').hide(500);
}
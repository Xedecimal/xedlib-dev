<null>
$(function () {
	$('input:visible,textarea:visible,select:visible').each(function () {
		$(this).attr('readonly', true);
		$(this).addClass('disabled');
	});

	// Remove interactivity.
	$('input:button,input:submit,.front').hide();

	$('select').each(function () {
		$(this).replaceWith('<span class="input">'+$(this).find('option:selected').text()+'</span>');
	});

	$('input[type!=checkbox][type!=radio],textarea').each(function () {
		$(this).replaceWith('<span class="input">'+$(this).val()+'</span>');
	});
});
</null>
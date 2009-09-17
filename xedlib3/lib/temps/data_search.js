jQuery.fn.showHide = function(toggle) {
	if (toggle) this.show(500);
	else this.hide(500);
};

$(function () {
	$('.hider').each(function () {
		var targ = $(this).attr('id').match(/hider_(.*)/)[1];
		if (!$(this).attr('checked'))
			$('#hidden_'+targ).hide();
		$(this).click(function () {
			$('#hidden_'+targ).showHide($(this).attr('checked'));
		});
	})
});
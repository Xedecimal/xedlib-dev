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
	});
	
	$('.delResult').click(function () {
		if (confirm('Are you sure you wish to delete this entry?'))
		{
			id = $(this).attr('id').match(/del:(\d+)/)[1];
			$.post(root+me+'/'+name+'/delete/'+id, null, function (data) {
				$('#result\\:'+id).hide(500);
			}, 'json');
		}
		return false;
	});
});

jQuery.fn.repeat = function(target) {
	var repeat_ix = $(target).children().length
	obj = $(this).clone();
	obj.html(obj.html().replace(/:/g, repeat_ix++));
	$(target).append(obj);
	obj.show();
	obj.find('.cloneable').removeClass('cloneable');
	obj.find('.date').datepicker();
	return obj;
};

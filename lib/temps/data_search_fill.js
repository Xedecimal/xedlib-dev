<null>
json = {{json}}

record = 0

function fill(ix, element) {
	input = $(this);
	m = $(this).attr('name').match(/form\[([^\]]+)\]/);
	if (m)
	{
		// Checkboxes must be a sub-table
		if (input.attr('type') == 'checkbox')
		{
			col = m[1].match(/[^.]+\.([^\]]+)/)[1];
			$(json).each(function (ix, row) {
				if (row[col] == input.val()) input.attr('checked', 'checked');
			});
		}
		else if (input.attr('type') == 'radio')
		{
			if (json[record][m[1]] == input.val()) input.attr('checked', 'checked');
		}
		else
		{
			if (m[1].match(/\.([^.]+)/)) m[1] = m[1].match(/\.([^.]+)/)[1];
			input.val(json[record][m[1]]);
		}
	}
}

$(function () {
	// Populate the remains.
	$('input,textarea,select').each(fill);

	//Populate the repeatable entries.
	$('.repeatable').each(function () {
		rep = this;
		$(rep.children[0]).hide();
		col = rep.id.match(/repeat:(.*)/)[1];
		ids = {};
		$(json).each(function (ix, row) {
			if (!ids[row[col]] && row[col] != null) {
				obj = $(rep.children[0]).repeat($(rep));
				record = ix;
				obj.find('input,textarea,select').each(fill);
				ids[row[col]] = 1;
			}
		});
	});
});
</null>
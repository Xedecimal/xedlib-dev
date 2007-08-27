function gebi(id) { return document.getElementById(id); }

function toggle(id)
{
	obj = document.getElementById(id);
	if (obj.style.display == 'none') obj.style.display = '';
	else obj.style.display = 'none';
}

function toggleAny(ids, target)
{
	show = false;
	for (iy = 0; iy < ids.length; iy++)
	{
		id = ids[iy];
		ix = 0;
		while ((obj = document.getElementById(id+(ix++))))
		{
			if (obj.checked)
			{
				show = true;
				break;
			}
		}
		if (show) break;
	}
	if (show) document.getElementById(target).style.display = '';
	else document.getElementById(target).style.display = 'none';
}

function showAll(prefix, show)
{
	for (ix = 0; gebi(prefix+ix) != undefined; ix++)
		gebi(prefix+ix).style.display = show ? '' : 'none';
}

function sel_all(type, checked)
{
	var ix = 0;
	while ((cb = document.getElementById('sel_'+type+'_'+(ix++))))
	{
		cb.checked = checked;
	}
}
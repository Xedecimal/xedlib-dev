function gebi(id) { return document.getElementById(id); }

function toggle(id)
{
	obj = gebi(id);
	if (obj.style.display == 'none') obj.style.display = '';
	else obj.style.display = 'none';
}

function show(id, display)
{
	obj = gebi(id);
	if (display) obj.style.display = '';
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

function getAjax(method, url, block, target, args)
{
	var xmlHttp;
	try { xmlHttp=new XMLHttpRequest(); }
	catch (e) //May be IE based
	{
		try { xmlHttp=new ActiveXObject("Msxml2.XMLHTTP"); }
		catch (e)
		{
			try { xmlHttp=new ActiveXObject("Microsoft.XMLHTTP"); }
			catch (e)
			{
				alert("Your browser does not support AJAX!");
				return false;
			}
		}
	}

	xmlHttp.onreadystatechange=function()
	{
		if (xmlHttp.readyState == 4)
		{
			target(xmlHttp.responseText, args);
		}
	}

	xmlHttp.open(method, url);
	xmlHttp.send(null);
}

function putAjax(method, url, block, target)
{
	getAjax(method, url, block, ajOutput, target);
}

function ajOutput(data, target)
{
	gebi(target).innerHTML = data;
}

function appendTag(parent, tag, args)
{
	try
	{
		var add = '<'+tag;
		for (i in args) add += ' '+i+'="'+args[i]+'"';
		add += ' />';
		tag = document.createElement(add);
	}
	catch (err)
	{
		tag = document.createElement(tag);
		for (i in args) tag.setAttribute(i, args[i]);
	}
	parent.appendChild(tag);
	return tag;
}

function findElementsByRegex(parent, target)
{
	ret = Array();
	if (parent.nodeType != 9 && parent.nodeType != 1) return ret;
	for (ix in parent.childNodes)
		ret = ret.concat(findElementsByRegex(parent.childNodes[ix], target));
	if (parent.id != null && parent.id.match(target))
		ret = ret.concat(parent);
	return ret;
}

function selAllChecks(target, on)
{
	objs = findElementsByRegex(document, target);
	for (ix in objs)
	{
		objs[ix].checked = on;
	}
}
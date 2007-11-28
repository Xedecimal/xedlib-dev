<null>
{{header}}

<hr size="1" />

<form action="{{me}}" method="post">

<script type="text/javascript" src="{{relpath}}/js/helper.js"></script>

<if check="$this->vars['mass_available']">

<script type="text/javascript">
var __sels;

function docmanSelAll(type)
{
	if (__sels == undefined) __sels = {'files': false, 'dirs': false }
	__sels[type] = !__sels[type];
	sel_all(type, __sels[type]);
	toggleAny(['sel_files_','sel_dirs_'],'{{name}}_mass_options');
}
</script>

<p>Select the checkbox of the file(s) or folder(s) that you would like to delete
or move.</p>

<input type="hidden" name="editor" value="{{name}}" />
<input type="hidden" name="cf" value="{{cf}}" />

</if>

<if check="strlen($this->vars['folders']) > 0">
<box title="Folders">
{{folders}}
</box>
</if>

<if check="strlen($this->vars['files']) > 0">
<box title="Files">
{{files_neck}}
{{files}}
</box>
</if>

<if check="$GLOBALS['user']['usr_access'] == 3">
<p><a href="#" onclick="toggle('{{name}}_options'); return false;">
	View Options for this File or Folder</a></p>
</if>

{{options}}
</form>
</null>
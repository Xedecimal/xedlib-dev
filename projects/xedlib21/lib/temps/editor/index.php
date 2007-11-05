<null>
<box title="Search">
<form action="{{me}}">
<input type="hidden" name="editor" value="{{name}}" />
Search: <input type="text" name="{{name}}_q" />
<input type="submit" value="Search" />
</form>
</box>

<if check="isset($this->vars['table'])">
<box title="{{table_title}}">
{{table}}
</box>
</if>

{{forms}}
</null>
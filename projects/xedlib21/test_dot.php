<?php

require_once('h_main.php');
require_once('lib/a_code.php');

$ix = 0;

function GetShape($t)
{
	if ($t == T_FUNCTION) return "Msquare";
	if ($t == T_CLASS) return "Mdiamond";
	if ($t == T_VARIABLE) return "diamond";
	return "record";
}

function WalkMember($m)
{
	global $ix, $out;

	$id = @hexdec(crc32($m->file.$m->line));
	$pid = @hexdec(crc32($m->parent->file.$m->parent->line));

	echo @"{$m->file}:{$m->line} ({$m->name}) -> {$m->file}:{$m->line} ({$m->parent->name})<br />\n";

	//Formatting

	if (isset($m->type))
	{
		$out .= "\t{$id} [label=\"{$m->name}\",".
			" shape=\"".GetShape($m->type)."\"]\r\n";
	}

	//Linkage
	if (isset($m->parent))
		$out .= "\t{$id}->{$pid}\r\n";
	else if (isset($m->doc->package))
		$out .= "\t{$id}->{$m->doc->package}\r\n";

	if (!empty($m->members))
		foreach ($m->members as $c)
			WalkMember($c);
}

$c = new CodeReader();
$res = $c->Parse('lib/a_editor.php');

$out = "digraph a {\r\n";

WalkMember($res['data']);

$out .= "}\r\n";

$fp = fopen('input.txt', 'w');
fwrite($fp, $out);
fclose($fp);

passthru("c:\util\dot.exe -odiagram.svg -Tsvg < input.txt");

?>
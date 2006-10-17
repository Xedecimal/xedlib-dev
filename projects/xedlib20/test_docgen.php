<?php

require_once('lib/a_docgen.php');

//Helpers

function CreateDoc($type)
{
	$doc = new DOMDocument();
	$doc->appendChild($doc->createProcessingInstruction(
		'xml-stylesheet', "href=\"../{$type}.xsl\" type=\"text/xsl\""));
	return $doc;
}

function GetTypeName($type)
{
	switch ($type)
	{
		case T_FUNCTION: return 'function';
		case T_CLASS: return 'class';
		case T_VARIABLE: return 'variable';
		default: return 'unknown';
	}
}

function GetDocumentElement($doc, $data)
{
	$elDoc = $doc->createElement('doc');
	preg_match_all('#/*([^*/]+)#', $data, $matches);
	$ret = null;
	foreach ($matches[0] as $line)
	{
		if (preg_match('#@param ([^ ]+) ([^ ]+) (.*)#', $line, $tag_match))
		{
			$elTag = $doc->createElement('param', $tag_match[3]);
			$elTag->setAttribute('type', $tag_match[1]);
			$elTag->setAttribute('name', $tag_match[2]);
			$elDoc->appendChild($elTag);
		}
		else if (preg_match('#@([^ ]+) (.*)#', $line, $tag_match))
		{
			$elTag = $doc->createElement('tag', $tag_match[2]);
			$elTag->setAttribute('type', $tag_match[1]);
			$elDoc->appendChild($elTag);
		}
		else $ret .= $line;
	}
	$elDoc->appendChild($doc->createElement('text', $ret));
	return $elDoc;
}

//Guts

function OutputMember($doc, $parent, $item)
{
	$e = $doc->createElement(GetTypeName($item->type));
	$e->setAttribute('name', $item->name);
	if (isset($item->doc)) $e->appendChild(GetDocumentElement($doc, $item->doc));
	else echo "Item not documented {$item->name}<br/>\n";
	if (!empty($item->members)) foreach ($item->members as $member)
		OutputMember($doc, $e, $member);
	$parent->appendChild($e);
}

function OutputDetail($item, $target)
{
	$type = GetTypeName($item->type);

	$doc = CreateDoc($type);

	$root = $doc->createElement('root');
	$root->setAttribute('name', $item->name);
	if (isset($item->doc)) $root->appendChild(GetDocumentElement($doc, $item->doc));

	//Arguments and methods
	if (!empty($item->members)) foreach ($item->members as $member)
	{
		OutputMember($doc, $root, $member);
	}
	$doc->appendChild($root);

	$doc->save($target."/{$type}_{$item->name}.xml");
}

function OutputTOC($toc, $parent, $item, $target)
{
	$type = GetTypeName($item->type);

	$element = $toc->createElement($type);
	$element->setAttribute('name', $item->name);

	//Create xml document for this object.
	OutputDetail($item, $target);
	$parent->appendChild($element);
}

function OutputFiles($mask, $target)
{
	$d = new DocGen();

	$files = glob($mask);
	$out = null;
	foreach ($files as $file)
	{
		$new = $d->Parse($file);
		if (!isset($out)) $out = $new;
		else if (isset($new)) $out->members = array_merge($out->members, $new->members);
	}

	if (!empty($out))
	{
		if (!file_exists($target)) mkdir($target);

		$doc = CreateDoc('index');

		$root = $doc->createElement('root');
		foreach ($out->members as $item) OutputTOC($doc, $root, $item, $target);
		$doc->appendChild($root);
		
		$doc->save($target.'/index.xml');
	}
}

OutputFiles('lib/*.php', 'doc/output');

?>
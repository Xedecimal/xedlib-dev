<?php

require_once('lib/a_docgen.php');

function OutputDetail($item, $target)
{
	$type = 'unknown';
	switch ($item->type)
	{
		case T_FUNCTION: $type = 'function'; break;
		case T_CLASS: $type = 'class'; break;
		case T_VARIABLE: $type = 'variable'; break;
	}

	$doc = new DOMDocument();
	$root = $doc->createElement('root');
	$doc->appendChild($doc->createProcessingInstruction(
	   'xml-stylesheet', "href=\"../{$type}.xsl\" type=\"text/xsl\""));
	$doc->appendChild($root);

	if (isset($item->doc))
	{
		$elDoc = $doc->createElement('doc');
		preg_match_all('#/*([^*/]+)#', $item->doc, $matches);
		$ret = null;
		foreach ($matches[0] as $line)
		{
			if (preg_match('#@([^ ]+) (.*)#', $line, $tag_match))
			{
				$elTag = $doc->createElement('tag');
				$elTag->setAttribute('type', $tag_match[1]);
				$elTag->appendChild($doc->createElement('text', $tag_match[2]));
				$elDoc->appendChild($elTag);
			}
			else $ret .= $line;
		}
		$elDoc->appendChild($doc->createElement('text', $ret));
		$root->appendChild($elDoc);
	}

	$doc->save($target."/{$type}_{$item->name}.xml");
}

/**
 * Enter description here...
 *
 * @param DOMDocument $doc
 * @param DOMElement $parent
 * @param DocObject $item
 * @param String $target
 */
function OutputTOC($toc, $parent, $item, $target)
{
	$type = 'unnamed';
	switch ($item->type)
	{
		case T_FUNCTION: $type = 'function'; break;
		case T_CLASS: $type = 'class'; break;
	}
	$element = $toc->createElement($type);
	$element->appendChild($toc->createElement('name', $item->name));
	$element->appendChild($toc->createElement('type', $type));
	
	//Create xml document for this object.
	OutputDetail($item, $target);
	if (!empty($item->members)) foreach ($item->members as $member)
	{
		OutputDetail($member, $target);
	}
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
		$doc = new DOMDocument();
		$root = $doc->createElement('root');
		$doc->appendChild($doc->createProcessingInstruction(
		   'xml-stylesheet', 'href="../index.xsl" type="text/xsl"'));
		$doc->appendChild($root);

		foreach ($out->members as $item)
		{
			OutputTOC($doc, $root, $item, $target);
		}
		
		if (!file_exists($target)) mkdir($target);
		$doc->save($target.'/index.xml');
	}
}

$files = glob('lib/*.php');
foreach ($files as $file)
{
	OutputFiles('lib/*.php', 'doc/output');
}

?>
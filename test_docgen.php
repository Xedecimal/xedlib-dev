<?php

require_once('lib/h_utility.php');
require_once('lib/classes/code_reader.php');

class DocGeneratorXML
{
	function CreateDoc($type)
	{
		$doc = new DOMDocument();
		$doc->appendChild($doc->createProcessingInstruction(
			'xml-stylesheet', 'href="../'.$type.'.xsl" type="text/xsl"'));
		return $doc;
	}

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $doc
	 * @param CodeObject $item
	 * @return unknown
	 */
	function GetDocumentElement($doc, $item)
	{
		$elDoc = $doc->createElement('doc');

		if (isset($item->doc->example))
		{
			if (!file_exists($item->doc->example) ||
				!is_file($item->doc->example)) $data = $item->doc->example;
			else $data = highlight_file($match[1], true);
			$elTag = $doc->createElement('tag');
			$elTag->appendChild($doc->createCDATASection($data));
			$elTag->setAttribute('type', 'example');
			$elDoc->appendChild($elTag);
		}
		else if (!empty($item->doc->params))
		{
			foreach ($item->doc->params as $n => $p)
			{
				$elTag = $doc->createElement('tag', $p['desc']);
				$elTag->setAttribute('type', 'param');
				$elTag->setAttribute('datatype', $p['type']);
				$elTag->setAttribute('name', $n);
				$elDoc->appendChild($elTag);
			}
		}
		else if (isset($item->doc->return))
		{
			$elTag = $doc->createElement('tag', $item->doc->return['desc']);
			$elTag->setAttribute('type', 'return');
			$elTag->setAttribute('datatype', $item->doc->return['type']);
			$elDoc->appendChild($elTag);
		}

		//$elTag = $doc->createElement('tag', isset($match[2]) ? $match[2] : null);
		//$elTag->setAttribute('type');
		//$elDoc->appendChild($elTag);

		if (!empty($item->doc->body))
			$elDoc->appendChild($doc->createCDATASection($item->doc->body));
		return $elDoc;
	}

	function AddNode($doc, $parent, $name, $value)
	{
		$ret = $doc->createElement($name, $value);
		$parent->appendChild($ret);
		return $ret;
	}

	function OutputMember($doc, $parent, $item)
	{
		$e = $doc->createElement(GetTypeName($item->type));
		$e->setAttribute('name', $item->name);

		if (isset($item->modifier))
			$e->setAttribute('modifier', GetTypeName($item->modifier));
		if (isset($item->doc))
		{
			$e->appendChild($this->GetDocumentElement($doc, $item));
		}
		else
		{
			if ($item->parent->type != T_FUNCTION)
			{
				$path = null;
				$t = $item;
				while (isset($t->parent))
				{
					$path = '(<b>'.GetTypeName($t->parent->type).'</b>)'
					.$t->parent->name.'->'
					.$path;
					$t = $t->parent;
				}
				echo "Item not documented {$path}{$item->name}".
					" ({$item->file}:{$item->line})\n";
			}
		}
		if (!empty($item->members)) foreach ($item->members as $member)
			$this->OutputMember($doc, $e, $member);
		$parent->appendChild($e);
	}

	function OutputDetail($item, $target)
	{
		$type = GetTypeName($item->type);

		$doc = $this->CreateDoc($type);

		$root = $doc->createElement('root');
		$root->setAttribute('name', $item->name);
		if (isset($item->doc))
			$root->appendChild($this->GetDocumentElement($doc, $item));

		//Arguments and methods
		if (!empty($item->members))
		foreach ($item->members as $member)
			$this->OutputMember($doc, $root, $member);
		$doc->appendChild($root);

		$doc->save("{$target}/{$type}_{$item->name}.xml");
	}

	function OutputTOC($toc, $parent, $item, $target)
	{
		$type = GetTypeName($item->type);

		$element = $toc->createElement($type);
		$element->setAttribute('name', $item->name);
		if (isset($item->value))
			$element->setAttribute('value', $item->value);
		if (isset($item->extends))
			$element->setAttribute('extends', $item->extends);
		if (isset($item->filename))
			$element->setAttribute('filename', $item->filename);
		if (isset($item->line))
			$element->setAttribute('line', $item->line);
		if (isset($item->doc))
			$element->appendChild($this->GetDocumentElement($toc, $item));

		//Create xml document for this object.
		if ($item->type != T_DEFINE) $this->OutputDetail($item, $target);
		$parent->appendChild($element);
	}

	function OutputFiles($source, $include, $exclude, $target)
	{
		$d = new CodeReader();

		$rdi = new RecursiveDirectoryIterator($source);
		$rii = new RecursiveIteratorIterator($rdi);
		$ri = new RegexIterator($rii, $include, RecursiveRegexIterator::GET_MATCH);

		$data = null;
		foreach ($ri as $file)
		{
			if (preg_match($exclude, $file[0])) continue;
			$new = $d->Parse($file[0]);
			if (!isset($data)) $data = $new['data'];
			else if (!empty($new['data']->members))
			{
				$data->members += $new['data']->members;
				$data->file = $file;
			}
		}

		ksort($data->members);

		if (!empty($data))
		{
			if (!file_exists($target)) mkdir($target);

			$doc = $this->CreateDoc('index');
			$root = $doc->createElement('root');
			foreach ($data->members as $item)
				$this->OutputTOC($doc, $root, $item, $target);
			$doc->appendChild($root);
			$doc->save($target.'/index.xml');
		}
	}
}

$GLOBALS['debug'] = true;

echo '<pre>';
$stime = microtime(true);
$d = new DocGeneratorXML();
$d->OutputFiles('lib', '/^.+\.php$/i', '#lib\\\\3rd.*#i', 'doc/output');
echo 'Done in '.(microtime(true)-$stime)." seconds.<br/>\n";
echo '</pre>';

?>
<?php

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/a_code.php');

function GetTypeName($type)
{
	switch ($type)
	{
		case T_FUNCTION: return 'function';
		case T_CLASS: return 'class';
		case T_VARIABLE: return 'variable';
		case T_DEFINE: return 'define';
		case T_PUBLIC: return 'public';
		case T_PRIVATE: return 'private';
		case T_PROTECTED: return 'protected';
		default: return 'unknown';
	}
}

class DocGeneratorXML
{
	function CreateDoc($type)
	{
		$doc = new DOMDocument();
		$doc->appendChild($doc->createProcessingInstruction(
			'xml-stylesheet', "href=\"../{$type}.xsl\" type=\"text/xsl\""));
		return $doc;
	}

	function GetDocumentElement($doc, $data)
	{
		$elDoc = $doc->createElement('doc');
		preg_match_all('#/*([^*/]+)#', $data, $matches);
		$ret = null;
		foreach ($matches[0] as $line)
		{
			$line = chop($line);
			
			//Tag
			if (preg_match('#@(.+)#', $line, $match))
			{
				$tag = $match[1];
				if (preg_match('#example (.+)#', $tag, $match))
				{
					$data = highlight_file($match[1], true);
					$elTag = $doc->createElement('tag');
					$elTag->appendChild($doc->createCDATASection($data));
					$elTag->setAttribute('type', 'example');
					$elDoc->appendChild($elTag);
				}
				else if (preg_match('#param ([^ ]+) ([^ ]+)(.*)#', $tag, $match))
				{
					$elTag = $doc->createElement('tag', $match[3]);
					$elTag->setAttribute('type', 'param');
					$elTag->setAttribute('datatype', $match[1]);
					$elTag->setAttribute('name', $match[2]);
					$elDoc->appendChild($elTag);
				}
				else if (preg_match('#return ([^ ]+)(.*)#', $line, $match))
				{
					$elTag = $doc->createElement('tag', isset($match[3]) ? $match[3] : null);
					$elTag->setAttribute('type', 'return');
					$elTag->setAttribute('datatype', $match[1]);
					$elTag->setAttribute('text', $match[2]);
					$elDoc->appendChild($elTag);
				}
				else
				{
					$elTag = $doc->createElement('tag', isset($match[2]) ? $match[2] : null);
					$elTag->setAttribute('type', $tag);
					$elDoc->appendChild($elTag);
				}
			}
			else $ret .= $line;
		}
		$elDoc->appendChild($doc->createCDATASection($ret));
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
			$e->appendChild($this->GetDocumentElement($doc, $item->doc));
		else
		{
			if ($item->parent->type != T_FUNCTION)
			{
				$path = null;
				$t = $item;
				while (isset($t->parent))
				{
					$path = '(<b>'.GetTypeName($t->parent->type).'</b>)'
					.$t->parent->name.' -> '
					.$path;
					$t = $t->parent;
				}
				echo "Item not documented {$path} {$item->name}<br/>\n";
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
		if (isset($item->doc)) $root->appendChild($this->GetDocumentElement($doc, $item->doc));
	
		//Arguments and methods
		if (!empty($item->members)) foreach ($item->members as $member)
		{
			$this->OutputMember($doc, $root, $member);
		}
		$doc->appendChild($root);
	
		$doc->save("{$target}/{$type}_{$item->name}.xml");
	}
	
	function OutputTOC($toc, $parent, $item, $target)
	{
		$type = GetTypeName($item->type);
	
		$element = $toc->createElement($type);
		$element->setAttribute('name', $item->name);
		if (isset($item->value)) $element->setAttribute('value', $item->value);
		if (isset($item->extends)) $element->setAttribute('extends', $item->extends);
		if (isset($item->filename)) $element->setAttribute('filename', $item->filename);
		if (isset($item->doc))
			$element->appendChild($this->GetDocumentElement($toc, $item->doc));

		//Create xml document for this object.
		if ($item->type != T_DEFINE) $this->OutputDetail($item, $target);
		$parent->appendChild($element);
	}
	
	function OutputFiles($mask, $target)
	{
		$d = new CodeReader();

		$files = glob($mask);
		$data = null;
		foreach ($files as $file)
		{
			$new = $d->Parse($file);
			if (!isset($data)) $data = $new;
			else if (isset($new))
				$data->members = array_merge($data->members, $new->members);
		}
		ksort($data->members);
	
		if (!empty($data))
		{
			if (!file_exists($target)) mkdir($target);

			$doc = $this->CreateDoc('index');
			$root = $doc->createElement('root');
			foreach ($data->members as $item) $this->OutputTOC($doc, $root, $item, $target);
			$doc->appendChild($root);
			$doc->save($target.'/index.xml');
		}
	}
}

echo '<pre>';
$d = new DocGeneratorXML();
$d->OutputFiles('lib/*.php', 'doc/output');
echo '</pre>';

?>
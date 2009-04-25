<?php

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/a_code.php');

function BuildNamesRecurse(&$t, $m)
{
	if ($m->type == T_FUNCTION)
	{
		if (@$m->parent->type == T_CLASS)
			$t[$m->name][0] = 'class_'.$m->parent->name.'.xml#'.$m->name;
		else
			$t[$m->name][0] = $m->name;
		if (!empty($m->members))
		foreach ($m->members as $m2) BuildNamesRecurse($t[$m->name], $m2);
	}
	if ($m->type == T_CLASS)
	{
		$t[$m->name][0] = "class_{$m->name}.xml";
		foreach ($m->members as $m2) BuildNamesRecurse($t[$m->name], $m2);
	}
	if ($m->type == T_VARIABLE)
	{
		if (@$m->parent->type == T_CLASS)
			$t[$m->parent->name][$m->name] = 'class_'.$m->parent->name.'.xml#'.$m->name;
		else $t[$m->name] = 'variable_'.$m->name;
	}
}

function GetNames(&$data)
{
	$t = array();
	foreach ($data->members as $m) BuildNamesRecurse($t, $m);
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
			$elTag = $doc->createElement('tag'/*, $item->doc->example*/);
			$elTag->appendChild($doc->createCDATASection($item->doc->example));
			$elTag->setAttribute('type', 'example');
			$elDoc->appendChild($elTag);
		}

		if (!empty($item->doc->params))
		foreach ($item->doc->params as $name => $args)
		{
			$elTag = $doc->createElement('tag');
			$elTag->setAttribute('type', 'param');
			$elTag->setAttribute('datatype', $args['type']);
			$elTag->setAttribute('desc', $args['desc']);
			$elTag->setAttribute('name', $name);
			$elDoc->appendChild($elTag);
		}

		if (!empty($item->doc->return))
		{
			$elTag = $doc->createElement('tag', $item->doc->return['desc']);
			$elTag->setAttribute('type', 'return');
			$elTag->setAttribute('datatype', $item->doc->return['type']);
			$elTag->setAttribute('desc', $item->doc->return['desc']);
			$elDoc->appendChild($elTag);
		}

		if (!empty($item->doc->package))
		{
			$elTag = $doc->createElement('tag');
			$elTag->setAttribute('type', 'package');
			$elTag->setAttribute('desc', $item->doc->package);
			$elDoc->appendChild($elTag);
		}
		if (!empty($item->doc->access))
		{
			$elTag = $doc->createElement('tag');
			$elTag->setAttribute('type', 'modifier');
			$elTag->setAttribute('value', $item->doc->access);
			$elDoc->appendChild($elTag);
		}

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
		if (isset($item->doc->type))
			$e->setAttribute('type', $item->doc->type);
		if (isset($item->doc->package))
			$e->setAttribute('package', $item->doc->package);
		if (isset($item->doc))
		{
			$e->appendChild($this->GetDocumentElement($doc, $item));
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

	function OutputTOC($toc, $root, &$packs, $item, $target)
	{
		$p = $item->doc->package;
		if (!isset($packs[$p]))
		{
			$packs[$p] = $toc->createElement('package');
			$packs[$p]->setAttribute('name', $p);
			$root->appendChild($packs[$p]);
		}

		$element = $toc->createElement(GetTypeName($item->type));
		$element->setAttribute('name', $item->name);
		if (isset($item->value))
			$element->setAttribute('value', $item->value);
		if (isset($item->extends))
			$element->setAttribute('extends', $item->extends);
		if (isset($item->file))
			$element->setAttribute('file', $item->file);
		if (isset($item->line))
			$element->setAttribute('line', $item->line);
		$packs[$p]->appendChild($element);

		//Create xml document for this object.
		if ($item->type != T_DEFINE) $this->OutputDetail($item, $target);
		//$parent->appendChild($element);
	}

	function OutputFiles($mask, $target)
	{
		$d = new CodeReader();

		$files = glob($mask);
		$data = null;
		$names = array();

		foreach ($files as $file)
		{
			echo "Parsing file: {$file}\n";
			$new = $d->Parse($file);
			if (!empty($new['data']->misc['longest']['name']))
			{
				echo "Most documented item is {$new['data']->misc['longest']['name']}"
				." weighing in at {$new['data']->misc['longest']['len']} characters.\n";
			}
			if (!empty($new['data']->misc['shortest']['name']))
			{
				echo "Least documented item is {$new['data']->misc['shortest']['name']}"
				." weighing in at {$new['data']->misc['shortest']['len']} characters.\n";
			}
			if (!isset($data)) $data = $new['data'];

			if (!empty($new['data']->members))
				$data->members = array_merge($data->members,
					$new['data']->members);
			$names = array_merge($names, $new['names']);
			$data->file = $file;
		}

		$names = array_keys($names);

		GetNames($data);

		$d->Validate($data, $names);

		if (!empty($d->todos))
		{
			$doc = $this->CreateDoc('todo');
			$el = $doc->createElement('todos');
			foreach ($d->todos as $todo)
			{
				$elt = $doc->createElement('todo', $todo[2]);
				$elt->setAttribute('file', $todo[0]);
				$elt->setAttribute('line', $todo[1]);
				$el->appendChild($elt);
			}
			$doc->appendChild($el);
			$doc->save($target.'/todos.xml');
		}

		ksort($data->members);

		if (!empty($data))
		{
			if (!file_exists($target)) mkdir($target);

			$doc = $this->CreateDoc('index');
			$root = $doc->createElement('root');
			$packs = array();
			foreach ($data->members as $item)
				$this->OutputTOC($doc, $root, $packs, $item, $target);

			$doc->appendChild($root);
			$doc->save($target.'/index.xml');
		}
	}
}

$GLOBALS['debug'] = true;

echo '<pre>';
$stime = microtime(true);

$switch = true;

if ($switch)
{
	$d = new DocGeneratorXML();
	$d->OutputFiles('lib/*.php', 'doc/output');
	//$d->OutputFiles('../../tools/jpgraph-2.1.2/src/*.php', 'doc/output2');
}
else
{
	$cr = new CodeReader();
	$cr->Refector = true;
	echo "Parsing...\n";
	$ret = $cr->Parse('../../tools/jpgraph-2.1.2/src/jpgraph.php');
	echo "Validating...\n";
	$cr->Validate($ret['data'], $ret['names']);
}
echo "Done in ".(microtime(true) - $stime)." seconds.<br/>\n";
echo '</pre>';

?>
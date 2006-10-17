<?php

require_once('h_utility.php');
HandleErrors();

define('GET_NONE', 0);
define('GET_NAME', 1);

class DocGen
{
	private $show_tree = false;

	function Parse($filename)
	{
		$parsing = true;
		$tree = array();
		$tokens = token_get_all(file_get_contents($filename));

		$class = null;
		$doc = null;
		$getting = GET_NONE;
		$ret = null;

		foreach ($tokens as $tok)
		{
			if (!is_array($tok))
			{
				switch ($tok)
				{
					case "'":
					case '"':
						$parsing = !$parsing;
						break;
				}
				if ($parsing)
				{
					switch ($tok)
					{
						case '{':
							if ($this->show_tree) echo str_repeat("\t", count($tree))."{$tok}\n";
							array_push($tree, $current);

							//We're done with function arguments.
							if (isset($current) && $current->type == T_FUNCTION)
								$current = null;
							break;
						case '}':
							$current = array_pop($tree);
							if ($this->show_tree) echo str_repeat("\t", count($tree))."{$tok}\n";
							break;
					}
				}
			}
			else if ($parsing)
			{
				if ($tok[0] == T_CURLY_OPEN)
				{
					if ($this->show_tree) echo str_repeat("\t", count($tree))."{\n";
					array_push($tree, $current);
				}
				
				if ($tok[0] == T_FUNCTION)
				{
					if ($this->show_tree) echo str_repeat("\t", count($tree))."function ";
					$current = new DocObject($tok[0]);
					$current->doc = $doc;
					$doc = null;
					$getting = GET_NAME;
				}

				if ($tok[0] == T_CLASS)
				{
					if ($this->show_tree) echo "class ";
					$getting = GET_NAME;
					$current = new DocObject($tok[0]);
					$current->doc = $doc;
					$doc = null;
				}
				
				if ($tok[0] == T_STRING)
				{
					if ($getting == GET_NAME)
					{
						$current->name = $tok[1];
						if ($current->type == T_FUNCTION)
						{
							if (count($tree) == 1)
							{
								$parent = array_get($tree);
								$parent->members[$tok[1]] = $current;
							}
							else $ret->members[$tok[1]] = $current;
						}
						if ($current->type == T_CLASS)
						{
							$ret->members[$tok[1]] = $current;
						}
						$getting = GET_NONE;
						if ($this->show_tree) echo $tok[1]."\n";
					}
				}
				
				if ($tok[0] == T_DOC_COMMENT)
				{
					$doc .= $tok[1];
				}
				
				if ($tok[0] == T_VARIABLE)
				{
					$d = new DocObject(T_VARIABLE);
					$d->name = $tok[1];
					$d->doc = $doc;
					$doc = null;

					//Argument
					if (isset($current) && $current->type == T_FUNCTION)
						$current->members[$tok[1]] = $d;

					//Member
					else if (count($tree) == 1 && $tree[count($tree)-1]->type == T_CLASS)
						$current->members[$tok[1]] = $d;

					//Global
					else if (empty($tree))
						$ret->members[$tok[1]] = $d;
				}
			}
		}
		
		return $ret;
	}
}

class DocObject
{
	public $type;
	public $name;
	public $members;
	public $parent;
	
	function DocObject($type)
	{
		$this->type = $type;
	}
}

?>
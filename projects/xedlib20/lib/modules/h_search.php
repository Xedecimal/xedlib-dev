<?php

/**
 * @package Search
 */

/**
 * Enter description here...
 *
 * @package Search
 */
class Search
{
	var $searchItems;
	var $searcResults;
	var $siteFiles;
	
	function getResults()
	{
		$results = array();
		for($z = 0; $z < count($this->searchResults); $z++)
		{
			array_push($results, $this->searchResults[$z]);
		}
		
		return $results;
	}

	function getFiles()
	{
		return $this->scandir_recursive(GetVar('DOCUMENT_ROOT'));
	}

	function scandir_recursive($path)
	{
		if (!is_dir($path)) return 0;
		$list = array();
		$directory = opendir($path);
		while ($file = readdir($directory))
	    {
			$f = "{$path}/{$file}";
			$f = preg_replace('/(\/){2,}/','/', $f);
			if (is_file($f))
			{
				if (substr($f, -5) != '.html') continue;
				$list[] = $f;
			}
			if (is_dir($f))
				$list = array_merge($list ,$this->scandir_recursive($f));                     
	     }
		 closedir($directory); 
		 return $list ;
	}

	function Get($query)
	{
		$this->searchItems = $query;
		$this->siteFiles = $this->getFiles();
		varinfo($this->siteFiles);
		$this->searchResults = array();

		foreach ($this->siteFiles as $file)
		{
			$contents = file_get_contents($file);
			$matches = preg_match('"'.$query.'"', $contents);
			if ($matches > 0)
			{
				array_push($this->searchResults, $file."<br />");
			}
		}
	}
}

?>
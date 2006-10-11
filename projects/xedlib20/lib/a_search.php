<?php

require_once('h_utility.php');

/**
 * @package Search
 */

/**
 * Enter description here...
 *
 * @package Search
 * 
 * Lots of problems and solutions here...
 * 
 * 1. Cannot search source of php files! We'll end up resulting in showing
 * passwords, exploits, sensitive information, anything.
 * 
 * 2. Need inclusion and exception rules. Skip administration pages, include
 * recursively.
 * 
 * 3. Need to index files for quicker hits, was thinking something along the
 * lines of like a .index file in every folder with a large array of keyword
 * hits and counts for each sub-folder, if there are hits, it will walk to
 * that subfolder to continue the search, returning each hit it finds.
 * 
 * 4. Optimization of indexing. We do not want the first search to index the
 * entire site, this has to be throttled.
 */
class Search
{
	public $search_items;
	public $searc_results;
	public $site_files;
	public $throttle;
	public $step; //!< For reaching throttle.
	
	public $include;
	public $exclude;

	function Search()
	{
		$this->include = array('*.html','*.php');
		$this->exclude = array('admin.php', 'admin/');
		$this->throttle = 5;
		$this->step = 0;
	}

	function Query($query)
	{
		$this->searchItems = $query;
		$this->siteFiles = $this->GetFiles();
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

	function GetResults()
	{
		$results = array();
		for($ix = 0; $ix < count($this->searchResults); $ix++)
		{
			array_push($results, $this->searchResults[$ix]);
		}
		return $results;
	}

	function GetFiles()
	{
		return $this->RecurseQuery(GetVar('DOCUMENT_ROOT'));
	}

	function RecurseQuery($path)
	{
		if (!is_dir($path)) return null;
		
		$index_file = "{$path}/.xedlib_index";
		if (file_exists($index_file)) $index = file_get_contents($index_file);
		else $index = array();

		if ($this->step < $this->throttle)
				$this->ImproveIndex($path, $index);

		$list = array();

		foreach ($this->include as $inc)
		{
			$files = glob("{$path}/{$inc}");
			foreach ($files as $file)
		    {
	    		if ($file[0] == '.') continue;

				$f = $file;
				$f = preg_replace('/(\/){2,}/','/', $f);
				if (is_dir($f)) $list = array_merge($list ,$this->RecurseQuery($f));                     
				else
				{
					if (substr($f, -5) != '.html') continue;
					$list[] = $f;
				}
			}
		}
		return $list;
	}

	/**
	* Improves the index file for a folder, adding new indexed items
	* and recursively updating the indexes.
	*/
	function ImproveIndex($path, $index)
	{
		foreach ($this->include as $inc)
		{
			$files = glob("{$path}/{$inc}");
			
			foreach ($files as $file)
			{
				if (is_dir($file)) continue;
				$pinfo = pathinfo($file);

				if (!isset($index[$file]))
				{
					echo "Adding item {$file} to index...<br />\n";
					$index[$file] = array(
						'info' => array('mtime' => filemtime($file)),
						'keywords' => $this->GetKeywords($file)
					);

					if (++$this->step >= $this->throttle)
					{
						echo "Hit throttle, terminating...<br />\n";
						$fp = fopen("{$path}/.xedlib_index", 'w+');
						fwrite($fp, serialize($index));
						fclose($fp);
						return;
					}
				}
			}
		}
		$dirs = glob($path.'/*', GLOB_ONLYDIR);
		foreach ($dirs as $dir)
		{
			$nindexfile = $dir.'/.xedlib_index';
			if (file_exists($nindexfile)) $nindex = unserialize(file_get_contents($nindexfile));
			else $nindex = array();
			$this->ImproveIndex($dir, $nindex);
		}
	}
	
	function GetKeywords($file)
	{
		$contents = file_get_contents($file);
		$matches = null;

		//Strip HTML comments...
		$stripped = preg_replace('/<!--.*-->/s', '', $contents);
		
		//Strip HTML tags...
		$stripped = preg_replace('/(<[^>]*>)/', '', $stripped);
		
		preg_match_all('/[0-9a-zA-Z()]+/', $stripped, $matches);

		$ret = array();
		foreach ($matches[0] as $match)
		{
			if (strlen($match) < 4) continue;
			if (!isset($ret[strtolower($match)])) $ret[strtolower($match)] = 1;
			else $ret[strtolower($match)]++;
		}
		return $ret;
	}
}

?>
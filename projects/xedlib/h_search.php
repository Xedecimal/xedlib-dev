<?php

/**
 * @return mixed
 * @param searchItems array
 * @param searchResults array
 * @param siteFiles array
 */
class Search
{
	var $searchItems;
	var $searcResults;
	var $siteFiles;
	
	function Search()
	{
		$this->searchItems = array();
		$this->searchResults = array();
		$this->siteFiles = array();
	}
	
	function prepare($keyWords)
	{
		$this->searchItems = $keyWords;
		$this->siteFiles = $this->getFiles();
		
		
	}
	
	function performSearch()
	{
		for($x = 0; $x < count($this->siteFiles); $x++)
		{
			$contents = file_get_contents($this->siteFiles[$x]);
			for($y = 0; $y < count($this->searchItems); $y++)
			{
				
				$matches = preg_match('"'.$this->searchItems[$y].'"',$contents);
				if($matches > 0)
				{
					array_push($this->searchResults, $this->siteFiles[$x]."<br />");
				}
			}
			
		}
		
	}
	
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
		return $this->scandir_recursive($_SERVER['DOCUMENT_ROOT']);
	}
	
	function scandir_recursive($path)
	{
		if (!is_dir($path)) return 0;
		$list=array();
		$directory = @opendir("$path"); // @-no error display
		while ($file= @readdir($directory))
	    {
	         if (($file<>".")&&($file<>"..")&&($file<>"images")&&($file<>"js"))
	         { 
	              $f=$path."/".$file;
				  $f=preg_replace('/(\/){2,}/','/',$f); //replace double slashes
				  if(is_file($f)) $list[]=$f;           
				  if(is_dir($f))
				  $list = array_merge($list ,$this->scandir_recursive($f));  //RECURSIVE CALL                     
	         }   
	     }
		 @closedir($directory); 
		 return $list ;
	}
	
	
}



?>
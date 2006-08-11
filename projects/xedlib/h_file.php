<?php

class FileManager
{
	public $name;
	public $filters;

	function FileManager($name, $filters = array())
	{
		$this->name = $name;
		$this->filters = $filters;
	}

	function Get($cf)
	{
		global $fi;
		$fi = new FileInfo($cf);
		$ret = null;

		if (file_exists($cf))
		{
			if (is_dir($cf))
			{
				$page_body .= GetDirectory($fi);
			}
			else
			{
				$info = dirname($cf) . "/." . basename($cf);
				$time = gmdate("M j Y H:i:s ", filemtime($cf));
				$size = filesize($cf);
				$page_body .= "Size: $size bytes<br/>\n";
				$page_body .= "Last Modified: $time<br/>\n";

				if ($cu['admin'])
				{
					//Additional information.
					$page_body .= "<form action=\"$me\" method=\"post\"/>\n";
					$page_body .= "<input type=\"hidden\" name=\"ca\" value=\"update_info\"/>";
					$page_body .= "<input type=\"hidden\" name=\"cf\" value=\"$cf\"/>";
					$page_body .= "Keywords (separated by spaces):<br/><textarea name=\"ck\" rows=\"5\" cols=\"50\">";
					if (file_exists($info))
					{
						$page_body .= file_get_contents($info);
					}
					$page_body .= "</textarea>\n";
					$page_body .= "<input type=\"submit\" value=\"Update\"/>\n";
					$page_body .= "</form>\n";
				}
			}
		}
		
		$ret .= $this->GetSetType($fi);
		$ret .= $this->GetCreateDirectory();
		$ret .= $this->GetUpload();
		return $ret;
	}

	function GetSetType($fi)
	{
		global $me, $cf;
		if (!isset($fi)) return null;
		$options = $fi->GetTypeOptions();
		$ret = <<<EOF
<form action="{$me}" method="post">
	<input type="hidden" name="ca" value="settype"/>
	<input type="hidden" name="cf" value="{$cf}"/>
	Set this directory type to:
	<select name="type">
EOF;
		foreach ($this->filters as $filter)
		{
			foreach ($filters as $key => $val)
			{
				if ($fi->filtername == $key) $sel = ' selected="selected"';
				else $sel = null;
				$ret .= "<option value=\"{$key}\"{$sel}>{$val}</option>\n";
			}
		}
		$ret .= <<<EOF
	</select>
	<input type="submit" value="Update"/>
</form>
EOF;
	}

	function GetCreateDirectory()
	{
		global $me, $cf;
		return <<<EOF
<form action="{$me}" method="post">
	<input type="hidden" name="ca" value="createdir"/>
	<input type="hidden" name="cf" value="{$cf}"/>
	<input type="text" name="name"/>
	<input type="submit" value="Create Directory Here"/>
</form>
EOF;
	}

	function GetUpload()
	{
		global $me, $cf;
		return <<<EOF
<form action="{$me}" method="post" enctype="multipart/form-data">
	<input type="hidden" name="ca" value="upload"/>
	<input type="hidden" name="cf" value="{$cf}"/>
	<input type="file" name="cu"/>
	<input type="submit" value="Upload"/>
</form>
EOF;
	}
}

class FileInfo
{
	public $path;
	public $dir;
	public $dirs;
	public $files;
	public $bitpos;
	public $filter;
	public $filtername;
	public $owned;

	function FileInfo($source)
	{
		global $user_root;
		$this->owned = strlen(strstr($source, $user_root)) > 0;
		$this->bitpos = 0;
		$this->path = $source;
		$this->dirs = array();
		$this->files = array();

		if (is_file($source)) { }
		else
		{
			if (file_exists("$source.filter"))
			{
				$name = file_get_contents("$source.filter");
				if (file_exists("filter_$name.php"))
				{
					$this->filtername = $name;
					require_once("filter_$name.php");
					$objname = "Filter$name";
					$this->filter = new $objname();
				}
				else $this->filter = new DirFilter();
			}
			else $this->filter = new DirFilter();
			if (!file_exists($source)) return;
			$dir = opendir($source);
			while (($file = readdir($dir)))
			{
				if ($file[0] == '.') continue;
				if (is_dir($source.$file)) $this->AddDir($source.$file);
				else
				{
					$filename = basename($file);
					$name = substr($filename, 0, strpos($filename, '.'));
					$ext = substr($filename, strpos($filename, '.')+1);
					$this->AddFile($this->path, $name, $ext);
				}
			}
			asort($this->files);
			asort($this->dirs);
		}
	}

	function AddDir($path)
	{
		if (file_exists("$path/.filter"))
		{
			$name = file_get_contents("$path/.filter");
			require_once("filter_$name.php");
			$objname = "Filter{$name}";
			$filter = new $objname();
		}
		else $filter = new DirFilter($path);
		$info = $filter->GetDirInfo($path);
		if ($info != null) $this->dirs[] = $info;
	}

	function AddFile($path, $name, $ext)
	{
		$finfo = $this->filter->GetInfo($path, $name, $ext);
		if ($finfo != null) $this->files[] = $finfo;
	}

	function GetBit($off)
	{
		$items = explode('/', $this->path);
		if ($off < count($items)) return $items[$off];
		return null;
		/*$newpos = strpos($this->path, '/', $this->bitpos+1);
		if ($newpos > 0)
		{
			$ret = substr($this->path, $this->bitpos, $newpos-$this->bitpos);
			$this->bitpos = $newpos+1;
			return $ret;
		}*/
	}

	function GetTypeOptions()
	{
		$ret = null;

		return $ret;
	}
}

/**
 * The generic file handler.
 *
 */
class DirFilter
{
	function GetName() { return "Normal"; }
	function GetInfo($path, $file, $ext)
	{
		$ret = array();
		$ret['name'] = "$file.$ext";
		$ret['path'] = "$path$file.$ext";
		switch ($ext)
		{
			case 'jpg':
			case 'png':
			case 'gif':
				$ret['icon'] = '<img src="images/img.gif" alt="Image" />';
				break;
			default:
				$ret['icon'] = '<img src="images/file.gif" alt="File" />';
		}
		return $ret;
	}

	function GetDirInfo($path)
	{
		$ret['name'] = basename($path);
		$ret['path'] = $path;
		$ret['icon'] = '<img src="images/folder.gif" alt="Folder" />';
		return $ret;
	}

	function Upload($file)
	{
		global $cf;
		move_uploaded_file($file['tmp_name'], "{$cf}{$file['name']}");
	}

	/**
	 * Delete a file or folder.
	 *
	 * @param FileInfo $fi
	 */
	function Delete($fi)
	{
		if (is_file($fi->path))
		{
			$finfo = $fi->path . '/.' . $info['basename'];
			if (file_exists($finfo)) unlink($finfo);
		}
		else DelTree($fi->path);;
	}
}

?>
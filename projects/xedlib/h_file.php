<?php

require_once('h_display.php');

$cf = GetVar('cf');

class FileManager
{
	public $name;
	public $filters;
	public $root;
	public $icons;
	public $cf;
	public $DefaultFilter;
	public $sortable;
	public $FullPath;
	public $DefaultInfo;

	/**
	 * Associated login manager.
	 *
	 * @var LoginManager
	 */
	public $lm;

	/**
	 * Enter description here...
	 *
	 * @param string $name Name of this instance.
	 * @param string $root Highlest folder level allowed.
	 * @param LoginManager $lm Login manager.
	 * @param array $filters Directory filters allowed.
	 * @return FileManager
	 */
	function FileManager($name, $root, $lm = null, $filters = array(), $DefaultFilter = 'Default')
	{
		global $cf;
		$this->name = $name;
		$this->filters = $filters;
		$this->DefaultFilter = $DefaultFilter;
		$this->root = $root;
		$this->FullPath = $root.$cf;

		$this->FI = new FileInfo($root.$cf);

		//Append trailing slash.
		if (substr($this->root, strlen($this->root)-1) != '/') $this->root .= '/';
		if (!file_exists($root)) Error('FileManager: Root directory does not exist.');
		$this->lm = $lm;
	}

	function Prepare($ca, $cf)
	{
		//Security
		if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
		$fullcf = $this->root.$cf;

		//Actions
		if ($ca == "upload")
		{
			$fi = new FileInfo($newcf, $this->DefaultFilter);
			$file = GetVar("cu");
			$fi->filter->Upload($file, $newcf);
		}
		else if ($ca == "update_info")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			$info = new FileInfo($newcf, $this->DefaultFilter);
			$info->info = GetVar("info");
			$fp = fopen($info->dir.'/.'.$info->filename, "w+");
			fwrite($fp, serialize($info->info));
			fclose($fp);
		}
		else if ($ca == "delete")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			$fi = new FileInfo($newcf.GetVar('ci'), $this->DefaultFilter);
			$fi->filter->Delete($fi);
		}
		else if ($ca == "createdir")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			mkdir($newcf.GetVar("name"));
		}
	}

	/**
	* Return the display.
	*
	* @param string $cf Current folder.
	* @return string Output.
	*/
	function Get($target, $cf)
	{
		if (!file_exists($this->root.$cf))
			return "FileManager::Get(): File doesn't exist ({$cf}).<br/>\n";

		$fi = new FileInfo($this->FullPath, $this->DefaultFilter);
		if (!empty($this->filters)) $fi->DefaultFilter = $this->filters[0];
		$ret = '';

		$ret .= $this->GetHeader($target, $cf);
		if (is_dir($this->FullPath)) $ret .= $this->GetDirectory($target, $cf, $fi);
		else
		{
			$info = dirname($this->root.$cf).'/.'.basename($this->root.$cf);
			$time = gmdate("M j Y H:i:s ", filemtime($this->root.$cf));
			$size = filesize($this->root.$cf);
			$ret .= "Size: $size bytes<br/>\n";
			$ret .= "Last Modified: $time<br/>\n";

			if ($this->lm != null && $this->lm->access == ACCESS_ADMIN)
			{
				//Additional information.
				$ret .= "<form action=\"$target\" method=\"post\"/>\n";
				$ret .= "<input type=\"hidden\" name=\"editor\" value=\"{$this->name}\" />";
				$ret .= "<input type=\"hidden\" name=\"ca\" value=\"update_info\"/>";
				$ret .= "<input type=\"hidden\" name=\"cf\" value=\"$cf\"/>";
				$ret .= 'Title: <input type="text" name="ck"';
				if (file_exists($info)) $ret .= ' value="'.htmlspecialchars($fi->info).'"';
				$ret .= " />\n";
				$ret .= "<input type=\"submit\" value=\"Update\"/>\n";
				$ret .= "</form>\n";
			}
		}

		if ($this->lm != null && $this->lm->access == ACCESS_ADMIN)
		{
			$ret .= $this->GetSetType($target, $cf, $fi);
			$ret .= $this->GetCreateDirectory($target, $cf);
			$ret .= $this->GetUpload();
		}
		return $ret;
	}

	function GetSetType($target, $cf, $fi)
	{
		if (!isset($fi)) return null;
		if (empty($this->filters)) return null;
		if (count($this->filters) < 2) return null;
		$ret = <<<EOF
<form action="{$target}" method="post">
	<input type="hidden" name="editor" value="{$this->name}" />
	<input type="hidden" name="ca" value="update_info"/>
	<input type="hidden" name="cf" value="{$cf}"/>
	Set this directory type to:
	<select name="info[type]">
EOF;
		foreach ($this->filters as $key => $val)
		{
			if ($fi->filtername == $key) $sel = ' selected="selected"';
			else $sel = null;
			$ret .= "<option value=\"{$val}\"{$sel}>{$val}</option>\n";
		}
		$ret .= <<<EOF
	</select>
	<input type="submit" value="Update"/>
</form>
EOF;
		return $ret;
	}

	function GetCreateDirectory($target, $cf)
	{
		return <<<EOF
<form action="{$target}" method="post">
	<input type="hidden" name="editor" value="{$this->name}" />
	<input type="hidden" name="ca" value="createdir" />
	<input type="hidden" name="cf" value="{$cf}" />
	<input type="text" name="name" />
	<input type="submit" value="Create Directory Here" />
</form>
EOF;
	}

	function GetUpload()
	{
		global $me, $cf;
		return <<<EOF
<form action="{$me}" method="post" enctype="multipart/form-data">
	<input type="hidden" name="editor" value="{$this->name}" />
	<input type="hidden" name="ca" value="upload"/>
	<input type="hidden" name="cf" value="{$cf}"/>
	<input type="file" name="cu"/>
	<input type="submit" value="Upload"/>
</form>
EOF;
	}

	function GetDirectory($target, $cf, $fi)
	{
		$dp = opendir($this->FullPath);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$newfi = new FileInfo($this->FullPath.$file, $this->DefaultFilter);
			if (is_dir($this->FullPath.$file)) $dirs[] = $newfi;
			else $files[] = $newfi;
		}
		$ret = '';
		if (!empty($dirs))
		{
			$ret = "<p>Folders<br/>\n";
			foreach($dirs as $dir) $ret .= $this->GetFile($target, $dir);
			$ret .= "</p>";
		}

		if (!empty($files))
		{
			$ret .= "<p>Files<br/>\n";
			foreach($files as $file) $ret .= $this->GetFile($target, $file);
			$ret .= "</p>";
		}
		return $ret;
	}

	/**
	 * Get a single file.
	 *
	 * @param string $target
	 * @param FileInfo $file
	 */
	function GetFile($target, $file)
	{
		global $cf;
		$ret = null;
		if (isset($file->thumb)) $ret .= "{$file->thumb}\n";
		else
		{
			if (isset($this->icons[$file->type])) $icon = $this->icons[$file->type];
			if (isset($icon))
				$ret .= '<img src="'.$icon.'" alt="'.$file->type.'" /> ';
		}
		$ret .= "[<a href=\""
			.MakeURI($target, array(
				'editor' => $this->name,
				'ca' => "delete",
				'cf' => $cf,
				'ci' => $file->filename))
			."\" onClick=\"return confirm('Are you sure you wish to delete this file?')\">X</a>]\n";
		$ret .= "<a href=\"$target?editor={$this->name}&amp;cf=".urlencode($cf.$file->filename)."\">{$file->filename}</a><br/>\n";
		return $ret;
	}

	function GetHeader($target, $source)
	{
		$ret = null;
		if (is_dir($this->root.$source))
		{
			$ret .= "<form action=\"$target\" method=\"post\">\n";
			$ret .= "<input type=\"hidden\" name=\"editor\" value=\"$this->name\" />\n";
			$ret .= "<input type=\"hidden\" name=\"ca\" value=\"search\"/>\n";
			$ret .= "<input type=\"hidden\" name=\"cf\" value=\"$source\"/>\n";
			$ret .= "Search in: /\n";
		}
		else $ret .= '<p>';
		$ret .= $this->GetPath($target, $source);

		if (is_file($this->root.$source))
			$ret .= " [<a href=\"{$this->root}{$source}\" target=\"_blank\">Download</a>]</p>";
		if (is_dir($this->root.$source))
		{
			$ret .= " for <input type=\"text\" name=\"cq\"/>\n";
			$ret .= "<input type=\"submit\" value=\"Search\"/>\n";
			$ret .= "</form>\n";
		}
		else $ret .= "<br/><br/>\n";
		return $ret;
	}

	function GetPath($target, $path)
	{
		$pos = $start = 0;
		$ret = null;
		if (strlen($path) > 0) while (($pos = strpos($path, '/', $pos+1)) > 0)
		{
			$ret .= "<a href=\"".
				MakeURI($target, array(
					'editor' => $this->name,
					'cf' => substr($path, 0, $pos+1)
				))
				."\">".substr($path, $start, $pos-$start)."</a> /\n";
			$start = $pos+1;
		}
		if ($pos < strlen($path))
		{
			$ret .= "<a href=\"".
				MakeURI($target, array(
					'editor' => $this->name,
					'cf' => substr($path, 0)
				))
				."\">" . substr($path, $start) . "</a>";
		}
		return "<a href=\"".
			MakeURI($target, array(
				'editor' => $this->name,
				'cf' => ''
			))
			."\"> Home</a> / ".$ret;
	}
}

/**
 * Collects information for a SINGLE file OR folder.
 *
 */
class FileInfo
{
	public $path;
	public $dir;
	public $bitpos;
	public $filename;

	/**
	* Directory Filter.
	*
	* @var DirFilter
	*/
	public $Filter;
	public $filtername;
	public $owned;
	public $info;
	public $type;
	public $thumb;

	function FileInfo($source, $DefaultFilter = 'Default')
	{
		global $user_root;
		if (!file_exists($source))
			Error("FileInfo: Directory does not exist. ({$source})<br/>\n");
		$this->owned = strlen(strstr($source, $user_root)) > 0;
		$this->bitpos = 0;
		$this->path = $source;
		$this->dir = dirname($source);
		$this->filename = basename($source);

		$finfo = $this->dir.'/.'.$this->filename;
		if (file_exists($finfo))
			$this->info = unserialize(file_get_contents($finfo));

			$this->GetFilter($source, $DefaultFilter);
		if (is_dir($source)) $this->type = 'folder';
		$this->Filter->GetInfo($this);
	}

	function GetFilter($path, $default = 'Default')
	{
		if (isset($this->info['type']))
		{
			$name = $this->info['type'];
			if (class_exists("Filter{$name}"))
			{
				$objname = "Filter$name";
				return $this->Filter = new $objname();
			}
		}
		if (isset($default))
		{
			$name = $default;
			if (class_exists("Filter$name"))
			{
				$objname = "Filter$name";
				return $this->Filter = new $objname();
			}
		}
		return $this->Filter = new DirFilter();
	}

	function GetBit($off)
	{
		$items = explode('/', $this->path);
		if ($off < count($items)) return $items[$off];
		return null;
	}
}

/**
* The generic file handler.
*
*/
class FilterDefault
{
	function GetName() { return "Normal"; }
	function GetInfo($fi) { return $fi; }

	function Upload($file, $target)
	{
		move_uploaded_file($file['tmp_name'], "{$target}{$file['name']}");
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
			$info = pathinfo($fi->path);
			$finfo = "{$info['dirname']}/.{$info['basename']}";
			if (file_exists($finfo)) unlink($finfo);
			unlink($fi->path);
		}
		else DelTree($fi->path);
	}
}

class FilterGallery extends FilterDefault
{
	function GetName() { return "Gallery"; }

	function GetInfo($fi)
	{
		$ret = parent::GetInfo($fi);
		if (substr($fi->filename, 0, 2) == 't_') return null;
		$ret->info['Width'] = 'w';
		$ret->info['Height'] = 'h';
		varinfo($fi->dir."/t_".$fi->filename);
		if (file_exists($fi->dir."t_".$fi->filename))
			$ret['thumb'] = "<img src=\"{$path}t_{$file}.{$ext}\"/>";
		return $ret;
	}

	/**
	* @param FileInfo $fi Target to be deleted.
	*/
	function Delete($fi)
	{
		parent::Delete($fi);
		$pi = pathinfo($fi->path);
		$thumb = $pi['dirname'].'/t_'.$pi['basename'];
		if (file_exists($thumb)) unlink($thumb);
	}

	function Upload($file, $target)
	{
		$filename = substr(basename($file['name']), 0, strpos(basename($file['name']), '.'));

		switch ($file['type'])
		{
			case "image/jpeg":
			case "image/pjpeg":
				$img = imagecreatefromjpeg($file['tmp_name']);
			break;
			case "image/x-png":
			case "image/png":
				$img = imagecreatefrompng($file['tmp_name']);
			break;
			case "image/gif":
				$img = imagecreatefromgif($file['tmp_name']);
			break;
			default:
				die("Unknown image type: {$file['type']}<br>\n");
			break;
		}
		$destimage = "{$target}/{$filename}.jpg";
		$destthumb = "{$target}/t_{$filename}.jpg";
		imagejpeg($img, $destimage);
		$img = $this->ResizeImg($img, 200, 200);
		imagejpeg($img, $destthumb);
	}

	function ResizeImg($image, $newWidth, $newHeight)
	{
		$srcWidth  = ImageSX( $image );
		$srcHeight = ImageSY( $image );
		if ($srcWidth < $newWidth && $srcHeight < $newHeight) return $image;

		if ($srcWidth < $srcHeight)
		{
			$destWidth  = $newWidth * $srcWidth/$srcHeight;
			$destHeight = $newHeight;
		}
		else
		{
			$destWidth  = $newWidth;
			$destHeight = $newHeight * $srcHeight/$srcWidth;
		}
		$destImage = imagecreatetruecolor( $destWidth, $destHeight);
		ImageCopyResampled($destImage, $image, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
		return $destImage;
	}
}

?>
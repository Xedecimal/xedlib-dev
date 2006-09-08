<?php

require_once('h_display.php');

class FileManager
{
	public $name;
	public $filters;
	public $root;
	public $icons;
	public $cf;
	public $DefaultFilter;
	public $sortable;
	public $files;

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
		$this->name = $name;
		$this->filters = $filters;
		$this->DefaultFilter = $DefaultFilter;
		$this->root = $root;

		//Append trailing slash.
		if (!file_exists($root)) Error('FileManager: Root directory does not exist.');
		if (substr($this->root, -1) != '/') $this->root .= '/';
		$this->cf = GetVar('cf');
		if (is_dir($this->root.$this->cf)
		&& strlen($this->cf) > 0
		&& substr($this->cf, -1) != '/')
			$this->cf .= '/';
		$this->lm = $lm;
	}

	function Prepare($action)
	{
		$this->files = $this->GetDirectory();

		//Security
		if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;

		//Actions
		if ($action == "upload")
		{
			$fi = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
			$file = GetVar("cu");
			$fi->Filter->Upload($file, $this->root.$this->cf);
			$this->files = $this->GetDirectory();
		}
		else if ($action == "update_info")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			$info = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
			$info->info = array_merge($info->info, GetVar("info"));
			$fp = fopen($info->dir.'/.'.$info->filename, "w+");
			fwrite($fp, serialize($info->info));
			fclose($fp);
		}
		else if ($action == "delete")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			$index = GetVar('ci');
			$types = GetVar('type');
			$fi = $this->files[$types][$index];
			$fi->Filter->Delete($fi);
			array_splice($this->files[$types], $index, 1);
		}
		else if ($action == "createdir")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			mkdir($this->root.$this->cf.GetVar("name"));
		}
		else if ($action == 'swap')
		{
			$index = GetVar('index');
			$types = GetVar('type');
			$cd = GetVar('cd');
			if ($cd == 'up')
			{
				$this->files[$types][$index]->info['index'] -= 1;
				$this->files[$types][$index]->SaveInfo();
				$this->files[$types][$index-1]->info['index'] += 1;
				$this->files[$types][$index-1]->SaveInfo();

				$move = $this->files[$types][$index];
				$this->files[$types][$index] = $this->files[$types][$index-1];
				$this->files[$types][$index-1] = $move;
			}
			else
			{
				$this->files[$types][$index]->info['index'] += 1;
				$this->files[$types][$index]->SaveInfo();
				$this->files[$types][$index+1]->info['index'] -= 1;
				$this->files[$types][$index+1]->SaveInfo();

				$move = $this->files[$types][$index];
				$this->files[$types][$index] = $this->files[$types][$index+1];
				$this->files[$types][$index+1] = $move;
			}
		}
	}

	/**
	* Return the display.
	*
	* @param string $cf Current folder.
	* @return string Output.
	*/
	function Get($target)
	{
		if (!file_exists($this->root.$this->cf))
			return "FileManager::Get(): File doesn't exist ({$this->root}{$this->cf}).<br/>\n";

		$fi = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
		if (!empty($this->filters)) $fi->DefaultFilter = $this->filters[0];
		$ret = '';

		$ret .= $this->GetHeader($target, $fi);
		if (is_dir($this->root.$this->cf))
		{
			if (!empty($this->files['dirs']))
			{
				$ret .= "<p>Folders</p>\n";
				foreach($this->files['dirs'] as $dir)
					$ret .= $this->GetFile($target, $dir);
			}
	
			if (!empty($this->files['files']))
			{
				$ret .= "<p>Files</p>\n";
				foreach($this->files['files'] as $file)
					$ret .= $this->GetFile($target, $file);
			}
		}
		else
		{
			$info = dirname($this->root.$this->cf).'/.'.basename($this->root.$this->cf);
			$time = gmdate("M j Y H:i:s ", filemtime($this->root.$this->cf));
			$size = filesize($this->root.$this->cf);
			$ret .= "Size: $size bytes<br/>\n";
			$ret .= "Last Modified: $time<br/>\n";

			if ($this->lm != null && $this->lm->access == ACCESS_ADMIN)
			{
				//Additional information.
				$ret .= "<form action=\"$target\" method=\"post\">\n";
				$ret .= "<input type=\"hidden\" name=\"editor\" value=\"{$this->name}\" />";
				$ret .= "<input type=\"hidden\" name=\"ca\" value=\"update_info\"/>";
				$ret .= "<input type=\"hidden\" name=\"cf\" value=\"{$this->cf}\"/>";
				$ret .= 'Title: <input type="text" name="info[title]"';
				if (file_exists($info)) $ret .= ' value="'.htmlspecialchars($fi->info['title']).'"';
				$ret .= " />\n";
				$ret .= "<input type=\"submit\" value=\"Update\"/>\n";
				$ret .= "</form>\n";
			}
		}

		if ($this->lm != null && $this->lm->access == ACCESS_ADMIN)
		{
			$ret .= $this->GetSetType($target, $fi);
			$ret .= $this->GetCreateDirectory($target, $this->cf);
			$ret .= $this->GetUpload();
		}
		return $ret;
	}

	function GetSetType($target, $fi)
	{
		if (empty($this->filters)) return null;
		if (count($this->filters) < 2) return null;
		$fname = $fi->Filter->GetName();
		$ret = <<<EOF
<form action="{$target}" method="post">
	<input type="hidden" name="editor" value="{$this->name}" />
	<input type="hidden" name="ca" value="update_info"/>
	<input type="hidden" name="cf" value="{$this->cf}"/>
	Set this directory type to:
	<select name="info[type]">
EOF;
		foreach ($this->filters as $key => $val)
		{
			if ($fname == $val) $sel = ' selected="selected"';
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

	function GetCreateDirectory($target)
	{
		return <<<EOF
<form action="{$target}" method="post">
	<input type="hidden" name="editor" value="{$this->name}" />
	<input type="hidden" name="ca" value="createdir" />
	<input type="hidden" name="cf" value="{$this->cf}" />
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
	<input type="hidden" name="cf" value="{$this->cf}"/>
	<input type="file" name="cu"/>
	<input type="submit" value="Upload"/>
</form>
EOF;
	}

	function GetDirectory()
	{
		$dp = opendir($this->root.$this->cf);
		$ret['files'] = array();
		$ret['dirs'] = array();
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$newfi = new FileInfo($this->root.$this->cf.'/'.$file, $this->DefaultFilter);
			if (!$newfi->show) continue;
			if (is_dir($this->root.$this->cf.'/'.$file))
			{
				$put = isset($newfi->info['index']) ? $newfi->info['index'] : 0;
				array_splice($ret['dirs'], $put, 0, array($newfi));
			}
			else
			{
				$put = isset($newfi->info['index']) ? $newfi->info['index'] : 0;
				array_splice($ret['files'], $put, 0, array($newfi));
			}
		}
		foreach ($ret['files'] as $ix => $file) $file->info['index'] = $ix;
		foreach ($ret['dirs'] as $ix => $dir) $dir->info['index'] = $ix;
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
		$ret = null;
		if (!$file->show) return;
		$types = $file->type ? 'dirs' : 'files';
		if (isset($file->info['thumb'])) $ret .= "<p>{$file->info['thumb']}\n";
		else
		{
			if (isset($this->icons[$file->type])) $icon = $this->icons[$file->type];
			if (isset($icon))
				$ret .= '<p><img src="'.$icon.'" alt="'.$file->type.'" /> ';
		}
		$ret .= "<br/><a href=\"$target?editor={$this->name}&amp;cf=".urlencode($this->cf.$file->filename)."\">{$file->filename}</a>\n";

		$common = array(
			'cf' => $this->cf,
			'editor' => $this->name,
			'type' => $types,
		);

		$uriDel = MakeURI($target, array_merge($common, array(
			'ca' => "delete",
			'ci' => $file->info['index']
		)));

		$uriUp = MakeURI($target, array_merge($common, array(
			'ca' => 'swap',
			'cd' => 'up',
			'index' => $file->info['index']
		)));

		$uriDown = MakeURI($target, array_merge($common, array(
			'ca' => 'swap',
			'cd' => 'down',
			'index' => $file->info['index']
		)));

		if ($file->info['index'] > 0)
			$ret .= " - <a href=\"$uriUp\"><img src=\"xedlib/up.png\" border=\"0\" alt=\"Move Up\" title=\"Move Up\" /></a> ";
		if ($file->info['index'] < count($this->files[$types])-1)
			$ret .= " - <a href=\"$uriDown\"><img src=\"xedlib/down.png\" border=\"0\" alt=\"Move Down\" title=\"Move Down\" /></a> ";

		$ret .= " - <a href=\"$uriDel".
			"\" onClick=\"return confirm('Are you sure you wish to delete this file?')\"><img src=\"xedlib/delete.png\" border=\"0\"  alt=\"Delete\" title=\"Delete\" /></a></p>\n";

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
		else $ret .= '</p>';
		return $ret;
	}

	function GetPath($target, $fi)
	{
		$items = explode('/', $fi->path);
		$ret = null;
		$cpath = '';
		for ($ix = 1; $ix < count($items); $ix++)
		{
			if (strlen($items[$ix]) < 1) continue;
			$cpath = (strlen($cpath) > 0 ? $cpath.'/' : null).$items[$ix];
			$uri = MakeURI($target, array('editor' => $this->name, 'cf' => $cpath));
			$ret .= "<a href=\"{$uri}\">{$items[$ix]}</a>";
			if ($ix < count($items)-1) $ret .= " / \n";
		}
		return $ret;
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
	public $show;

	function FileInfo($source, $DefaultFilter = 'Default')
	{
		global $user_root;
		if (!file_exists($source))
			Error("FileInfo: File/Directory does not exist. ({$source})<br/>\n");
		$this->owned = strlen(strstr($source, $user_root)) > 0;
		$this->bitpos = 0;
		$this->path = $source;
		$this->dir = dirname($source);
		$this->filename = basename($source);
		$this->show = true;

		$finfo = $this->dir.'/.'.$this->filename;
		if (file_exists($finfo))
			$this->info = unserialize(file_get_contents($finfo));
		else $this->info = array();
		$this->GetFilter($source, $DefaultFilter);
		if (is_dir($source)) $this->type = 'folder';
		if (!$this->Filter->GetInfo($this)) $this->show = false;
	}

	function GetFilter($path, $default = 'Default')
	{
		if (is_file($path))
		{
			$dirname = substr(strrchr('/'.$this->dir, '/'), 1);
			$dinfo = dirname($path).'/../.'.$dirname;
			if (file_exists($dinfo))
			{
				$dinfo = unserialize(file_get_contents($dinfo));
				$this->info = array_merge($dinfo, $this->info);
			}
		}
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

	function SaveInfo()
	{
		$info = $this->dir.'/.'.$this->filename;
		$fp = fopen($info, 'w+');
		fwrite($fp, serialize($this->info));
		fclose($fp);
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
		if (file_exists($fi->dir."/t_".$fi->filename))
			$ret->info['thumb'] = "<img src=\"{$fi->dir}/t_{$fi->filename}\" alt=\"Thumbnail\" title=\"Thumbnail\" />";
		return $ret;
	}

	/**
	* @param FileInfo $fi Target to be deleted.
	*/
	function Delete($fi)
	{
		parent::Delete($fi);
		$thumb = $fi->dir.'/t_'.$fi->filename;
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
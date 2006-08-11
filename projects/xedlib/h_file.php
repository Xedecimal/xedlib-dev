<?php

require_once('h_display.php');

$cf = GetVar('cf');

class FileManager
{
	public $name;
	public $filters;
	public $root;
	
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
	function FileManager($name, $root, $lm = null, $filters = array())
	{
		$this->name = $name;
		$this->filters = $filters;
		$this->root = $root;
		$this->lm = $lm;
	}

	function Prepare($ca, $cf)
	{
		$newcf = isset($cf) ? $cf : $this->root;
		if ($ca == "upload")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			$fi = new FileInfo($cf);
			$file = GetVar("cu");
			$fi->filter->Upload($file);
		}
		else if ($ca == "update_info")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			$info = pathinfo($cf);
			$fp = fopen($info['dirname'] . '/.' . $info['basename'], "w+");
			fwrite($fp, GetVar("ck"));
			fclose($fp);
		}
		else if ($ca == "delete")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			$fi = new FileInfo($newcf.GetVar('ci'));
			$fi->filter->Delete($fi);
		}
		else if ($ca == "createdir")
		{
			if ($this->lm == null || $this->lm->access != ACCESS_ADMIN) return;
			mkdir(GetVar("cf") . GetVar("name"));
		}
		else if ($ca == "settype")
		{
			$type = GetVar('type');
			if ($type == 'normal') unlink("$cf/.filter");
			else
			{
				$fp = fopen("$cf/.filter", "w+");
				fwrite($fp, GetVar("type"));
			}
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
		$newcf = isset($cf) ? $cf : $this->root;
		$fi = new FileInfo($newcf);
		$ret = '';

		if (file_exists($newcf))
		{
			$ret .= $this->GetHeader($target, $newcf);
			if (is_dir($newcf))
			{
				$ret .= $this->GetDirectory($target, $fi);
			}
			else
			{
				$info = dirname($cf).'/.'.basename($cf);
				$time = gmdate("M j Y H:i:s ", filemtime($cf));
				$size = filesize($cf);
				$ret .= "Size: $size bytes<br/>\n";
				$ret .= "Last Modified: $time<br/>\n";

				if ($this->lm != null && $this->lm->access == ACCESS_ADMIN)
				{
					//Additional information.
					$ret .= "<form action=\"$target\" method=\"post\"/>\n";
					$ret .= "<input type=\"hidden\" name=\"ca\" value=\"update_info\"/>";
					$ret .= "<input type=\"hidden\" name=\"cf\" value=\"$cf\"/>";
					$ret .= "Keywords (separated by spaces):<br/><textarea name=\"ck\" rows=\"5\" cols=\"50\">";
					if (file_exists($info))
					{
						$ret .= file_get_contents($info);
					}
					$ret .= "</textarea>\n";
					$ret .= "<input type=\"submit\" value=\"Update\"/>\n";
					$ret .= "</form>\n";
				}
			}
		}

		if ($this->lm != null && $this->lm->access == ACCESS_ADMIN)
		{
			$ret .= $this->GetSetType($fi);
			$ret .= $this->GetCreateDirectory();
			$ret .= $this->GetUpload();
		}
		return $ret;
	}

	function GetSetType($fi)
	{
		global $me, $cf;
		if (!isset($fi)) return null;
		$ret = <<<EOF
<form action="{$me}" method="post">
	<input type="hidden" name="ca" value="settype"/>
	<input type="hidden" name="cf" value="{$cf}"/>
	Set this directory type to:
	<select name="type">
EOF;
		foreach ($this->filters as $key => $val)
		{
			if ($fi->filtername == $key) $sel = ' selected="selected"';
			else $sel = null;
			$ret .= "<option value=\"{$key}\"{$sel}>{$val}</option>\n";
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
	<input type="hidden" name="editor" value="{$this->name}" />
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
	<input type="hidden" name="editor" value="{$this->name}" />
	<input type="hidden" name="ca" value="upload"/>
	<input type="hidden" name="cf" value="{$cf}"/>
	<input type="file" name="cu"/>
	<input type="submit" value="Upload"/>
</form>
EOF;
	}

	function GetDirectory($target, $fi)
	{
		$ret = '';
		if (!empty($fi->dirs))
		{
			$ret = "<p>Folders<br/>\n";
			foreach($fi->dirs as $dir)
			{
				$ret .= "{$dir['icon']}\n";
				$ret .= "[<a href=\""
					.MakeURI($target, array(
						'editor' => $this->name,
						'ca' => "delete",
						'cf' => $fi->path,
						'ci' => $dir['name']))
					."\" onClick=\"return confirm('Are you sure you wish to
						delete this folder?')\">X</a>]\n";
				$ret .= "<a href=\"$target?cf=".urlencode($dir['path'].'/')
					."\">{$dir['name']}<br/></a>\n";
			}
			$ret .= "</p>";
		}

		if (!empty($fi->files))
		{
			$ret .= "<p>Files<br/>\n";
			foreach($fi->files as $file)
			{
				if (isset($file['thumb'])) $ret .= "{$file['thumb']}\n";
				else $ret .= "{$file['icon']}\n";
				$ret .= "[<a href=\""
					.MakeURI($target, array(
						'editor' => $this->name,
						'ca' => "delete",
						'cf' => $fi->path,
						'ci' => $file['name']))
					."\" onClick=\"return confirm('Are you sure you wish to delete this file?')\">X</a>]\n";
				$ret .= "<a href=\"$target?cf={$file['path']}\">{$file['name']}</a><br/>\n";
			}
			$ret .= "</p>";
		}
		return $ret;
	}

	function GetHeader($target, $source)
	{
		global $me, $cf;
		$ret = null;
		if (is_dir($source))
		{
			$ret .= "<form action=\"$me\" method=\"post\">\n";
			$ret .= "<input type=\"hidden\" name=\"ca\" value=\"search\"/>\n";
			$ret .= "<input type=\"hidden\" name=\"cf\" value=\"$cf\"/>\n";
			$ret .= "Search in:\n";
		}
		$ret .= $this->GetPath($target, $source);
	
		if (is_file($source)) $ret .= " [<a href=\"$cf\" target=\"_blank\">Download</a>]";
		if (is_dir($source))
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
			$ret .= "<a href=\""
				.MakeURI($target,array('cf' => substr($path, 0, $pos+1)))
				."\">".substr($path, $start, $pos-$start)."</a> /\n";
			$start = $pos+1;
		}
		if ($pos < strlen($path))
		{
			$ret .= "<a href=\""
				.MakeURI($target, array('cf' => substr($path, 0)))
				."\">" . substr($path, $start) . "</a>";
		}
		return $ret;
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

		$pinfo = pathinfo($source);
		$this->GetFilter($pinfo['dirname']);

		if (is_dir($source))
		{
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

	function GetFilter($path)
	{
		if (file_exists("$path.filter"))
		{
			$name = file_get_contents("$path.filter");
			if (class_exists("Filter$name"))
			{
				$objname = "Filter$name";
				return $this->filter = new $objname();
			}
		}
		$this->filter = new DirFilter();
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

class FilterGallery extends DirFilter
{
	function GetName() { return "Gallery"; }

	function GetInfo($path, $file, $ext)
	{
		if ($file[0] == 't' && $file[1] == '_') return null;
		$ret = parent::GetInfo($path, $file, $ext);
		$ret['Width'] = 'w';
		$ret['Height'] = 'h';
		if (file_exists("{$path}t_{$file}.{$ext}")) $ret['thumb'] = "<img src=\"{$path}t_{$file}.{$ext}\"/>";
		return $ret;
	}

	/**
	 * @param $target string target to be deleted.
	 */
	function Delete($fi)
	{
		parent::Delete($fi);
		$thumb = $pi['dirname'].'/t_'.$pi['basename'];
		if (file_exists($thumb)) unlink($thumb);
	}

	function Upload($file)
	{
		global $cf;
		$dirname = $cf;
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
		$destimage = "{$dirname}/{$filename}.jpg";
		$destthumb = "{$dirname}/t_{$filename}.jpg";
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
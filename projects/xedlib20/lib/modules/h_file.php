<?php

/**
 * @package FileManager
 *
 */

/**
 * Enter description here...
 *
 * @package FileManager
 */
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

	//Access Relation
	public $allow_upload;
	public $allow_create_dir;
	public $allow_delete;
	public $allow_sort;
	public $allow_rename;
	public $allow_edit;

	//Display
	public $show_title;
	public $show_files_header = 'Files';
	public $show_folders_header = 'Folders';
	/**
	 * Whether files or folders come first.
	 *
	 * @var boolean
	 */
	public $show_files_first = false;
	public $show_info = true;

	/**
	 * Enter description here...
	 *
	 * @param string $name Name of this instance.
	 * @param string $root Highlest folder level allowed.
	 * @param array $filters Directory filters allowed.
	 * @return FileManager
	 */
	function FileManager($name, $root, $filters = array(), $DefaultFilter = 'Default')
	{
		$this->name = $name;
		$this->filters = $filters;
		$this->DefaultFilter = $DefaultFilter;
		$this->root = $root;

		//Append trailing slash.
		if (!file_exists($root)) Error("FileManager::FileManager(): Root
		($root) directory does not exist.");
		if (substr($this->root, -1) != '/') $this->root .= '/';
		$this->cf = GetVar('cf');
		if (is_dir($this->root.$this->cf)
		&& strlen($this->cf) > 0
		&& substr($this->cf, -1) != '/')
			$this->cf .= '/';

		$this->allow_upload = false;
	}

	function Prepare($action)
	{
		//Actions
		if ($action == "upload" && $this->allow_upload)
		{
			$fi = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
			$file = GetVar("cu");
			$info = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
			$fi->Filter->Upload($file, $info);
		}
		else if ($action == "update_info")
		{
			if (!$this->allow_edit) return;
			$info = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
			$info->info = GetVar("info");
			$fp = fopen($info->dir.'/.'.$info->filename, "w+");
			fwrite($fp, serialize($info->info));
			fclose($fp);
		}
		else if ($action == 'rename_done')
		{
			if (!$this->allow_rename) return;
			$this->files = $this->GetDirectory();
			$fi = new FileInfo($this->root.$this->cf.GetVar('ci'));
			$name = GetVar('name');
			$fi->Filter->Rename($fi, $name);
		}
		else if ($action == "delete")
		{
			if (!$this->allow_delete) return;
			$fi = new FileInfo($this->root.$this->cf.GetVar('ci'));
			$types = GetVar('type');
			$fi->Filter->Delete($fi);
			$this->files = $this->GetDirectory();
			$ix = 0;
			foreach ($this->files[$types] as $file)
			{
				$file->info['index'] = $ix++;
				$file->SaveInfo();
			}
		}
		else if ($action == "createdir")
		{
			if (!$this->allow_create_dir) return;
			mkdir($this->root.$this->cf.GetVar("name"));
		}
		else if ($action == 'swap')
		{
			$this->files = $this->GetDirectory();
			$index = GetVar('index');
			$types = GetVar('type');
			$cd = GetVar('cd');

			$dpos = $cd == 'up' ? $index-1 : $index+1;

			$sfile = $this->files[$types][$index];
			$this->files[$types][$index] = $this->files[$types][$dpos];
			$this->files[$types][$dpos] = $sfile;

			foreach ($this->files[$types] as $ix => $file)
			{
				$file->info['index'] = $ix;
				$file->SaveInfo();
			}
		}

		if (is_dir($this->root.$this->cf)) $this->files = $this->GetDirectory();
	}

	/**
	* Return the display.
	*
	* @param string $cf Current folder.
	* @return string Output.
	*/
	function Get($target, $action)
	{
		if (!file_exists($this->root.$this->cf))
			return "FileManager::Get(): File doesn't exist ({$this->root}{$this->cf}).<br/>\n";

		$fi = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
		if (!empty($this->filters)) $fi->DefaultFilter = $this->filters[0];
		$ret = '';

		$ret .= $this->GetHeader($target, $fi);
		if (is_dir($this->root.$this->cf))
		{
			if ($this->show_files_first)
			{
				$title = "<p>{$this->show_files_header}</p>\n";
				$ret .= $this->GetFiles($target, 'files', $title);
			}
			else
			{
				$title = "<p>{$this->show_folders_header}</p>\n";
				$ret .= $this->GetFiles($target, 'dirs', $title);
			}

			if (!$this->show_files_first)
			{
				$title = "<p>{$this->show_files_header}</p>\n";
				$ret .= $this->GetFiles($target, 'files', $title);
			}
			else
			{
				$title = "<p>{$this->show_folders_header}</p>\n";
				$ret .= $this->GetFiles($target, 'dirs', $title);
			}
		}
		else
		{
			$info = dirname($this->root.$this->cf).'/.'.basename($this->root.$this->cf);
			$time = gmdate("M j Y H:i:s ", filemtime($this->root.$this->cf));
			$size = filesize($this->root.$this->cf);
			$ret .= "Size: $size bytes<br/>\n";
			$ret .= "Last Modified: $time<br/>\n";

			if ($this->allow_edit)
			{
				$title = isset($fi->info['title']) ? $fi->info['title'] : null;
				//Additional information.
				$ret .= "<form action=\"$target\" method=\"post\">\n";
				$ret .= "<input type=\"hidden\" name=\"editor\" value=\"{$this->name}\" />";
				$ret .= "<input type=\"hidden\" name=\"ca\" value=\"update_info\"/>";
				$ret .= "<input type=\"hidden\" name=\"cf\" value=\"{$this->cf}\"/>";
				$ret .= 'Title: <input type="text" name="info[title]"';
				if (file_exists($info)) $ret .= ' value="'.htmlspecialchars($title).'"';
				$ret .= " />\n";
				$ret .= "<input type=\"submit\" value=\"Update\"/>\n";
				$ret .= "</form>\n";
			}
		}

		$ret .= $this->GetSetType($target, $fi);
		if ($this->allow_create_dir) $ret .= $this->GetCreateDirectory($target, $this->cf);
		if ($this->allow_upload) $ret .= $this->GetUpload();
		if ($action == 'rename')
		{
			$file = GetVar('ci');
			$types = GetVar('type');
			$fi = new FileInfo($this->root.$this->cf.$file);

			$form = new Form('rename');
			$form->AddHidden('editor', $this->name);
			$form->AddHidden('ca', 'rename_done');
			$form->AddHidden('ci', $file);
			$form->AddHidden('type', $types);
			$form->AddInput('Name', 'text', 'name', $fi->filename);
			$form->AddInput(null, 'submit', 'butSubmit', 'Rename');
			$ret .= '<a name="rename"></a><b>Rename</b>'.$form->Get('method="post"');
		}
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

		if (is_file($source->path))
			$ret .= " [<a href=\"{$source->path}\" target=\"_blank\">Download</a>]</p>";
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
		$items = explode('/', substr($fi->path, strlen($this->root)));
		$ret = null;
		$cpath = '';

		$uri = MakeURI($target, array('editor' => $this->name));
		$ret .= "<a href=\"{$uri}\">Home</a> / ";

		for ($ix = 0; $ix < count($items); $ix++)
		{
			if (strlen($items[$ix]) < 1) continue;
			$cpath = (strlen($cpath) > 0 ? $cpath.'/' : null).$items[$ix];
			$uri = MakeURI($target, array('editor' => $this->name,
				'cf' => $cpath));
			$ret .= "<a href=\"{$uri}\">{$items[$ix]}</a>";
			if ($ix < count($items)-1) $ret .= " / \n";
		}
		return $ret;
	}

	function GetFiles($target, $type, $title)
	{
		$ret = '';
		if (!empty($this->files[$type]))
		{
			$ret .= $title;
			$ret .= '<table>';
			foreach($this->files[$type] as $ix => $file)
				$ret .= $this->GetFile($target, $file, $ix);
			$ret .= '</table>';
		}
		return $ret;
	}

	/**
	 * Get a single file.
	 *
	 * @param string $target
	 * @param FileInfo $file
	 */
	function GetFile($target, $file, $index)
	{
		$ret = '<tr>';
		if (!$file->show) return;
		$types = $file->type ? 'dirs' : 'files';
		if (isset($file->info['thumb'])) $ret .= "<td>{$file->info['thumb']}</td>\n";
		else
		{
			if (isset($this->icons[$file->type])) $icon = $this->icons[$file->type];
			if (isset($icon))
				$ret .= '<td><img src="'.$icon.'" alt="'.$file->type.'" /></td> ';
		}
		$name = ($this->show_title && isset($file->info['title'])) ?
			$file->info['title'] : $file->filename;
		if (is_file($file->path) && !$this->show_info)
			$url = $this->root.$this->cf.$file->filename;
		else $url = "$target?editor={$this->name}&amp;cf=".urlencode($this->cf.$file->filename);
		$ret .= "<td><a href=\"$url\">{$name}</a>\n";

		$common = array(
			'cf' => $this->cf,
			'editor' => $this->name,
			'type' => $types,
		);

		$uriUp = MakeURI($target, array_merge($common, array(
			'ca' => 'swap',
			'cd' => 'up',
			'index' => $index
		)));

		$uriDown = MakeURI($target, array_merge($common, array(
			'ca' => 'swap',
			'cd' => 'down',
			'index' => $index
		)));
		
		$uriDel = MakeURI($target, array_merge($common, array(
			'ca' => "delete",
			'ci' => urlencode($file->filename)
		)));

		$uriEdit = MakeURI($target, array_merge($common, array(
			'ca' => "rename",
			'ci' => urlencode($file->filename)
		)));

		if ($this->allow_sort && $index > 0)
			$ret .= "<td><a href=\"$uriUp\"><img src=\"xedlib/up.png\"
			border=\"0\" alt=\"Move Up\" title=\"Move Up\" /></a></td>";
		else $ret .= '<td>&nbsp;</td>';

		if ($this->allow_sort &&
			$index < count($this->files[$types])-1)
			$ret .= "<td><a href=\"$uriDown\"><img src=\"xedlib/down.png\"
			border=\"0\" alt=\"Move Down\" title=\"Move Down\" /></a></td>";
		else $ret .= '<td>&nbsp;</td>';

		if ($this->allow_rename)
			$ret .= "<td><a href=\"$uriEdit#rename\"><img src=\"xedlib/rename.png\"
			border=\"0\" alt=\"Rename\" title=\"Rename\" style=\"vertical-align: text-bottom\" /></a></td>";

		if ($this->allow_delete)
			$ret .= "<td><a href=\"$uriDel".
				"\" onclick=\"return confirm('Are you sure you wish to delete this file?')\"><img src=\"xedlib/delete.png\" border=\"0\"
				alt=\"Delete\" title=\"Delete\" style=\"vertical-align: text-bottom\" /></a>\n";
		$ret .= '</td></tr>';

		return $ret;
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
				if (!isset($newfi->info['index'])) $newdirs[] = $newfi;
				else $ret['dirs'][$newfi->info['index']] = $newfi;
			}
			else
			{
				if (!isset($newfi->info['index'])) $newfiles[] = $newfi;
				else $ret['files'][$newfi->info['index']] = $newfi;
			}
		}
		if (!empty($newdirs)) $ret['dirs'] = array_merge($newdirs, $ret['dirs']);
		if (!empty($newfiles)) $ret['files'] = array_merge($newfiles, $ret['files']);
		ksort($ret['dirs']);
		ksort($ret['files']);
		return $ret;
	}

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $target
	 * @param FileInfo $fi
	 * @return string HTML output
	 */
	function GetSetType($target, $fi)
	{
		if (empty($this->filters)) return null;
		if (count($this->filters) < 2) return null;
		$fname = $fi->Filter->GetName();
		$ret = "<b>Configure Type</b>\n";
		$form = new Form('formType');
		$form->AddHidden('editor', $this->name);
		$form->AddHidden('ca', 'update_info');
		$form->AddHidden('cf', $this->cf);
		$form->AddInput('Change Type', 'select', 'info[type]',
			ArrayToSelOptions($this->filters, $fname, false));
		$options = $fi->Filter->GetOptions();
		if (!empty($options)) foreach ($options as $text => $field)
		{
			$def = isset($field[2]) ? $field[2] : null;
			$val = isset($fi->info[$field[0]]) ? $fi->info[$field[0]] : $def;
			$form->AddInput($text, $field[1], "info[{$field[0]}]", $val);
		}
		$form->AddInput(null, 'submit', 'butSubmit', 'Update');
		return $ret.$form->Get('action="'.$target.'" method="post"');
	}

	function GetCreateDirectory($target)
	{
		return <<<EOF
<p><b>Create New Folder</b></p>
<form action="{$target}" method="post">
	<input type="hidden" name="editor" value="{$this->name}" />
	<input type="hidden" name="ca" value="createdir" />
	<input type="hidden" name="cf" value="{$this->cf}" />
	<input type="text" name="name" />
	<input type="submit" value="Create" />
</form>
EOF;
	}

	function GetUpload()
	{
		global $me, $cf;
		ini_set('max_execution_time', 0);
		ini_set('max_input_time', 0);
		return <<<EOF
<p><b>Upload Files to Current Folder</b></p>
<form action="{$me}" method="post" enctype="multipart/form-data">
	<input type="hidden" name="MAX_FILE_SIZE" value="50000000" />
	<input type="hidden" name="editor" value="{$this->name}" />
	<input type="hidden" name="ca" value="upload"/>
	<input type="hidden" name="cf" value="{$this->cf}"/>
	<input type="file" name="cu"/>
	<input type="submit" value="Upload" />
</form>
EOF;
	}

	function AllowAll()
	{
		$this->allow_create_dir =
		$this->allow_delete =
		$this->allow_edit =
		$this->allow_rename =
		$this->allow_sort =
		$this->allow_upload = true;
	}
}

/**
 * Collects information for a SINGLE file OR folder.
 *
 * @package FileManager
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
	* @var FilterDefault
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
		chmod($info, 0777);
	}
}

/**
 * The generic file handler.
 *
 * @package FileManager
 */
class FilterDefault
{
	function GetName() { return "Normal"; }
	function GetInfo($fi) { return $fi; }

	function GetOptions() { return null; }
	function Upload($file, $target)
	{
		move_uploaded_file($file['tmp_name'], "{$target->path}{$file['name']}");
	}

	function Rename ($fi, $newname)
	{
		$finfo = "{$fi->dir}/.{$fi->filename}";
		if (file_exists($finfo)) rename($finfo, $fi->dir.'/.'.$newname);
		rename($fi->path, $fi->dir.'/'.$newname);
	}

	/**
	* Delete a file or folder.
	*
	* @param FileInfo $fi
	*/
	function Delete($fi)
	{
		$finfo = "{$fi->dir}/.{$fi->filename}";
		if (file_exists($finfo)) unlink($finfo);
		if (is_dir($fi->path)) DelTree($fi->path);
		else unlink($fi->path);
	}
}

/**
 * Enter description here...
 *
 * @package FileManager
 */
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

	function GetOptions()
	{
		return array(
			'Thumbnail Width' => array('thumb_width', 'text', 200),
			'Thumbnail Height' => array('thumb_height', 'text', 200),
			'Gallery Name' => array('gallery_name', 'text')
		);
	}

	function Rename($fi, $newname)
	{
		parent::Rename($fi, $newname);
		$thumb = $fi->dir.'/t_'.$fi->filename;
		if (file_exists($thumb)) rename($thumb, $fi->dir.'/t_'.$newname);
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
		$destimage = "{$target->path}/{$filename}.jpg";
		$destthumb = "{$target->path}/t_{$filename}.jpg";
		imagejpeg($img, $destimage);
		$img = $this->ResizeImg($img, $target->info['thumb_width'], $target->info['thumb_height']);
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
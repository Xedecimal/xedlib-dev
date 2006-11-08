<?php

require_once('h_utility.php');
require_once('h_display.php');

/**
 * @package File
 *
 */

/**
 * Allows a user to administrate files from a web browser.
 */
class FileManager
{
	/**
	 * Name of this file manager.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Behavior of this filemanager.
	 *
	 * @var FileManagerBehavior
	 */
	public $Behavior;
	/**
	 * Enter description here...
	 *
	 * @var FileManagerView
	 */
	private $view;

	/**
	 * Array of filters that are available for this object.
	 *
	 * @var array
	 */
	private $filters;
	/**
	 * People are not allowed above this folder.
	 *
	 * @var string
	 */
	private $root;
	/**
	 * Icons and their associated filetypes, overridden with FilterGallery.
	 *
	 * @var array
	 */
	private $icons;
	/**
	 * Current File
	 *
	 * @var string
	 */
	private $cf;
	/**
	 * Filter that new folders will begin with.
	 *
	 * @var FilterDefault
	 */
	private $DefaultFilter;
	/**
	 * Default options for any file.
	 *
	 * @var array
	 */
	public $DefaultOptionHandler;
	/**
	 * An array of files and folders.
	 *
	 * @var array
	 */
	private $files;
	private $mass_avail;

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
		
		$this->Behavior = new FileManagerBehavior();
		$this->View = new FileManagerView();

		//Append trailing slash.
		if (!file_exists($root))
			Error("FileManager::FileManager(): Root ($root) directory does
			not exist.");
		if (substr($this->root, -1) != '/') $this->root .= '/';
		$this->cf = GetVar('cf');
		if (is_dir($this->root.$this->cf)
		&& strlen($this->cf) > 0
		&& substr($this->cf, -1) != '/')
			$this->cf .= '/';

		$this->allow_upload = false;
	}

	/**
	 * This must be called before Get. This will prepare for presentation.
	 *
	 * @param string $action Use GetVar('ca') usually.
	 */
	function Prepare($action)
	{
		//Actions
		if ($action == "upload" && $this->Behavior->AllowUpload)
		{
			$fi = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
			$file = GetVar("cu");
			$info = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
			$fi->Filter->Upload($file, $info);
		}
		else if ($action == "update_info")
		{
			if (!$this->Behavior->AllowEdit) return;
			$info = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
			$info->info = GetVar("info");
			$fp = fopen($info->dir.'/.'.$info->filename, "w+");
			fwrite($fp, serialize($info->info));
			fclose($fp);
		}
		else if ($action == 'rename')
		{
			if (!$this->Behavior->AllowRename) return;
			//$this->files = $this->GetDirectory();
			$fi = new FileInfo(GetVar('ci'));
			$name = GetVar('name');
			$fi->Filter->Rename($fi, $name);
			$pinfo = pathinfo($this->cf);
			if ($pinfo['basename'] == $fi->filename)
				$this->cf = $pinfo['dirname'].'/'.$name;
		}
		else if ($action == "Delete")
		{
			if (!$this->Behavior->AllowDelete) return;
			$sels = GetVar('sels');
			foreach ($sels as $file)
			{
				$fi = new FileInfo($file, $this->DefaultFilter);
				$fi->Filter->Delete($fi, $this->Behavior->Recycle);
				$types = GetVar('type');
				$this->files = $this->GetDirectory();
				$ix = 0;
			}
		}
		else if ($action == "createdir")
		{
			if (!$this->Behavior->AllowCreateDir) return;
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
		else if ($action == 'Move')
		{
			$sels = GetVar('sels');
			$ct = GetVar('ct');
			foreach ($sels as $file)
			{
				$fi = new FileInfo($file);
				$fi->Filter->Rename($fi, $ct.$fi->filename);
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

		$ret = null;

		if ($this->mass_avail = $this->Behavior->MassAvailable())
		{
			$ret .= "<form action=\"{$target}\" method=\"post\">";
			$ret .= "<input type=\"hidden\" name=\"cf\" value=\"{$this->cf}\" />";
		}
		$fi = new FileInfo($this->root.$this->cf, $this->DefaultFilter);
		if (!empty($this->filters)) $fi->DefaultFilter = $this->filters[0];
		$ret .= '<script type="text/javascript" src="'.
			GetRelativePath(dirname(__FILE__)).'/js/helper.js"></script>';

		$ret .= $this->GetHeader($target, $fi);
		if (is_dir($this->root.$this->cf))
		{
			if ($this->View->ShowFilesFirst)
			{
				$title = "<p>{$this->view->show_files_header}</p>\n";
				$ret .= $this->GetFiles($target, 'files', $title);
			}
			else
			{
				$title = "<p>{$this->View->FoldersHeader}</p>\n";
				$ret .= $this->GetFiles($target, 'dirs', $title);
			}

			if (!$this->View->ShowFilesFirst)
			{
				$title = "<p>{$this->View->FilesHeader}</p>\n";
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
		}

		$ret .= $this->GetOptions($fi, $target, $action);

		return $ret;
	}

	function GetOptions($fi, $target, $action)
	{
		$ret = null;
		if ($this->Behavior->MassAvailable())
		{
			$ret .= "<div id=\"{$this->name}_mass_options\" style=\"display: none\">";
			$ret .= "<p>With selected files...</p>\n";
			$ret .= '<input type="submit" name="ca" value="Move" /> to '.$this->GetDirectorySelect('ct')."<br/>\n";
			$ret .= '<input type="submit" name="ca" value="Delete" onclick="return confirm(\'Are you sure you wish to delete'.
				" these files?')\" />";
			$ret .= "</div></form>\n";
		}
		if ($this->Behavior->Available())
		{
			$ret .= "<p><a href=\"#\" onclick=\"toggle('{$this->name}_options'); return false;\">Options</a></p>\n";
			$ret .= "<div id=\"{$this->name}_options\" style=\"display: none\">";

			if ($this->Behavior->AllowCreateDir)
				$ret .= $this->GetCreateDirectory($target, $this->cf);
			if ($this->Behavior->AllowUpload)
				$ret .= $this->GetUpload();

			$fi = new FileInfo($this->root.$this->cf);

			if ($this->Behavior->AllowRename)
			{
				$form = new Form('rename');
				$form->AddHidden('editor', $this->name);
				$form->AddHidden('ca', 'rename');
				$form->AddHidden('ci', $fi->path);
				$form->AddHidden('cf', $this->cf);
				$form->AddInput('Name', 'text', 'name', $fi->filename);
				$form->AddInput(null, 'submit', 'butSubmit', 'Rename');
				global $me;
				$ret .= '<a name="rename"></a><b>Rename</b>'.
					$form->Get('method="post" action="'.$me.'"');
			}

			if ($this->Behavior->AllowEdit)
			{
				//Filter options.
				$form = new Form('formUpdate');
				$form->AddHidden('editor', $this->name);
				$form->AddHidden('ca', 'update_info');
				$form->AddHidden('cf', $this->cf);
				if (isset($this->DefaultOptionHandler))
				{
					$handler = $this->DefaultOptionHandler;
					$def = $handler($fi);
				}
				else $def = null;
				if ($this->Behavior->AllowSetType)
				$form->AddInput('Change Type', 'select', 'info[type]',
					ArrayToSelOptions($this->filters, $fi->Filter->Name, false));
				$options = $fi->Filter->GetOptions($def);
				if (!empty($options)) foreach ($options as $text => $field)
				{
					if (isset($field[2])) $val = $field[2];
					else $val = isset($fi->info[$field[0]]) ? $fi->info[$field[0]] : null;
					$form->AddInput($text, $field[1], "info[{$field[0]}]", $val);
				}
				$form->AddInput(null, 'submit', 'butSubmit', 'Update');
				$ret .= "<p><b>Settings for {$this->root}{$this->cf}</b></p>";
				$ret .= $form->Get('method="post" action="'.$target.'"');
			}
			$ret .= "</div>";
		}
		return $ret;
	}

	function GetDirectorySelect($name)
	{
		$ret = "<select name=\"{$name}\">";
		$ret .= $this->GetDirectorySelectRecurse($this->root);
		$ret .= '</select>';
		return $ret;
	}

	function GetDirectorySelectRecurse($path)
	{
		$ret = "<option value=\"{$path}\">{$path}</option>";
		$dp = opendir($path);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			if (!is_dir($path.$file)) continue;
			$ret .= $this->GetDirectorySelectRecurse($path.$file.'/');
		}
		closedir($dp);
		return $ret;
	}

	/**
	 * Returns the top portion of the file manager.
	 * * Path
	 * * Search
	 *
	 * @param string $target Destination of all forms.
	 * @param string $source Related directory.
	 * @return string
	 */
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
		//else $ret .= '<p>';
		$ret .= $this->GetPath($target, $source);

		if (is_file($source->path))
			$ret .= " [<a href=\"{$source->path}\" target=\"_blank\">Download</a>]</p>";
		if (is_dir($this->root.$source))
		{
			$ret .= " for <input type=\"text\" name=\"cq\"/>\n";
			$ret .= "<input type=\"submit\" value=\"Search\"/>\n";
			$ret .= "</form>\n";
		}
		//else $ret .= '</p>';
		return $ret;
	}

	/**
	 * Returns a linked breadcrumb trail of the path back to the root of this
	 * file manager.
	 *
	 * @param string $target Target filename of all links.
	 * @param FileInfo $fi File / Folder to create path out of.
	 * @return string
	 * @see Get
	 * @see GetHeader
	 */
	function GetPath($target, $fi)
	{
		$items = explode('/', substr($fi->path, strlen($this->root)));
		$ret = null;
		$cpath = '';

		if (isset($this->cf))
		{
			$uri = MakeURI($target, array('editor' => $this->name));
			$ret .= "<a href=\"{$uri}\">Home</a> / ";
		}

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

	/**
	 * Returns a series of files or folders.
	 *
	 * @param string $target Target filename of script using this.
	 * @param string $type files or dirs
	 * @param string $title Header
	 * @return string
	 */
	function GetFiles($target, $type, $title)
	{
		$ret = '';
		if (!empty($this->files[$type]))
		{
			$ret .= $title;
			$ret .= '<table>';
			foreach($this->files[$type] as $ix => $file)
				$ret .= $this->GetFile($target, $file, $type, $ix);
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
	function GetFile($target, $file, $type, $index)
	{
		$ret = "\n<tr>\n";
		if (!$file->show) return;
		if (!empty($file->info['access']) && !$this->Behavior->ShowAllFiles)
		{
			if (!in_array($this->uid, $file->info['access'])) return;
		}
		$types = $file->type ? 'dirs' : 'files';
		if (isset($file->info['thumb'])) $ret .= "<td>{$file->info['thumb']}</td>\n";
		else
		{
			if (isset($this->icons[$file->type])) $icon = $this->icons[$file->type];
			if (isset($icon))
				$ret .= '<td><img src="'.$icon.'" alt="'.$file->type.'" /></td> ';
		}
		$name = ($this->View->ShowTitle && isset($file->info['title'])) ?
			$file->info['title'] : $file->filename;
		if (is_file($file->path) && !$this->Behavior->UseInfo)
			$url = $this->root.$this->cf.$file->filename.'" target="_new';
		else $url = "$target?editor={$this->name}&amp;cf=".urlencode($this->cf.$file->filename);
		$ret .= "\t<td>\n";
		if ($this->mass_avail)
			$ret .= "\t\t<input type=\"checkbox\" id=\"sel_{$type}_{$index}\" name=\"sels[]\" value=\"{$file->path}\" onclick=\"toggleAny(['sel_files_', 'sel_dirs_'], '{$this->name}_mass_options');\" />\n";
		$ret .= "\t\t<a href=\"$url\">{$name}</a> ".
			gmdate("m/d/y h:i", filectime($file->path))."\n\t</td>\n";

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

		if ($this->Behavior->AllowSort && $index > 0)
		{
			$img = GetRelativePath(dirname(__FILE__)).'/images/up.png';
			$ret .= "\t<td><a href=\"$uriUp\"><img src=\"{$img}\" ".
			"border=\"0\" alt=\"Move Up\" title=\"Move Up\" /></a></td>";
		}
		else $ret .= "\t<td>&nbsp;</td>\n";

		if ($this->Behavior->AllowSort &&
			$index < count($this->files[$types])-1)
		{
			$img = GetRelativePath(dirname(__FILE__)).'/images/down.png';
			$ret .= "\t<td><a href=\"$uriDown\"><img src=\"{$img}\" ".
			"border=\"0\" alt=\"Move Down\" title=\"Move Down\" /></a></td>";
		}
		else $ret .= "\t<td>&nbsp;</td>\n";
		$ret .= "</tr>\n";

		return $ret;
	}

	/**
	 * Gets an array of files and directories in a directory.
	 *
	 * @return array
	 */
	function GetDirectory()
	{
		$dp = opendir($this->root.$this->cf);
		$ret['files'] = array();
		$ret['dirs'] = array();
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$newfi = new FileInfo($this->root.$this->cf.$file, $this->DefaultFilter);
			if (!isset($newfi->info['index'])) $newfi->info['index'] = 0;
			if (!$newfi->show) continue;
			if (is_dir($this->root.$this->cf.'/'.$file)) $ret['dirs'][] = $newfi;
			else $ret['files'][] = $newfi;
		}
		usort($ret['files'], array($this, 'cmp_file'));
		usort($ret['dirs'], array($this, 'cmp_file'));
		return $ret;
	}

	/**
	 * Compare two files.
	 *
	 * @param FileInfo $f1
	 * @param FileInfo $f2
	 */
	function cmp_file($f1, $f2)
	{
		return $f1->info['index'] < $f2->info['index'] ? -1 : 1;
	}

	/**
	 * Gets the form used to create folders.
	 *
	 * @param string $target Script filename that uses this editor.
	 * @return string
	 */
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

	/**
	 * Gets the form used to upload files.
	 *
	 * @return string
	 */
	function GetUpload()
	{
		global $me, $cf;
		ini_set('max_execution_time', 0);
		ini_set('max_input_time', 0);
		return <<<EOF
<p><b>Upload to Current Folder</b></p>
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
}

class FileManagerView
{
	//Display
	/**
	 * Whether titles of files or filenames are shown.
	 *
	 * @var bool
	 */
	public $ShowTitle;
	/**
	 * Text of the header that comes before files.
	 *
	 * @var string
	 */
	public $FilesHeader = 'Files';
	/**
	 * Text of the header that comes before folders.
	 *
	 * @var string
	 */
	public $FoldersHeader = 'Folders';
	/**
	 * Whether files or folders come first.
	 *
	 * @var boolean
	 */
	public $ShowFilesFirst = false;
}

class FileManagerBehavior
{
	//Access Restriction

	/**
	 * Whether or not file uploads are allowed.
	 *
	 * @var bool
	 */
	public $AllowUpload = false;
	/**
	 * Whether or not users are allowed to create directories.
	 *
	 * @var bool
	 */
	public $AllowCreateDir = false;
	/**
	 * Whether users are allowed to delete files.
	 * @see AllowAll
	 *
	 * @var bool
	 */
	public $AllowDelete = false;
	/**
	 * Whether users are allowed to manually sort files.
	 *
	 * @var bool
	 */
	public $AllowSort = false;
	/**
	 * Whether users are allowed to set filter types on folders.
	 *
	 * @var bool
	 */
	public $AllowRename = false;
	/**
	 * Whether users are allowed to rename or update file information.
	 *
	 * @var bool
	 */
	public $AllowEdit = false;
	/**
	 * Allow move.
	 *
	 * @var Allow moving files to another location.
	 */
	public $AllowMove = false;
	/**
	 * Whether users are allowed to change directory filters.
	 *
	 * @var bool
	 */
	public $AllowSetType = false;
	/**
	 * Whether file information is shown, or file is simply downloaded on click.
	 *
	 * @var bool
	 */
	public $UseInfo = true;
	/**
	 * If true, do not delete files, they are renamed to
	 * .delete_filename
	 *
	 * @var boolean
	 */
	public $Recycle = false;

	/**
	 * Return true if options are available.
	 *
	 */
	function Available()
	{
		return $this->AllowCreateDir ||
			$this->AllowUpload ||
			$this->AllowEdit;
	}
	
	/**
	 * Return true if mass options are available.
	 *
	 */
	function MassAvailable()
	{
		return $this->AllowMove ||
			$this->AllowDelete;
	}

	/**
	 * Turns on all allowances for administration usage.
	 *
	 */
	function AllowAll()
	{
		$this->AllowCreateDir =
		$this->AllowDelete =
		$this->AllowEdit =
		$this->AllowRename =
		$this->AllowSort =
		$this->AllowUpload =
		$this->AllowSetType = true;
	}
}

/**
 * Collects information for a SINGLE file OR folder.
 */
class FileInfo
{
	/**
	 * Path of this file, including the filename.
	 *
	 * @var string
	 */
	public $path;
	/**
	 * Directory of this file excluding filename.
	 *
	 * @var string
	 */
	public $dir;
	/**
	 * No idea, probably depricated.
	 *
	 * @var unknown
	 */
	public $bitpos;
	/**
	 * Name of this file, excluding path.
	 *
	 * @var string
	 */
	public $filename;

	/**
	* Directory Filter.
	*
	* @var FilterDefault
	*/
	public $Filter;
	/**
	 * Name of the associated filter, hopefully depricated.
	 *
	 * @var string
	 */
	public $filtername;
	/**
	 * Whether or not the current users owns this object.
	 *
	 * @var bool
	 */
	public $owned;
	/**
	 * Array of serializable information on this file, including but not limited
	 * to, index and title.
	 *
	 * @var array
	 */
	public $info;
	/**
	 * Extension of this filename, used for collecting icon information.
	 *
	 * @var string
	 */
	public $type;
	/**
	 * Thumbnail of this item, this should be depricated as it only applies
	 * to FilterGallery.
	 *
	 * @var string
	 */
	public $thumb;
	/**
	 * Whether or not this file should be shown.
	 *
	 * @var bool
	 */
	public $show;

	/**
	 * Creates a new FileInfo from an existing file. Filter manages how this
	 * information will be handled, manipulated or displayed.
	 *
	 * @param string $source Filename to gather information on.
	 * @param string $DefaultFilter Associated directory filter.
	 * @return FileInfo
	 */
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

	/**
	 * Returns the filter that was explicitely set on this object, object's
	 * directory, or fall back on the default filter.
	 *
	 * @param string $path Path to file to get filter of.
	 * @param string $default Default filter to fall back on.
	 * @return DirFilter
	 */
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
		return $this->Filter = new FilterDefault();
	}

	/**
	 * Gets a bit of a path, a bit is anything between the path separators
	 * ('/').
	 *
	 * @param int $off Which bit to return
	 * @return string
	 */
	function GetBit($off)
	{
		$items = explode('/', $this->path);
		if ($off < count($items)) return $items[$off];
		return null;
	}

	/**
	 * Serializes the information of this file to the filesystem for later
	 * reuse.
	 *
	 */
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
 */
class FilterDefault
{
	/**
	 * Returns the name of this filter.
	 *
	 * @return string
	 */
	function GetName() { return "Normal"; }

	/**
	 * Places information into $fi for later use.
	 *
	 * @param FileInfo $fi
	 * @return FileInfo
	 * @todo Replace this with ApplyInfo as reference with no return.
	 */
	function GetInfo(&$fi)
	{
		return $fi;
	}

	/**
	 * Returns an array of options that allow configuring this filter.
	 *
	 * @return array
	 */
	function GetOptions(&$default)
	{
		$more = array(
			'Title' => array('title', 'text')
		);
		if (!empty($default)) return array_merge($default, $more);
		else return $more;
	}

	/**
	 * Called when a file is requested to upload.
	 *
	 * @param array $file Upload form's file field.
	 * @param string $target Destination folder.
	 */
	function Upload($file, $target)
	{
		move_uploaded_file($file['tmp_name'], "{$target->path}{$file['name']}");
	}

	/**
	 * Called when a file is requested to be renamed.
	 *
	 * @param FileInfo $fi Source file information.
	 * @param FileInfo $newname Destination file information.
	 */
	function Rename($fi, $newname)
	{
		$pinfo = pathinfo($newname);
		$finfo = "{$fi->dir}/.{$fi->filename}";
		$ddir = $pinfo['dirname'] == '.' ? $fi->dir : $pinfo['dirname'];
		if (file_exists($finfo))
			rename($finfo, $ddir.'/.'.$pinfo['basename']);
		rename($fi->path, $ddir.'/'.$pinfo['basename']);
	}

	/**
	* Delete a file or folder.
	*
	* @param FileInfo $fi
	*/
	function Delete($fi, $save)
	{
		$finfo = "{$fi->dir}/.{$fi->filename}";
		if (file_exists($finfo)) unlink($finfo);
		if ($save) rename($fi->path, $fi->dir.'/.deleted_'.$fi->filename);
		else if (is_dir($fi->path)) DelTree($fi->path);
		else unlink($fi->path);
	}
}

class FilterGallery extends FilterDefault
{
	public $Name = 'Gallery';

	/**
	 * Appends the width, height, thumbnail and any other image related
	 * information on this file.
	 *
	 * @param FileInfo $fi
	 * @return FileInfo
	 */
	function GetInfo(&$fi)
	{
		parent::GetInfo($fi);
		if (substr($fi->filename, 0, 2) == 't_') return null;
		$fi->info['thumb_width'] = 200;
		$fi->info['thumb_height'] = 200;
		if (file_exists($fi->dir."/t_".$fi->filename))
			$fi->info['thumb'] = "<img src=\"{$fi->dir}/t_{$fi->filename}\" alt=\"Thumbnail\" title=\"Thumbnail\" />";
		return $fi;
	}

	/**
	 * Returns an array of options that allow configuring this filter.
	 *
	 * @return array
	 */
	function GetOptions(&$default)
	{
		return array_merge(parent::GetOptions($default), array(
			'Thumbnail Width' => array('thumb_width', 'text', 200),
			'Thumbnail Height' => array('thumb_height', 'text', 200),
		));
	}

	/**
	 * Called when a file is requested to be renamed.
	 *
	 * @param FileInfo $fi Source file information.
	 * @param string $newname Destination filename.
	 */
	function Rename($fi, $newname)
	{
		parent::Rename($fi, $newname);
		$thumb = $fi->dir.'/t_'.$fi->filename;
		if (file_exists($thumb)) rename($thumb, $fi->dir.'/t_'.$newname);
	}

	/**
	* @param FileInfo $fi Target to be deleted.
	*/
	function Delete($fi, $save)
	{
		parent::Delete($fi, $save);
		$thumb = $fi->dir.'/t_'.$fi->filename;
		if (file_exists($thumb)) unlink($thumb);
	}

	/**
	 * Called when a file is requested to upload.
	 *
	 * @param array $file Upload form's file field.
	 * @param string $target Destination folder.
	 */
	function Upload($file, $target)
	{
		$filename = substr(basename($file['name']), 0, strpos(basename($file['name']), '.'));
		switch ($file['type'])
		{
			case "image/jpeg":
			case "image/pjpeg":
				$img = imagecreatefromjpeg($file['tmp_name']);
				$destthumb = "{$target->path}/t_{$filename}.jpg";
				$img = $this->ResizeImg($img, $target->info['thumb_width'], $target->info['thumb_height']);
				imagejpeg($img, $destthumb);
			break;
			case "image/x-png":
			case "image/png":
				$img = imagecreatefrompng($file['tmp_name']);
				$destthumb = "{$target->path}/t_{$filename}.png";
				$img = $this->ResizeImg($img, $target->info['thumb_width'], $target->info['thumb_height']);
				imagepng($img, $destthumb);
			break;
			case "image/gif":
				$img = imagecreatefromgif($file['tmp_name']);
				$destthumb = "{$target->path}/t_{$filename}.gif";
				$img = $this->ResizeImg($img, $target->info['thumb_width'], $target->info['thumb_height']);
				imagegif($img, $destthumb);
			break;
		}
		$destimage = "{$target->path}/{$filename}.jpg";
		parent::Upload($file, $target);
	}

	/**
	 * Resizes an image bicubicly with GD keeping aspect ratio.
	 *
	 * @param resource $image
	 * @param int $newWidth
	 * @param int $newHeight
	 * @return resource
	 */
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
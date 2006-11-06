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
	private $behavior;
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
	 * Whether or not files and folders can be manually sorted with an index.
	 *
	 * @var bool
	 */
	private $sortable;
	/**
	 * An array of files and folders.
	 *
	 * @var array
	 */
	private $files;

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
		
		$this->behavior = new FileManagerBehavior();
		$this->view = new FileManagerView();

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
			$path = $this->root.$this->cf.GetVar('ci');
			$fi = new FileInfo($path, $this->DefaultFilter);
			$fi->Filter->Delete($fi);
			$types = GetVar('type');
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
			if ($this->view->show_files_first)
			{
				$title = "<p>{$this->view->show_files_header}</p>\n";
				$ret .= $this->GetFiles($target, 'files', $title);
			}
			else
			{
				$title = "<p>{$this->view->show_folders_header}</p>\n";
				$ret .= $this->GetFiles($target, 'dirs', $title);
			}

			if (!$this->view->show_files_first)
			{
				$title = "<p>{$this->view->show_files_header}</p>\n";
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

		if ($this->behavior->AllowSetType)
			$ret .= $this->GetSetType($target, $fi);
		if ($this->allow_create_dir) $ret .= $this->GetCreateDirectory($target, $this->cf);
		if ($this->allow_upload) $ret .= $this->GetUpload();
		if ($action == 'rename')
		{
			global $ci;
			$file = $ci;
			$types = GetVar('type');
			$fi = new FileInfo($this->root.$this->cf.$file);

			$form = new Form('rename');
			$form->AddHidden('editor', $this->name);
			$form->AddHidden('ca', 'rename_done');
			$form->AddHidden('ci', $file);
			$form->AddHidden('type', $types);
			$form->AddInput('Name', 'text', 'name', $fi->filename);
			$form->AddRow(array('Or select a new location'));
			$form->AddRow(array('<iframe src="xedlib/a_file.php?browse=true" /></iframe>'));
			$form->AddInput(null, 'submit', 'butSubmit', 'Rename');
			global $me;
			$ret .= '<a name="rename"></a><b>Rename</b>'.$form->Get('method="post" action="'.$me.'"');
		}

		if ($this->allow_edit)
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
			$options = $fi->Filter->GetOptions($def);
			if (!empty($options)) foreach ($options as $text => $field)
			{
				if (isset($field[2])) $val = $field[2];
				else $val = isset($fi->info[$field[0]]) ? $fi->info[$field[0]] : null;
				$form->AddInput($text, $field[1], "info[{$field[0]}]", $val);
			}
			$form->AddInput(null, 'submit', 'butSubmit', 'Update');
			$ret .= "<p><b>Configuration for {$this->root}{$this->cf}</b></p>";
			$ret .= $form->Get('method="post" action="'.$target.'"');
		}
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
		if (!empty($file->info['access']) && !$this->show_all_files)
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
		$name = ($this->view->show_title && isset($file->info['title'])) ?
			$file->info['title'] : $file->filename;
		if (is_file($file->path) && !$this->view->show_info)
			$url = $this->root.$this->cf.$file->filename.'" target="_new';
		else $url = "$target?editor={$this->name}&amp;cf=".urlencode($this->cf.$file->filename);
		$ret .= "<td><a href=\"$url\">{$name}</a> ".
			gmdate("m/d/y h:i", filectime($file->path))."\n";

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

		$uriRename = MakeURI($target, array_merge($common, array(
			'ca' => "rename",
			'ci' => urlencode($file->filename)
		)));

		if ($this->allow_sort && $index > 0)
			$ret .= "<td><a href=\"$uriUp\"><img src=\"xedlib/images/up.png\"
			border=\"0\" alt=\"Move Up\" title=\"Move Up\" /></a></td>";
		else $ret .= '<td>&nbsp;</td>';

		if ($this->allow_sort &&
			$index < count($this->files[$types])-1)
			$ret .= "<td><a href=\"$uriDown\"><img src=\"xedlib/images/down.png\"
			border=\"0\" alt=\"Move Down\" title=\"Move Down\" /></a></td>";
		else $ret .= '<td>&nbsp;</td>';

		if ($this->allow_rename)
			$ret .= "<td><a href=\"$uriRename#rename\"><img src=\"xedlib/images/rename.png\"
			border=\"0\" alt=\"Rename or Move\" title=\"Rename or Move\" style=\"vertical-align: text-bottom\" /></a></td>";

		if ($this->allow_delete)
			$ret .= "<td><a href=\"$uriDel".
				"\" onclick=\"return confirm('Are you sure you wish to delete this file?')\"><img src=\"xedlib/images/delete.png\" border=\"0\"
				alt=\"Delete\" title=\"Delete\" style=\"vertical-align: text-bottom\" /></a>\n";
		$ret .= '</td></tr>';

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
			$newfi = new FileInfo($this->root.$this->cf.'/'.$file, $this->DefaultFilter);
			if (!$newfi->show) continue;
			if (is_dir($this->root.$this->cf.'/'.$file))
			{
				if (!isset($newfi->info['index'])) $newdirs[] = $newfi;
				else array_splice($ret['dirs'], $newfi->info['index'], 0, array($newfi));
			}
			else
			{
				if (!isset($newfi->info['index'])) $newfiles[] = $newfi;
				//We have to insert into the array so the merge doesn't end
				//up overwriting items that don't have indexes.
				else array_splice($ret['files'], $newfi->info['index'], 0, array($newfi));

				//This is the old method in case the above breaks sorting
				//functionality. Don't forgot to revert dirs too if this is the
				//case.
				//$ret['files'][$newfi->info['index']] = $newfi;
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

	/**
	 * Turns on all allowances for administration usage.
	 *
	 */
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

class FileManagerView
{
	//Display
	/**
	 * Whether titles of files or filenames are shown.
	 *
	 * @var bool
	 */
	public $show_title;
	/**
	 * Text of the header that comes before files.
	 *
	 * @var string
	 */
	public $show_files_header = 'Files';
	/**
	 * Text of the header that comes before folders.
	 *
	 * @var string
	 */
	public $show_folders_header = 'Folders';
	/**
	 * Whether files or folders come first.
	 *
	 * @var boolean
	 */
	public $show_files_first = false;
	/**
	 * Whether file information is shown, or file is simply downloaded on click.
	 *
	 * @var bool
	 */
	public $show_info = true;
}

class FileManagerBehavior
{
	//Access Restriction
	/**
	 * Whether or not file uploads are allowed.
	 *
	 * @var bool
	 */
	public $AllowUpload;
	/**
	 * Whether or not users are allowed to create directories.
	 *
	 * @var bool
	 */
	public $AllowCreateDir;
	/**
	 * Whether users are allowed to delete files.
	 * @see AllowAll
	 *
	 * @var bool
	 */
	public $AllowDelete;
	/**
	 * Whether users are allowed to manually sort files.
	 *
	 * @var bool
	 */
	public $AllowSort;
	/**
	 * Whether users are allowed to set filter types on folders.
	 *
	 * @var bool
	 */
	public $AllowRename;
	/**
	 * Whether users are allowed to rename or update file information.
	 *
	 * @var bool
	 */
	public $AllowEdit;
	/**
	 * Allow move.
	 *
	 * @var Allow moving files to another location.
	 */
	public $AllowMove;
	/**
	 * Whether users are allowed to change directory filters.
	 *
	 * @var bool
	 */
	public $AllowSetType;
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
	 * @param string $newname Destination filename.
	 */
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

class FilterGallery extends FilterDefault
{
	/**
	 * Returns the name of this filter.
	 *
	 * @return string
	 */
	function GetName() { return "Gallery"; }

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
			'Gallery Name' => array('gallery_name', 'text')
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
	function Delete($fi)
	{
		parent::Delete($fi);
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
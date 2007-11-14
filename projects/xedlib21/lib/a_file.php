<?php

/**
 * @package File Management
 */

require_once('h_utility.php');
require_once('h_display.php');

define('FM_SORT_MANUAL', -1);
define('FM_SORT_TABLE', -2);

define('FM_ACTION_UNKNOWN', 0);
define('FM_ACTION_CREATE', 5);
define('FM_ACTION_DELETE', 2);
define('FM_ACTION_MOVE', 3);
define('FM_ACTION_REORDER', 7);
define('FM_ACTION_RENAME', 6);
define('FM_ACTION_UPDATE', 4);
define('FM_ACTION_UPLOAD', 1);

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
	private $Name;

	/**
	 * Behavior of this filemanager.
	 *
	 * @var FileManagerBehavior
	 */
	public $Behavior;

	/**
	 * Visibility properties.
	 *
	 * @var FileManagerView
	 */
	public $View;

	/**
	 * People are not allowed above this folder.
	 *
	 * @var string
	 */
	public $Root;

	/**
	 * Array of filters that are available for this object.
	 *
	 * @var array
	 */
	private $filters;

	/**
	 * Icons and their associated filetypes, overridden with FilterGallery.
	 *
	 * @var array
	 */
	public $icons;

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
	public $files;
	/**
	 * Whether or not mass options are available and should be output.
	 *
	 * @var bool
	 */
	private $mass_avail;

	/**
	* User id that can designate what files are available to the
	* current user.
	* @var int
	*/
	public $uid;

	/**
	 * Enter description here...
	 *
	 * @param string $name Name of this instance.
	 * @param string $root Highlest folder level allowed.
	 * @param array $filters Directory filters allowed.
	 * @param string $DefaultFilter Default selected filter.
	 */
	function FileManager($name, $root, $filters = null, $DefaultFilter = 'Default')
	{
		$this->filters = $filters;

		$this->Name = $name;
		$this->DefaultFilter = $DefaultFilter;
		$this->Root = $root;

		$this->Behavior = new FileManagerBehavior();
		$this->View = new FileManagerView();

		if (!file_exists($root))
			Error("FileManager::FileManager(): Root ($root) directory does
			not exist.");

		//Append trailing slash.
		if (substr($this->Root, -1) != '/') $this->Root .= '/';
		$this->cf = SecurePath(GetVar('cf'));

		if (is_dir($this->Root.$this->cf)
			&& strlen($this->cf) > 0
			&& substr($this->cf, -1) != '/')
			$this->cf .= '/';
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
			if (GetVar('cm') == 'done')
			{
				foreach (glob($this->Root.$this->cf.'.*') as $file)
				{
					if (preg_match('#/.\[([0-9]*)\]_(.*)#', $file, $m))
					$fp = fopen($this->Root.$this->cf.$m[2], 'ab');
					fwrite($fp, file_get_contents($file));
					fclose($fp);
					unlink($file);
				}
			}
			ini_set('upload_max_filesize', ini_get('post_max_size'));

			$fi = new FileInfo($this->Root.$this->cf, $this->DefaultFilter);
			$files = GetVar('cu');

			//SWF Hack. Should be removed later.
			$swfile = GetVar('Filedata');

			if (!empty($swfile))
			{
				$files['name'][] = $swfile['name'];
				$files['type'][] = $swfile['type'];
				$files['tmp_name'][] = $swfile['tmp_name'];
			}
			//End SWF hack. - Xed

			foreach ($files['name'] as $ix => $file)
			{
				$newup = array(
					'name' => $files['name'][$ix],
					'type' => $files['type'][$ix],
					'tmp_name' => $files['tmp_name'][$ix]
				);

				$fi->Filter->Upload($newup, $fi);

				if (!empty($this->Behavior->Watcher))
					RunCallbacks($this->Behavior->Watcher, FM_ACTION_UPLOAD,
					$this->Root.$this->cf.$newup['name']);
			}
		}
		else if ($action == "update_info")
		{
			if (!$this->Behavior->AllowEdit) return;
			$info = new FileInfo($this->Root.$this->cf, $this->DefaultFilter);
			$newinfo = GetPost('info');
			$info->Filter->Updated($info, $newinfo);
			$this->Behavior->Update($newinfo);

			if (!empty($newinfo))
			{
				//Filter has been changed, we need to notify them.
				if (isset($newinfo['type']) && $info->Filter->Name != $newinfo['type'])
				{
					$info->Filter->Cleanup($info->path);
					$type = "Filter".$newinfo['type'];
					$newfilter = new $type();
					$newfilter->Install($info->path);
				}

				$info->info = array_merge($info->info, $newinfo);
				$info->SaveInfo();

				if (!empty($this->Behavior->Watcher))
					RunCallbacks($this->Behavior->Watcher, FM_ACTION_UPDATE,
						$info->path);
			}
		}
		else if ($action == 'Update Captions') //Mass Captions
		{
			if (!$this->Behavior->AllowEdit) return;
			$caps = GetVar('titles');

			if (!empty($caps))
			foreach ($caps as $file => $cap)
			{
				if (strlen($cap) < 1) continue;
				$fi = new FileInfo($this->Root.$this->cf.'/'.$file, $this->DefaultFilter);
				$fi->info['title'] = $cap;
				$fi->Filter->Updated($fi);
				$fi->SaveInfo();
			}
		}
		else if ($action == 'rename')
		{
			if (!$this->Behavior->AllowRename) return;
			$fi = new FileInfo(GetVar('ci'), $this->DefaultFilter);
			$name = GetVar('name');
			$fi->Filter->Rename($fi, $name);
			$pinfo = pathinfo($this->cf);
			if ($pinfo['basename'] == $fi->filename)
				$this->cf = $pinfo['dirname'].'/'.$name;
			if (!empty($this->Behavior->Watcher))
				RunCallbacks($this->Behavior->Watcher, FM_ACTION_RENAME,
					$fi->path.' to '.$name);
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
				if (!empty($this->Behavior->Watcher))
					RunCallbacks($this->Behavior->Watcher, FM_ACTION_DELETE,
						$fi->path);
			}
		}
		else if ($action == "createdir")
		{
			if (!$this->Behavior->AllowCreateDir) return;
			$p = $this->Root.$this->cf.GetVar("name");
			mkdir($p);
			chmod($p, 0755);
			FilterDefault::UpdateMTime($p);

			if (!empty($this->Behavior->Watcher))
				RunCallbacks($this->Behavior->Watcher, FM_ACTION_CREATE,
					$p);
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
			if (!empty($this->Behavior->Watcher))
				RunCallbacks($this->Behavior->Watcher, FM_ACTION_REORDER,
					$sfile->path . ' ' . ($cd == 'up' ? 'up' : 'down'));
		}
		else if ($action == 'Move')
		{
			$sels = GetVar('sels');
			$ct = GetVar('ct');
			foreach ($sels as $file)
			{
				$fi = new FileInfo($file, $this->DefaultFilter);
				$fi->Filter->Rename($fi, $ct.$fi->filename);

				if (!empty($this->Behavior->Watcher))
					RunCallbacks($this->Behavior->Watcher, FM_ACTION_MOVE,
						$fi->path . ' to ' . $ct);
			}
		}

		else if ($action == 'Download Selected')
		{
			require_once('3rd/zipfile.php');
			$zip = new zipfile();
			$zip->AddFiles(GetVar('sels'));

			$fname = pathinfo($this->Root.$this->cf);
			$fname = $fname['filename'].'.zip';

			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$fname.'"');
			header('Content-Transfer-Encoding: binary');
			echo $zip->file();
			die();
		}

		if (is_dir($this->Root.$this->cf)) $this->files = $this->GetDirectory();
	}

	/**
	* Return the display.
	*
	* @param string $target Target script.
	* @param string $action Current action, usually stored in GetVar('ca').
	* @return string Output.
	*/
	function Get($target, $action, $defaults = null)
	{
		if (!file_exists($this->Root.$this->cf))
			return "FileManager::Get(): File doesn't exist ({$this->Root}{$this->cf}).<br/>\n";

		$t = new Template();

		$fi = new FileInfo($this->Root.$this->cf, $this->DefaultFilter);

		$t->Set('name', $this->Name);
		$t->Set('header', $this->GetHeader($target, $fi));
		$t->Set('mass_available', $this->mass_avail = $this->Behavior->MassAvailable());

		if (!empty($this->filters)) $fi->DefaultFilter = $this->filters[0];

		$t->Set('options', $this->GetOptions($fi, $target, $action));

		if (is_dir($this->Root.$this->cf))
		{
			$t->Set('files', $this->GetFiles($target, 'files'));
			$t->Set('files_neck', $this->View->TextFilesNeck);
			$t->Set('folders', $this->GetFiles($target, 'dirs'));
			$t->Set('cf', $this->cf);
			$ret = $t->Get(dirname(__FILE__).'/temps/file/directory.php');
		}
		else
		{
			$t->Set('date', gmdate("M j Y H:i:s ", filectime($this->Root.$this->cf)));
			$t->Set('size', GetSizeString(filesize($this->Root.$this->cf)));
			$ret = $t->Get(dirname(__FILE__).'/temps/file/details.php');
		}

		return $ret;
	}

	/**
	 * Returns all specific and mass options for the current location.
	 *
	 * @param FileInfo $fi
	 * @param string $target
	 * @param string $action
	 * @return string
	 */
	function GetOptions($fi, $target, $action)
	{
		$ret = null;
		if ($this->Behavior->MassAvailable())
		{
			$ret .= "<div id=\"{$this->Name}_mass_options\">";
			$ret .= "<script type=\"text/javascript\">document.getElementById('{$this->Name}_mass_options').style.display = 'none';</script>";
			$ret .= "<p>With selected files...</p>\n";
			$ret .= '<input type="submit" name="ca" value="Move" /> to '.$this->GetDirectorySelect('ct')."<br/>\n";
			$ret .= '<input type="submit" name="ca" value="Delete" onclick="return confirm(\'Are you sure you wish to delete'.
				" these files?')\" />";
			$ret .= '<input type="submit" name="ca" value="Download Selected" />';
			$ret .= "</div></form>\n";
		}
		if ($this->Behavior->Available())
		{
			global $me;

			$ret .= "<p><a href=\"#\" onclick=\"toggle('{$this->Name}_options'); return false;\">View Options for this File or Folder</a></p>\n";
			$ret .= "<div id=\"{$this->Name}_options\">";
			if ($this->Behavior->HideOptions)
				$ret .= "<script type=\"text/javascript\">document.getElementById('{$this->Name}_options').style.display = 'none';</script>";

			if ($this->Behavior->AllowUpload && is_dir($fi->path))
			{
				ini_set('max_input_time', 0);

				if (!ini_get('file_uploads')) Error("File uploads are not
					enabled on this server, you should disable this ability in
					the FileManager::Behavior properties.");

				$maxsize = GetSizeString(min(GetStringSize(ini_get('post_max_size')),
							   GetStringSize(ini_get('upload_max_filesize'))));

				$pname = GetRelativePath(dirname(__FILE__));
				$sid = @$_COOKIE['PHPSESSID'];

				//Flash Uploader.

/*				$out = <<<EOF
	Maximum allowable individual file size: {$maxsize}<br/>
	<script type="text/javascript" src="{$pname}/js/swfobject.js"></script>
	<form action="{$target}" method="post" enctype="multipart/form-data">
	<input type="hidden" name="editor" value="{$this->Name}" />
	<input type="hidden" name="ca" value="upload"/>
	<input type="hidden" name="cf" value="{$this->cf}"/>
	<div id="flashUpload">
	<p><strong>You need to upgrade your Flash Player</strong></p>
	<p>Please visit <a href="http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash&amp;promoid=BIOW">Adobe</a> to obtain this.
	</p></div>
	<noscript>
	<input type="file" name="cu[]"/>
	<input type="submit" value="Upload" />
	</noscript>
	<script type="text/javascript">
	// <![CDATA[
	var so = new SWFObject("{$pname}/swf/fileUpload.swf", "fileUpload", "550", "100", "9");
	so.addParam('allowScriptAccess', 'sameDomain');
	so.addParam('movie', 'fileUpload.swf');
	so.addParam('quality', 'high');
	so.addParam('wmode', 'transparent');
	so.addParam('flashvars', 'uploadPage={$me}&amp;returns=editor,{$this->Name},ca,upload,cf,{$this->cf},PHPSESSID,{$sid}&amp;ref=editor,{$this->Name},cf,{$this->cf}');
	so.write("flashUpload");
	// ]]>
	</script>
	<input type="file" name="cu[]" />
	<input type="submit" value="Send" />
	</form>
EOF;*/

				//Java Uploader

				$loc = GetRelativePath(dirname(__FILE__));

				$sid = GetVar('PHPSESSID');

				$out = <<<EOF
		<applet codebase="{$loc}/java" code="uploadApplet.class" archive="UploadApplet.jar,commons-codec-1.3.jar,commons-httpclient-3.0.1.jar,commons-logging-1.0.4.jar" width="500" height="100">
			<param name="host" value="http://{$_SERVER['HTTP_HOST']}" />
			<param name="pathToScript" value="{$me}?PHPSESSID={$sid}" />
			<param name="path" value='{$this->cf}' />
			<param name="uploadMax" value="2044304" />
		</applet>
EOF;
				$ret .= GetBox('box_upload', $this->View->TitleUpload,
					$out, 'template_box.html');
			}

			if ($this->Behavior->AllowCreateDir && is_dir($fi->path))
			{
				$out = <<<EOF
<form action="{$target}" method="post">
	<input type="hidden" name="editor" value="{$this->Name}" />
	<input type="hidden" name="ca" value="createdir" />
	<input type="hidden" name="cf" value="{$this->cf}" />
	<input type="text" name="name" />
	<input type="submit" value="Create" /><br/>
	<small>Type folder name then click "create"</small>
</form>
EOF;
				$ret .= GetBox('box_createdir', $this->View->TitleCreateFolder,
					$out, 'template_box.html');
			}

			if ($this->Behavior->AllowRename)
			{
				$form = new Form('rename');
				$form->LabelStart = $form->LabelEnd = $form->FieldStart =
				$form->FieldEnd = '';
				$form->AddHidden('editor', $this->Name);
				$form->AddHidden('ca', 'rename');
				$form->AddHidden('ci', $fi->path);
				$form->AddHidden('cf', $this->cf);
				$form->AddInput('</td><td>Current Name');
				$form->AddInput(new FormInput(null,
					'text', 'name', $fi->filename, null));
				$form->AddInput('</td><td><small>Don\'t forget to include
					the correct file extension with the name (i.e. - .jpg, .zip,
					.doc, etc.)</small>');
				$form->AddInput(new FormInput('<br/>', 'submit', 'butSubmit', 'Rename'));
				global $me;
				$out = $form->Get('method="post" action="'.$me.'"');
				$ret .= GetBox('box_rename', $this->View->RenameTitle, $out,
					'template_box.html');
			}

			if ($this->Behavior->AllowEdit)
			{
				//Filter options.
				$form = new Form('formUpdate', null, false);
				$form->AddHidden('editor', $this->Name);
				$form->AddHidden('ca', 'update_info');
				$form->AddHidden('cf', $this->cf);

				if (isset($this->DefaultOptionHandler))
				{
					$handler = $this->DefaultOptionHandler;
					$def = $handler($fi);
				}
				else $def = null;

				if ($this->Behavior->AllowSetType && count($this->filters) > 1 && is_dir($fi->path))
				$form->AddInput(new FormInput('Change Type', 'select',
					'info[type]',
					ArrayToSelOptions($this->filters, $fi->Filter->Name,
					false)));

				$options = $fi->Filter->GetOptions($this, $fi, $def);
				$options = array_merge($options, $this->Behavior->GetOptions($fi));

				if (!empty($options))
				{
					foreach ($options as $col => $field)
					{
						if (is_string($field)) $form->AddInput($field);
						else $form->AddInput($field);
					}
					if ($this->Behavior->UpdateButton)
						$form->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));

					//$end = substr(strrchr(substr($this->cf, 0, -1), '/'), 1);
					//$start = substr($this->cf, 0, -strlen($end)-1);
					$ret .= GetBox('box_settings', $this->View->TextAdditional,
						$form->Get('method="post" action="'.$target.'"'), 'template_box.html');
				}
			}
			$ret .= "</div>";
		}
		return $ret;
	}

	/**
	 * Returns a tree selection of a directory mostly used for moving files.
	 *
	 * @param string $name Name of form item.
	 * @return string
	 */
	function GetDirectorySelect($name)
	{
		$ret = "<select name=\"{$name}\">";
		$ret .= $this->GetDirectorySelectRecurse($this->Root, $this->Behavior->IgnoreRoot);
		$ret .= '</select>';
		return $ret;
	}

	/**
	 * Recurses a single item in a directory.
	 *
	 * @access private
	 * @param string $path Root path to recurse into.
	 * @param bool $ignore Don't include this path.
	 * @return string
	 */
	function GetDirectorySelectRecurse($path, $ignore)
	{
		if (!$ignore) $ret = "<option value=\"{$path}\">{$path}</option>";
		else $ret = '';
		$dp = opendir($path);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			if (!is_dir($path.$file)) continue;
			$ret .= $this->GetDirectorySelectRecurse($path.$file.'/', false);
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
		if (is_dir($source->path) && $this->Behavior->AllowSearch)
		{
			$ret .= "<form action=\"$target\" method=\"post\">\n";
			$ret .= "<input type=\"hidden\" name=\"editor\" value=\"$this->Name\" />\n";
			$ret .= "<input type=\"hidden\" name=\"ca\" value=\"search\"/>\n";
			$ret .= "<input type=\"hidden\" name=\"cf\" value=\"{$source->path}\"/>\n";
			$ret .= "Search in: /\n";
		}
		//else $ret .= '<p>';
		$ret .= $this->GetPath($target, $source);

		if (is_file($source->path))
			$ret .= " [<a href=\"{$source->path}\" target=\"_blank\">Download</a>]</p>";
		if (is_dir($source->path) && $this->Behavior->AllowSearch)
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
		$items = explode('/', substr($fi->path, strlen($this->Root)));
		$ret = null;
		$cpath = '';

		if (isset($this->cf))
		{
			$uri = URL($target, array('editor' => $this->Name));
			$ret .= "To navigate back, or choose another folder, click the
			link(s) below<br/> <a href=\"{$uri}\">Home</a> ";
		}

		for ($ix = 0; $ix < count($items); $ix++)
		{
			if (strlen($items[$ix]) < 1) continue;
			$cpath = (strlen($cpath) > 0 ? $cpath.'/' : null).$items[$ix];
			$uri = URL($target, array('editor' => $this->Name,
				'cf' => $cpath));
			$ret .= " &raquo; <a href=\"{$uri}\">{$items[$ix]}</a>";
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
	function GetFiles($target, $type)
	{
		$ret = '';
		if (!empty($this->files[$type]))
		{
			$ret .= '<table class="tableFiles">';
			$end = false;
			if (count($this->files[$type]) > 1
				&& $this->View->Sort == FM_SORT_MANUAL
				&& $this->Behavior->AllowSort)
			{
				$ret .= '<tr><th>File</th>';
				$ret .= '<th colspan="2">Action</th>';
				$end = true;
			}
			if ($this->Behavior->QuickCaptions)
			{
				$ret .= '<th>Caption</th>';
				$end = true;
			}
			if ($end) $ret .= '</tr>';
			$ix = 0;
			foreach($this->files[$type] as $file)
			{
				if (!$file->show) continue;
				if (!$this->Behavior->ShowAllFiles && !empty($file->info['access']))
				{
					if (!$this->GetVisible($file)) continue;
				}
				$ret .= $this->GetFile($target, $file, $type, $ix++);
			}
			$ret .= '</table>';
			if ($this->Behavior->MassAvailable())
				$ret .= "<input id=\"butSelAll{$type}\" type=\"button\"
					onclick=\"docmanSelAll('{$type}');\"
					value=\"Select all {$type}\" />";
			if ($this->Behavior->AllowEdit && $this->Behavior->QuickCaptions)
			{
				$ret .= '<input type="submit" name="ca" value="Update Captions" />';
			}
		}
		return $ret;
	}

	/**
	 * Get a single file.
	 *
	 * @param string $target Target script to anchor to.
	 * @param FileInfo $file File information on this object.
	 * @param string $type files or dirs.
	 * @param int $index Index of this item in the parent.
	 * @return string Single row for the files table.
	 */
	function GetFile($target, $file, $type, $index)
	{
		$t = new Template();
		$t->Set('class', $index % 2 ? 'even' : 'odd');
		//$ret = "\n<tr class=\"{$class}\">\n";

		$types = $file->type ? 'dirs' : 'files';
		if (isset($file->info['thumb']))
			$t->Set('thumb', "<img src=\"".URL($file->info['thumb'])."\" alt=\"Thumbnail\" />");
		else
		{
			if (isset($this->icons[$file->type])) $icon = $this->icons[$file->type];
			if (isset($icon))
				$ret .= '<td><img src="'.$icon.'" alt="'.$file->type.'" /></td> ';
		}

		$name = ($this->View->ShowTitle && isset($file->info['title'])) ?
			$file->info['title'] : $file->filename;

		if (is_file($file->path))
		{
			if (isset($this->Behavior->FileCallback))
			{
				$cb = $this->Behavior->FileCallback;
				$url = $cb($file);
			}
			else if (!$this->Behavior->UseInfo)
				$url = $this->Root.$this->cf.$file->filename.'" target="_new';
			else
				$url = $target.'?editor='.$this->Name.'&amp;cf='.urlencode($this->cf.$file->filename);
		}
		else
			$url = "$target?editor={$this->Name}&amp;cf=".urlencode($this->cf.$file->filename);

		if ($this->mass_avail)
			$t->Set('check', "\t\t<input type=\"checkbox\"
			id=\"sel_{$type}_{$index}\" name=\"sels[]\" value=\"{$file->path}\"
			onclick=\"toggleAny(['sel_files_', 'sel_dirs_'],
			'{$this->Name}_mass_options');\" />\n");
		else $t->Set('check', '');
		$t->Set('file', "<a href=\"$url\">{$name}</a>");
		$time = isset($file->info['mtime']) ? $file->info['mtime'] : filemtime($file->path);
		$t->Set('date', gmdate("m/d/y h:i", $time));

		$common = array(
			'cf' => $this->cf,
			'editor' => $this->Name,
			'type' => $types,
		);

		$uriUp = URL($target, array_merge($common, array(
			'ca' => 'swap',
			'cd' => 'up',
			'index' => $index
		)));

		$uriDown = URL($target, array_merge($common, array(
			'ca' => 'swap',
			'cd' => 'down',
			'index' => $index
		)));

		$uriDel = URL($target, array_merge($common, array(
			'ca' => "delete",
			'ci' => urlencode($file->filename)
		)));

		//Move Up

		if ($this->Behavior->AllowSort
			&& $this->View->Sort == FM_SORT_MANUAL
			&& $index > 0)
		{
			$img = GetRelativePath(dirname(__FILE__)).'/images/up.png';
			$t->Set('butup', "<a href=\"$uriUp\"><img src=\"{$img}\" ".
			"alt=\"Move Up\" title=\"Move Up\" /></a>");
		}
		else $t->Set('butup', '');

		//Move Down

		if ($this->Behavior->AllowSort
			&& $this->View->Sort == FM_SORT_MANUAL
			&& $index < count($this->files[$types])-1)
		{
			$img = GetRelativePath(dirname(__FILE__)).'/images/down.png';
			$t->Set('butdown', "<a href=\"$uriDown\"><img src=\"{$img}\" ".
			"alt=\"Move Down\" title=\"Move Down\" /></a>");
		}
		else $t->Set('butdown', '');

		if ($this->Behavior->QuickCaptions)
		{
			$id = $type.'_'.$index;
			$ret .= '<td>'
			.'<textarea name="titles['.$file->filename.']" rows="2" cols="30">'
				.@htmlspecialchars(stripslashes($file->info['title'])).
				'</textarea>'
			.'</td>';
		}

		return $t->Get(dirname(__FILE__).'/temps/file/file.php');
	}

	/**
	 * Gets an array of files and directories in a directory.
	 *
	 * @return array
	 */
	function GetDirectory()
	{
		$dp = opendir($this->Root.$this->cf);
		$ret['files'] = array();
		$ret['dirs'] = array();
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$newfi = new FileInfo($this->Root.$this->cf.$file, $this->DefaultFilter);
			if (!isset($newfi->info['index'])) $newfi->info['index'] = 0;
			if (!$newfi->show) continue;
			if (is_dir($this->Root.$this->cf.'/'.$file)) $ret['dirs'][] = $newfi;
			else $ret['files'][] = $newfi;
		}

		usort($ret['dirs'], array($this, 'cmp_file'));
		usort($ret['files'], array($this, 'cmp_file'));

		return $ret;
	}

	/**
	 * Compare two files.
	 *
	 * @param FileInfo $f1
	 * @param FileInfo $f2
	 * @return int Higher or lower in comparison.
	 */
	function cmp_file($f1, $f2)
	{
		if ($this->View->Sort == FM_SORT_MANUAL)
			return $f1->info['index'] < $f2->info['index'] ? -1 : 1;
		else
			return strcasecmp($f1->filename, $f2->filename);
	}

	/**
	 * Whether or not $file is visible to the current user or not.
	 * @param FileInfo $file FileInfo object to get access information out of.
	 * @return bool Whether or not this object is visible.
	 */
	function GetVisible($file)
	{
		if (!isset($this->uid)) return true;

		if (!isset($file->info['access']) &&
			dirname($file->path) != dirname($this->Root))
			return $this->GetVisible(new FileInfo($file->dir));

		//Altering this again, user ids are stored as keys, not values!
		//if there is a specific reason for them to be stored as values then
		//we'll need another solution. -- Xed

		if (!empty($file->info['access']))
			if (isset($file->info['access'][$this->uid]))
				return true;

		return false;
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
	 * @var bool
	 */
	public $ShowFilesFirst = false;
	/**
	 * Sorting method used for files.
	 *
	 * @var int
	 */
	public $Sort = FM_SORT_TABLE;
	/**
	 * Whether or not to show the date next to files.
	 *
	 * @var bool
	 */
	public $ShowDate = true;
	/**
	 * Whether to float items instead of displaying them in a table.
	 *
	 * @var bool
	 */
	public $FloatItems = false;
	/**
	 * Create folder text to be displayed.
	 * @var string
	 */
	public $TitleCreateFolder = 'Create New Folder';
	/**
	 * Title message for the upload box.
	 * @var string
	 */
	public $TitleUpload = '<b>Upload Files to Current Folder</b> - <i>Browse hard drive then click "upload"</i>';

	/**
	 * Test displayed as collapsable link from the main view.
	 * @var string
	 */
	public $TextAdditional = '<b>Additional Settings</b>';

	public $TextFilesNeck = '';

	public $RenameTitle = 'Rename File / Folder';
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
	 * Allow moving files to another location.
	 *
	 * @var bool
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
	 * @var bool
	 */
	public $Recycle = false;

	/**
	 * Override file hiding.
	 *
	 * @var bool
	 */
	public $ShowAllFiles = false;

	/**
	 * Allow searching files.
	 *
	 * @var bool
	 */
	public $AllowSearch = false;

	/**
	 * Location of where to store logs.
	 *
	 * @var mixed
	 */
	public $Watchers = null;

	/**
	 * Whether or not to ignore the root folder when doing file operations.
	 * @var bool
	 */
	public $IgnoreRoot = false;

	/**
	 * A callback to modify the output of each file link.
	 * @var string
	 */
	public $FileCallback = null;

	/**
	 * Array of possible accessors.
	 * @var array
	 */
	public $Access;

	/**
	 * Whether or not quick captions are available.
	 * @var bool
	 */
	public $QuickCaptions = false;

	/**
	 * @var bool
	 */
	public $UpdateButton = true;

	public $HideOptions = true;

	/**
	 * Return true if options are available.
	 * @return bool
	 */
	function Available()
	{
		return $this->AllowCreateDir ||
			$this->AllowUpload ||
			$this->AllowEdit;
	}

	/**
	 * Return true if mass options are available.
	 * @return bool
	 */
	function MassAvailable()
	{
		return $this->AllowMove || $this->AllowDelete;
	}

	/**
	 * Turns on all allowances for administration usage.
	 */
	function AllowAll()
	{
		$this->AllowCreateDir =
		$this->AllowDelete =
		$this->AllowEdit =
		$this->AllowRename =
		$this->AllowSort =
		$this->AllowUpload =
		$this->AllowSetType =
		true;
	}

	/**
	 * Get behavior / security related options.
	 * @param FileInfo $fi Associated file information.
	 * @return array Array of FormInput objects to append to the parent form.
	 */
	function GetOptions($fi)
	{
		if (!empty($fi->info['access']))
		foreach ($fi->info['access'] as $id => $set)
		{
			if (isset($this->Access[$id]))
				$this->Access[$id]->selected = true;
		}
		$ret = array();
		if (isset($this->Access))
			$ret[] = new FormInput('<b>File / Folder Access</b> - <i>Ctrl+ select the users who can access this file/folder.</i><br/>', 'selects', 'info[access]',
				$this->Access);
		return $ret;
	}

	/**
	 * Called when an item gets updated as a handler.
	 * @param array $info Related file information.
	 */
	function Update(&$info)
	{
		if (!empty($info['access']))
		{
			$na = array();
			foreach ($info['access'] as $ix => $id) $na[$id] = 1;
			$info['access'] = $na;
		}
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
	 * Position of the current forward slash.
	 *
	 * @var int
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
	 */
	function FileInfo($source, $DefaultFilter = 'Default')
	{
		global $user_root;
		if (!file_exists($source))
			Error("FileInfo: File/Directory does not exist. ({$source})<br/>\n");

		if (strlen($user_root) > 0)
			$this->owned = strlen(strstr($source, $user_root)) > 0;

		$this->bitpos = 0;
		$this->path = $source;
		$this->dir = dirname($source);
		$this->filename = basename($source);
		$this->show = true;

		$finfo = $this->dir.'/.'.$this->filename;
		if (file_exists($finfo))
		{
			$this->info = unserialize(file_get_contents($finfo));
			if (!isset($this->info))
				Error("Failed to unserialize: {$finfo}<br/>\n");
		}
		else $this->info = array();
		$this->GetFilter($source, $DefaultFilter);
		if (is_dir($source)) $this->type = 'folder';

		$s = $this->Filter->GetInfo($this);
		if (!isset($s)) $this->show = false;
	}

	/**
	 * Returns the filter that was explicitely set on this object, object's
	 * directory, or fall back on the default filter.
	 *
	 * @param string $path Path to file to get filter of.
	 * @param string $default Default filter to fall back on.
	 * @return FilterDefault Or a derivitive.
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
				unset($dinfo['title']); //Don't inherit captions.
				if (is_array($dinfo))
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
		//This can cause issues if trying to chmod in the root. If the webserver
		//created the file, it should already be writeable.
		//chmod($info, 0777);
	}
}

/**
 * The generic file handler.
 */
class FilterDefault
{
	/**
	 * Name of this filter for identification purposes.
	 * @var string
	 */
	public $Name = "Default";

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
	 * @param FileManager $fm Calling filemanager.
	 * @param FileInfo $fi Object to get information out of.
	 * @param string $default Default option set.
	 * @return array
	 */
	function GetOptions(&$fm, &$fi, $default)
	{
		$more = array(
			new FormInput('<b>Change Display Name</b> - <i>Type the name that
				you would like to be displayed with the current file / folder.</i><br/>', 'text', 'info[title]',
			stripslashes(@$fi->info['title']), null)
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
		$dest = "{$target->path}{$file['name']}";
		move_uploaded_file($file['tmp_name'], $dest);
		$this->UpdateMTime($dest);
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
		$this->UpdateMTime($ddir.'/'.$pinfo['basename']);
	}

	/**
	 * When options are updated, this will be fired.
	 * @param FileInfo $fi Associated file information.
	 */
	function Updated(&$fi, &$newinfo)
	{
	}

	/**
	* Delete a file or folder.
	*
	* @param FileInfo $fi Associated file information.
	* @param bool $save Whether or not to back up the file getting deleted.
	*/
	function Delete($fi, $save)
	{
		$finfo = "{$fi->dir}/.{$fi->filename}";
		if (file_exists($finfo)) unlink($finfo);
		if ($save)
		{
			$r_target = $fi->dir.'/.deleted_'.$fi->filename;
			if (file_exists($r_target)) unlink($r_target);
			rename($fi->path, $fi->dir.'/.deleted_'.$fi->filename);
		}
		else if (is_dir($fi->path)) DelTree($fi->path);
		else unlink($fi->path);
	}

	/**
	 * Called when a filter is set to this one.
	 * @param string $path Source path.
	 */
	function Install($path) {}

	/**
	 * Called when a filter is no longer set to this one.
	 * @param string $path Source path.
	 */
	function Cleanup($path) {}

	static function UpdateMTime($filename)
	{
		$finfo = new FileInfo($filename);
		$finfo->info['mtime'] = time();
		$finfo->SaveInfo();
	}
}

class FilterGallery extends FilterDefault
{
	/**
	 * Name of this object for identification purposes.
	 *
	 * @var string
	 */
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
		if (empty($fi->info['thumb_width'])) $fi->info['thumb_width'] = 200;
		if (empty($fi->info['thumb_height'])) $fi->info['thumb_height'] = 200;
		if (file_exists($fi->dir."/t_".$fi->filename))
			$fi->info['thumb'] = "{$fi->dir}/t_{$fi->filename}";
		return $fi;
	}

	/**
	 * @param FileInfo $fi Associated file information.
	 */
	function Updated(&$fi, &$newinfo)
	{
		if (is_file($fi->path)) unset(
			$fi->info['thumb_width'],
			$fi->info['thumb_height'],
			$fi->info['thumb']
		);
	}

	/**
	 * Returns an array of options that allow configuring this filter.
	 * @param FileInfo $fi Associated file information.
	 * @param array $default Default values.
	 * @return array
	 */
	function GetOptions(&$fm, &$fi, $default)
	{
		$new = array();
		if (is_dir($fi->path))
		{
			$new[] = new FormInput('Thumbnail Width', 'text',
				'info[thumb_width]', $fi->info['thumb_width']);
			$new[] = new FormInput('Thumbnail Height', 'text',
				'info[thumb_height]', $fi->info['thumb_height']);
		}
		return array_merge(parent::GetOptions($fm, $fi, $default), $new);
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
		$ttarget = dirname($newname).'/t_'.basename($newname);
		if (file_exists($thumb)) rename($thumb, $ttarget);
	}

	/**
	 * Called when an item is to be deleted.
	 *
	 * @param FileInfo $fi Target to be deleted.
	 * @param bool $save Whether or not to back up the item to be deleted.
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
		$filename = substr(basename($file['name']), 0, strrpos(basename($file['name']), '.'));
		$ext = strrchr($file['name'], '.');
		$destthumb = "{$target->path}/t_{$filename}{$ext}";
		switch (strtolower($ext))
		{
			case ".jpg":
			case ".jpeg":
				$img = imagecreatefromjpeg($file['tmp_name']);
				$img = $this->ResizeImg($img, $target->info['thumb_width'], $target->info['thumb_height']);
				imagejpeg($img, $destthumb);
			break;
			case ".png":
				$img = imagecreatefrompng($file['tmp_name']);
				$img = $this->ResizeImg($img, $target->info['thumb_width'], $target->info['thumb_height']);
				imagepng($img, $destthumb);
			break;
			case ".gif":
				$img = imagecreatefromgif($file['tmp_name']);
				$img = $this->ResizeImg($img, $target->info['thumb_width'], $target->info['thumb_height']);
				imagegif($img, $destthumb);
			break;
		}
		//$destimage = "{$target->path}/{$filename}.jpg";
		parent::Upload($file, $target);
	}

	/**
	 * Regenerates the associated thumbnails for a given folder.
	 * @param string $path Destination path.
	 */
	function Install($path)
	{
		$files = glob($path."*.*");
		$fi = new FileInfo($path);
		$fi->info['thumb_width'] = $fi->info['thumb_height'] = 200;
		foreach ($files as $file)
		{
			if (substr($file, 0, 2) == 't_') continue;
			$pinfo = pathinfo($file);
			$destthumb = "{$pinfo['dirname']}/t_{$pinfo['basename']}";
			switch ($pinfo['extension'])
			{
				case "jpg":
				case "jpeg":
					$img = imagecreatefromjpeg($file);
					$img = $this->ResizeImg($img, $fi->info['thumb_width'], $fi->info['thumb_height']);
					imagejpeg($img, $destthumb);
				break;
				case "png":
					$img = imagecreatefrompng($file);
					$img = $this->ResizeImg($img, $fi->info['thumb_width'], $fi->info['thumb_height']);
					imagepng($img, $destthumb);
				break;
				case "gif":
					$img = imagecreatefromgif($file);
					$img = $this->ResizeImg($img, $fi->info['thumb_width'], $fi->info['thumb_height']);
					imagegif($img, $destthumb);
				break;
			}
		}
	}

	/**
	 * Cleans up all the generated thumbnail files for the given path.
	 * @param string $path Target path.
	 */
	function Cleanup($path)
	{
		$files = glob($path."t_*.*");
		foreach ($files as $file) unlink($file);
	}

	/**
	 * Resizes an image bicubicly with GD keeping aspect ratio.
	 *
	 * @param resource $img
	 * @param int $nx
	 * @param int $ny
	 * @return resource
	 */
	function ResizeImg($img, $nx, $ny)
	{
		$sx  = ImageSX($img);
		$sy = ImageSY($img);
		if ($sx < $nx && $sy < $ny) return $img;

		if ($sx < $sy)
		{
			$dx = $nx * $sx / $sy;
			$dy = $ny;
		}
		else
		{
			$dx = $nx;
			$dy = $ny * $sy / $sx;
		}
		$dimg = imagecreatetruecolor($dx, $dy);
		ImageCopyResampled($dimg, $img, 0, 0, 0, 0, $dx, $dy, $sx, $sy);
		return $dimg;
	}
}

?>

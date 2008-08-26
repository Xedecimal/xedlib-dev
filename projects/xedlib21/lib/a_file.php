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
define('FM_ACTION_DOWNLOAD', 8);
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
	 */
	function FileManager($name, $root, $filters = null)
	{
		$this->Name = CleanID($name);
		$this->Root = $root;
		$this->filters = $filters;

		$this->Behavior = new FileManagerBehavior();
		$this->View = new FileManagerView();

		$this->Template = dirname(__FILE__).'/temps/file.xml';

		if (!file_exists($root))
			die("FileManager::FileManager(): Root ($root) directory does
			not exist.");

		//Append trailing slash.
		if (substr($this->Root, -1) != '/') $this->Root .= '/';
		$this->cf = SecurePath(GetState($this->Name.'_cf'));
		if (!file_exists($this->Root.$this->cf)) $this->cf = '';

		if (is_dir($this->Root.$this->cf)
			&& strlen($this->cf) > 0
			&& substr($this->cf, -1) != '/')
			$this->cf .= '/';

		$rp = GetRelativePath(dirname(__FILE__));

		$this->icons = array(
			'folder' => $rp.'/images/icons/folder.png',
			'png' => $rp.'/images/icons/image.png',
			'jpg' => $rp.'/images/icons/image.png',
			'jpeg' => $rp.'/images/icons/image.png',
			'gif' => $rp.'/images/icons/image.png',
			'pdf' => $rp.'/images/icons/acrobat.png',
			'sql' => $rp.'/images/icons/db.png',
			'xls' => $rp.'/images/icons/excel.png',
			'doc' => $rp.'/images/icons/word.png',
			'docx' => $rp.'/images/icons/word.png'
		);
	}

	/**
	 * This must be called before Get. This will prepare for presentation.
	 *
	 * @param string $action Use GetVar('ca') usually.
	 */
	function Prepare()
	{
		$act = GetVar($this->Name.'_action');

		//Don't allow renaming the root or the file manager will throw errors
		//ever after.
		if (empty($this->cf)) $this->Behavior->AllowRename = false;

		//Actions

		if ($act == 'upload' && $this->Behavior->AllowUpload)
		{
			$fi = new FileInfo($this->Root.$this->cf);
			$filter = FileInfo::GetFilter($fi, $this->Root, $this->filters);

			// Completed chunked upload.
			if (GetVar('cm') == 'done')
			{
				$target = GetVar('cu');
				$ftarget = $this->Root.$this->cf.$target;
				$count = GetVar('count'); // Amount of peices

				if (file_exists($ftarget)) unlink($ftarget);
				$fpt = fopen($ftarget, 'ab');
				for ($ix = 0; $ix < $count+1; $ix++)
				{
					$src = $this->Root.$this->cf.".[$ix]_".$target;
					fwrite($fpt, file_get_contents($src));
					unlink($src);
				}
				fclose($fpt);

				$filter->Upload($target, $fi);
				if (!empty($this->Behavior->Watchers))
					RunCallbacks($this->Behavior->Watchers, FM_ACTION_UPLOAD,
						$fi->path.$target);
			}

			// Actual upload, full or partial.
			if (!empty($_FILES['cu']))
			foreach ($_FILES['cu']['name'] as $ix => $name)
			{
				$tname = $_FILES['cu']['tmp_name'][$ix];
				move_uploaded_file($tname, $this->Root.$this->cf.$name);

				if (!preg_match('#^\.\[[0-9]+\]_.*#', $name))
				{
					$filter->Upload($name, $fi);
					if (!empty($this->Behavior->Watchers))
						RunCallbacks($this->Behavior->Watchers, FM_ACTION_UPLOAD,
							$this->Root.$this->cf.$name);
				}
			}
		}
		else if ($act == 'Save')
		{
			if (!$this->Behavior->AllowEdit) return;
			$info = new FileInfo($this->Root.$this->cf, $this->filters);
			$newinfo = GetPost($this->Name.'_info');
			$f = FileInfo::GetFilter($info, $this->Root, $this->filters);
			$f->Updated($info, $newinfo);
			$this->Behavior->Update($newinfo);

			if (!empty($newinfo))
			{
				//Filter has been changed, we need to notify them.
				if (isset($newinfo['type']) && $f->Name != $newinfo['type'])
				{
					$f->Cleanup($info->path);
					$type = "Filter".$newinfo['type'];
					$newfilter = new $type();
					$newfilter->Install($info->path);
				}

				$info->info = array_merge($info->info, $newinfo);
				$info->SaveInfo();

				if (!empty($this->Behavior->Watchers))
					RunCallbacks($this->Behavior->Watchers, FM_ACTION_UPDATE,
						$info->path);
			}
		}
		else if ($act == 'Update Captions') //Mass Captions
		{
			if (!$this->Behavior->AllowEdit) return;
			$caps = GetVar($this->Name.'_titles');

			if (!empty($caps))
			foreach ($caps as $file => $cap)
			{
				$fi = new FileInfo($this->Root.$this->cf.$file, $this->filters);
				$fi->info['title'] = $cap;
				$f = FileInfo::GetFilter($fi, $this->Root, $this->filters);
				$f->Updated($fi, $fi->info);
				$fi->SaveInfo();
			}
		}
		else if ($act == 'Rename')
		{
			if (!$this->Behavior->AllowRename) return;
			$fi = new FileInfo($this->Root.$this->cf, $this->filters);
			$name = GetVar($this->Name.'_rname');
			$f = FileInfo::GetFilter($fi, $this->Root, $this->filters);
			$f->Rename($fi, $name);
			$this->cf = substr($fi->path, strlen($this->Root)).'/';
			if (!empty($this->Behavior->Watchers))
				RunCallbacks($this->Behavior->Watchers, FM_ACTION_RENAME,
					$fi->path.' to '.$name);
		}
		else if ($act == 'Delete')
		{
			if (!$this->Behavior->AllowDelete) return;
			$sels = GetVar($this->Name.'_sels');
			if (!empty($sels))
			foreach ($sels as $file)
			{
				$fi = new FileInfo(stripslashes($file), $this->filters);
				$f = FileInfo::GetFilter($fi, $this->Root, $this->filters);
				$f->Delete($fi, $this->Behavior->Recycle);
				$types = GetVar($this->Name.'_type');
				$this->files = $this->GetDirectory();
				$ix = 0;
				if (!empty($this->Behavior->Watchers))
					RunCallbacks($this->Behavior->Watchers, FM_ACTION_DELETE,
						$fi->path);
			}
		}
		else if ($act == 'Create')
		{
			if (!$this->Behavior->AllowCreateDir) return;
			$p = $this->Root.$this->cf.GetVar($this->Name.'_cname');
			mkdir($p);
			chmod($p, 0755);
			FilterDefault::UpdateMTime($p);

			if (!empty($this->Behavior->Watchers))
				RunCallbacks($this->Behavior->Watchers, FM_ACTION_CREATE, $p);
		}
		else if ($act == 'swap')
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
			if (!empty($this->Behavior->Watchers))
				RunCallbacks($this->Behavior->Watchers, FM_ACTION_REORDER,
					$sfile->path . ' ' . ($cd == 'up' ? 'up' : 'down'));
		}
		else if ($act == 'Move')
		{
			$sels = GetVar($this->Name.'_sels');
			$ct = GetVar($this->Name.'_ct');
			if (!empty($sels))
			foreach ($sels as $file)
			{
				$fi = new FileInfo($file, $this->filters);
				$f = FileInfo::GetFilter($fi, $this->Root, $this->filters);
				$f->Rename($fi, $ct.$fi->filename);

				if (!empty($this->Behavior->Watchers))
					RunCallbacks($this->Behavior->Watchers, FM_ACTION_MOVE,
						$fi->path . ' to ' . $ct);
			}
		}

		else if ($act == 'Download Selected')
		{
			require_once('3rd/zipfile.php');
			$zip = new zipfile();
			$sels = GetVar($this->Name.'_sels');
			$total = array();
			foreach ($sels as $s) $total = array_merge($total, Comb($s, '#^t_.*#'));

			$zip->AddFiles($total);

			$fname = pathinfo($this->Root.$this->cf);
			$fname = $fname['basename'].'.zip';

			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$fname.'"');
			header('Content-Transfer-Encoding: binary');
			echo $zip->file();
			die();
		}

		else if ($act == 'getfile')
		{
			$finfo = new FileInfo($this->Root.$this->cf);
			$size = filesize($finfo->path);

			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Transfer-Encoding: binary");
			header("Content-Type: application/octet-stream");
			header("Content-Length: {$size}");
			header("Content-Disposition: attachment; filename=\"{$finfo->filename}\";" );
			set_time_limit(0);
			$fp = fopen($finfo->path, 'r');
			while ($out = fread($fp, 4096))	echo $out;
			die();
		}

		if (is_dir($this->Root.$this->cf)) $this->files = $this->GetDirectory();
	}

	function TagPart($t, $guts, $attribs)
	{
		$this->vars[$attribs['TYPE']] = $guts;
	}

	/**
	 * Returns the top portion of the file manager.
	 * * Path
	 * * Search
	 *
	 * @return string
	 */
	function TagHeader($t, $guts, $attribs)
	{
		return $guts;
	}

	function TagPath($t, $guts, $attribs)
	{
		$fi = new FileInfo($this->Root.$this->cf);
		$vp = new VarParser();
		$ret = null;
		$cpath = '';

		if (isset($this->cf))
		{
			$d['uri'] = URL($this->vars['target'],
				array($this->Name.'_cf' => '/'));
			$d['name'] = $attribs['ROOT'];
			$ret .= $vp->ParseVars($guts, $d);
		}

		$items = explode('/', substr($fi->path, strlen($this->Root)));

		global $me;
		for ($ix = 0; $ix < count($items); $ix++)
		{
			if (strlen($items[$ix]) < 1) continue;
			$cpath = (strlen($cpath) > 0 ? $cpath.'/' : null).$items[$ix];
			$uri = URL($me, array($this->Name.'_cf' => $cpath));
			$ret .= ' '.$attribs['SEP'];
			$d['name'] = $items[$ix];
			$d['uri'] = $uri;
			$ret .= $vp->ParseVars($guts, $d);
		}
		return $ret;
	}

	function TagSearch($t, $guts, $attribs)
	{
		if (!$this->Behavior->AllowSearch) return null;
		return $guts;
	}

	function TagBehavior($t, $guts, $attribs)
	{
		$name = $attribs['TYPE'];
		if ($this->Behavior->$name) return $guts;
	}

	function TagDownload($t, $guts, $attribs)
	{
		if (!is_file($this->Root.$this->cf)) return;
		return $guts;
	}

	function TagFolders($t, $guts, $attribs)
	{
		if (!empty($this->files['folders'])) return $guts;
	}

	function TagFolder($t, $guts, $attribs)
	{
		if (is_file($this->Root.$this->cf)) return;

		$ret = '';
		$ix = 0;

		if (!empty($this->files['folders']))
		foreach ($this->files['folders'] as $f)
		{
			FileInfo::GetFilter($f, $this->Root, $this->filters, $f->dir);
			if (!$f->show) continue;

			global $me;
			if (isset($this->Behavior->FolderCallback))
			{
				$cb = $this->Behavior->FolderCallback;
				$vars = $cb($f);
				foreach ($vars as $k => $v)
				{
					$vars["{$this->Name}_$k"] = $v;
					unset($vars[$k]);
				}
				global $me;
				$this->vars['url'] = URL($me, $vars);
			}
			else
				$this->vars['url'] = URL($me, array($this->Name.'_cf' =>
					"{$this->cf}{$f->filename}"));

			$this->curfile = $f;
			$this->vars['caption'] = $this->View->GetCaption($f);
			$this->vars['filename'] = $f->filename;
			$this->vars['fipath'] = $f->path;
			$this->vars['type'] = 'folders';
			$this->vars['index'] = $ix;
			if (!empty($f->icon)) $this->vars['icon'] = $f->icon;
			else $this->vars['icon'] = '';

			$common = "?cf={$this->cf}&amp;editor={$this->Name}&amp;type=folders";

			//Move Up

			if ($this->Behavior->AllowSort && $this->Behavior->Sort == FM_SORT_MANUAL && $ix > 0)
			{
				$uriUp = $common."&amp;{$this->Name}_action=swap&amp;cd=up&amp;index={$ix}";
				$img = GetRelativePath(dirname(__FILE__)).'/images/up.png';
				$this->vars['butup'] = "<a href=\"$uriUp\"><img src=\"{$img}\" ".
				"alt=\"Move Up\" title=\"Move Up\" /></a>";
			}
			else $this->vars['butup'] = '';

			//Move Down

			if ($this->Behavior->AllowSort && $this->Behavior->Sort == FM_SORT_MANUAL
				&& $ix < count($this->files['folders'])-1)
			{
				$uriDown = $common."&amp;{$this->Name}_action=swap&amp;cd=down&amp;index={$ix}";
				$img = GetRelativePath(dirname(__FILE__)).'/images/down.png';
				$this->vars['butdown'] = "<a href=\"$uriDown\"><img src=\"{$img}\" ".
				"alt=\"Move Down\" title=\"Move Down\" /></a>";
			}
			else $this->vars['butdown'] = '';

			$tfile = new Template($this->vars);
			$tfile->ReWrite('icon', array(&$this, 'TagIcon'));
			$ret .= $tfile->GetString($guts);

			$ix++;
		}
		return $ret;
	}

	function TagFiles($t, $guts)
	{
		if (!empty($this->files['files'])) return $guts;
	}

	function TagFile($t, $guts, $attribs)
	{
		global $me;

		if (is_file($this->Root.$this->cf)) return;
		$ret = '';
		$ix = 0;

		if (!empty($this->files['files']))
		foreach ($this->files['files'] as $f)
		{
			FileInfo::GetFilter($f, $this->Root, $this->filters, $f->dir);
			if (!$f->show) continue;

			$this->curfile = $f;

			if (isset($this->Behavior->FileCallback))
			{
				$cb = $this->Behavior->FileCallback;
				$vars = $cb($f, $this->cf.$f->filename);
				foreach ($vars as $k => $v)
				{
					$vars["{$this->Name}_$k"] = $v;
					unset($vars[$k]);
				}
				global $me;
				$this->vars['url'] = URL($me, $vars);
			}
			else if ($this->Behavior->UseInfo)
				$this->vars['url'] = URL($me,
					array($this->Name.'_cf' => $this->cf.$f->filename));
			else
				$this->vars['url'] = $this->Root."{$this->cf}{$f->filename}";
			$this->vars['filename'] = $f->filename;
			$this->vars['fipath'] = $f->path;
			$this->vars['type'] = 'files';
			$this->vars['index'] = $ix;
			if (!empty($f->icon)) $this->vars['icon'] = $f->icon;
			else $this->vars['icon'] = '';
			$this->vars['ftitle'] = isset($f->info['title']) ?
				@stripslashes($f->info['title']) : '';

			$common = "?cf={$this->cf}&amp;editor={$this->Name}&amp;type=files";

			//Move Up

			if ($this->Behavior->AllowSort && $this->Behavior->Sort == FM_SORT_MANUAL && $ix > 0)
			{
				$uriUp = $common."&amp;{$this->Name}_action=swap&amp;cd=up&amp;index={$ix}";
				$img = GetRelativePath(dirname(__FILE__)).'/images/up.png';
				$this->vars['butup'] = "<a href=\"$uriUp\"><img src=\"{$img}\" ".
				"alt=\"Move Up\" title=\"Move Up\" /></a>";
			}
			else $this->vars['butup'] = '';

			//Move Down

			if ($this->Behavior->AllowSort && $this->Behavior->Sort == FM_SORT_MANUAL
				&& $ix < count($this->files['files'])-1)
			{
				$uriDown = $common."&amp;{$this->Name}_action=swap&amp;cd=down&amp;index={$ix}";
				$img = GetRelativePath(dirname(__FILE__)).'/images/down.png';
				$this->vars['butdown'] = "<a href=\"$uriDown\"><img src=\"{$img}\" ".
				"alt=\"Move Down\" title=\"Move Down\" /></a>";
			}
			else $this->vars['butdown'] = '';

			$tfile = new Template($this->vars);
			$tfile->ReWrite('icon', array(&$this, 'TagIcon'));
			$tfile->ReWrite('quickcap', array(&$this, 'TagQuickCap'));
			$ret .= $tfile->GetString($guts);

			$ix++;
		}
		return $ret;
	}

	function TagIcon($t, $guts)
	{
		$file = $this->curfile;

		if (!empty($this->vars['icon'])) return $guts;
		else if (isset($this->icons[$file->type]))
		{
			$this->vars['icon'] = $this->icons[$file->type];
		}
		else return null;

		$vp = new VarParser();
		return $vp->ParseVars($guts, $this->vars);
	}

	function TagQuickCap($t, $guts)
	{
		if (!$this->Behavior->QuickCaptions) return;
		return $guts;
	}

	function TagDetails($t, $guts, $attribs)
	{
		if (is_dir($this->Root.$this->cf)) return;
		$vp = new VarParser();
		$this->vars['date'] = gmdate("M j Y H:i:s ", filectime($this->Root.$this->cf));
		$this->vars['size'] = GetSizeString(filesize($this->Root.$this->cf));
		return $vp->ParseVars($guts, $this->vars);
	}

	function TagDirectory($t, $guts, $attribs)
	{
		if (is_file($this->Root.$this->cf)) return;
		$vp = new VarParser();
		return $vp->ParseVars($guts, $this->vars);
	}

	function TagCheck($t, $guts)
	{
		if ($this->mass_avail) return $guts;
	}

	function TagQuickCapButton($t, $guts)
	{
		if (!$this->Behavior->QuickCaptions || empty($this->files['files']))
			return null;
		return $guts;
	}

	function TagOptions($t, $guts)
	{
		if ($this->Behavior->AllowMove ||
			$this->Behavior->AllowCreateDir ||
			$this->Behavior->AllowEdit ||
			$this->Behavior->AllowRename) return $guts;
	}

	function TagAddOpts($t, $guts)
	{
		$ret = '<table>';
		$vp = new VarParser();

		$fi = new FileInfo($this->Root.$this->cf);

		if (isset($this->DefaultOptionHandler))
		{
			$handler = $this->DefaultOptionHandler;
			$def = $handler($fi);
		}
		else $def = null;

		$f = FileInfo::GetFilter($fi, $this->Root, $this->filters, $fi->info);

		if ($this->Behavior->AllowSetType && count($this->filters) > 1 && is_dir($fi->path))
		{
			$in = new FormInput('Change Type', 'select',
				'info[type]',
				ArrayToSelOptions($this->filters, $f->Name,
				false));
			$this->vars['text'] = $in->text;
			$this->vars['field'] = $in->Get($this->Name);
			$ret .= $vp->ParseVars($guts, $this->vars);
		}

		$options = $f->GetOptions($this, $fi, $def);
		$options = array_merge($options, $this->Behavior->GetOptions($fi));

		if (!empty($options))
		{
			foreach ($options as $field)
			{
				if (is_string($field))
				{
					$this->vars['text'] = '';
					$this->vars['field'] = $field;
					$ret .= $vp->ParseVars($guts, $this->vars);
				}
				else if (is_array($field))
				{
					//This is a series of fields, only the first text matters
					//the rest can just be appended.
					$this->vars['text'] = $field[0]->text;
					$this->vars['field'] = '';
					foreach ($field as $f)
						$this->vars['field'] .= $f->Get($this->Name);
					$ret .= $vp->ParseVars($guts, $this->vars);
				}
				else
				{
					$this->vars['text'] = $field->text;
					$this->vars['field'] = $field->Get($this->Name);
					$ret .= $vp->ParseVars($guts, $this->vars);
				}
			}
			if ($this->Behavior->UpdateButton)
			{
				$sub = new FormInput(null, 'submit', 'action', 'Save');
				$this->vars['text'] = '';
				$this->vars['field'] = $sub->Get($this->Name, false);
				$ret .= $vp->ParseVars($guts, $this->vars);
			}
		}
		return $ret.'</table>';
	}

	/**
	* Return the display.
	*
	* @param string $target Target script.
	* @param string $action Current action, usually stored in GetVar('ca').
	* @return string Output.
	*/
	function Get()
	{
		if (!file_exists($this->Root.$this->cf))
			return "FileManager::Get(): File doesn't exist ({$this->Root}{$this->cf}).<br/>\n";

		require_once('h_template.php');

		$relpath = GetRelativePath(dirname(__FILE__));

		if (!isset($GLOBALS['page_head'])) $GLOBALS['page_head'] = '';
		$GLOBALS['page_head'] .= <<<EOF
<script type="text/javascript" src="{$relpath}/js/helper.js"></script>

<script type="text/javascript">
window.onload = function() {
	$('#{$this->Name}_mass_options').hide();
}
</script>
EOF;

		$this->mass_avail = $this->Behavior->MassAvailable();

		//TODO: Get rid of this.
		$fi = new FileInfo($this->Root.$this->cf);

		global $me;
		$this->vars['target'] = $me;
		$this->vars['root'] = $this->Root;
		$this->vars['cf'] = $this->cf;

		$this->vars['filename'] = $fi->filename;
		$this->vars['path'] = $this->Root.$this->cf;
		$this->vars['dirsel'] = $this->GetDirectorySelect($this->Name.'_ct');
		$this->vars['relpath'] = $relpath;
		$this->vars['host'] = GetVar('HTTP_HOST');
		$this->vars['sid'] = GetVar('PHPSESSID');
		$this->vars['behavior'] = $this->Behavior;

		$this->vars['folders'] = count($this->files['folders']);
		$this->vars['files'] = count($this->files['files']);

		$t = new Template();
		$t->Set($this->vars);

		$t->ReWrite('form', 'TagForm');
		$t->ReWrite('header', array(&$this, 'TagHeader'));
		$t->ReWrite('path', array(&$this, 'TagPath'));
		$t->ReWrite('download', array(&$this, 'TagDownload'));
		$t->ReWrite('search', array(&$this, 'TagSearch'));

		$t->ReWrite('behavior', array(&$this, 'TagBehavior'));

		$t->ReWrite('details', array(&$this, 'TagDetails'));
		$t->ReWrite('directory', array(&$this, 'TagDirectory'));
		$t->ReWrite('folders', array(&$this, 'TagFolders'));
		$t->ReWrite('folder', array(&$this, 'TagFolder'));
		$t->ReWrite('files', array(&$this, 'TagFiles'));
		$t->ReWrite('file', array(&$this, 'TagFile'));
		$t->ReWrite('check', array(&$this, 'TagCheck'));
		$t->ReWrite('quickcapbutton', array(&$this, 'TagQuickCapButton'));

		$t->ReWrite('options', array(&$this, 'TagOptions'));
		$t->ReWrite('addopts', array(&$this, 'TagAddOpts'));

		$fi = new FileInfo($this->Root.$this->cf);

		$t->Set('fn_name', $this->Name);

		return $t->Get($this->Template);
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
	 * Returns a series of files or folders.
	 *
	 * @param string $target Target filename of script using this.
	 * @param string $type files or dirs
	 * @param string $title Header
	 * @return string
	 * @deprecated This is no longer used.
	 */
	function GetFiles($target, $type)
	{
		$ret = '';
		if (!empty($this->files[$type]))
		{
			$cfi = new FileInfo($this->Root.$this->cf);
			$f = FileInfo::GetFilter($cfi, $this->Root, $this->filters);
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
			if ($end) $ret .= '</tr>';
			$ix = 0;
			foreach($this->files[$type] as $file)
			{
				$f->GetInfo($file);
				if (!$file->show) continue;
				if (!$this->Behavior->ShowAllFiles)
					if (!$this->GetVisible($file)) continue;
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
	function GetFile($file, $type, $index)
	{
		$d['class'] = $index % 2 ? 'even' : 'odd';

		$types = $file->type ? 'folders' : 'files';
		if (isset($file->icon))
			$d['icon'] = "<img src=\"".URL($file->icon)."\" alt=\"Icon\" />";

		else
			$d['icon'] = '';

		$name = ($this->View->ShowTitle && isset($file->info['title'])) ?
			$file->info['title'] : $file->filename;

		$uri = "?editor={$this->Name}&amp;cf=".urlencode($this->cf.$file->filename);

		if ($this->mass_avail)
			$d['check'] = "\t\t<input type=\"checkbox\"
			id=\"sel_{$type}_{$index}\" name=\"sels[]\" value=\"{$file->path}\"
			onclick=\"toggleAny(['sel_files_', 'sel_dirs_'],
			'{$this->Name}_mass_options');\" />\n";
		else $d['check'] = '';
		$d['uri'] = $uri;
		$d['file'] = $name;
		$time = isset($file->info['mtime']) ? $file->info['mtime'] : filemtime($file->path);
		if ($this->View->ShowDate) $d['date'] = gmdate("m/d/y h:i", $time);

		$common = "?cf={$this->cf}&amp;editor={$this->Name}&amp;type={$types}";
		$uriUp = $common."&amp;ca=swap&amp;cd=up&amp;index={$index}";
		$uriDown = $common."&amp;ca=swap&amp;cd=down&amp;index={$index}";

		//Move Up

		if ($this->Behavior->AllowSort
			&& $this->View->Sort == FM_SORT_MANUAL
			&& $index > 0)
		{
			$img = GetRelativePath(dirname(__FILE__)).'/images/up.png';
			$d['butup'] = "<a href=\"$uriUp\"><img src=\"{$img}\" ".
			"alt=\"Move Up\" title=\"Move Up\" /></a>";
		}
		else $d['butup'] = '';

		//Move Down

		if ($this->Behavior->AllowSort
			&& $this->View->Sort == FM_SORT_MANUAL
			&& $index < count($this->files[$type])-1)
		{
			$img = GetRelativePath(dirname(__FILE__)).'/images/down.png';
			$d['butdown'] = "<a href=\"$uriDown\"><img src=\"{$img}\" ".
			"alt=\"Move Down\" title=\"Move Down\" /></a>";
		}
		else $d['butdown'] = '';

		if ($this->Behavior->QuickCaptions)
		{
			$d['caption'] = '<textarea name="titles['.$file->filename.
				']" rows="2" cols="30">'.
				@htmlspecialchars(stripslashes($file->info['title'])).
				'</textarea>';
		}

		return $d;
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
		$ret['folders'] = array();

		$foidx = $fiidx = 0;

		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			//TODO: Should handle this on a filter level.
			if (substr($file, 0, 2) == 't_') continue;
			$newfi = new FileInfo($this->Root.$this->cf.$file, $this->filters);
			if (!$newfi->show) continue;
			if (is_dir($this->Root.$this->cf.'/'.$file))
			{
				if ($this->Behavior->ShowFolders) $ret['folders'][] = $newfi;
				let($newfi->info['index'], $foidx++);
				$newfi->SaveInfo();
			}
			else
			{
				$ret['files'][] = $newfi;
				let($newfi->info['index'], $fiidx++);
			}
		}

		usort($ret['folders'], array($this, 'cmp_file'));
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
		if ($this->Behavior->Sort == FM_SORT_MANUAL)
			return $f1->info['index'] < $f2->info['index'] ? -1 : 1;
		else return strnatcasecmp($f1->filename, $f2->filename);
	}

	/**
	 * Whether or not $file is visible to the current user or not.
	 * @param FileInfo $file FileInfo object to get access information out of.
	 * @return bool Whether or not this object is visible.
	 */
	function GetVisible($file)
	{
		if (!isset($this->uid) || is_file($file->path)) return true;

		if (!isset($file->info['access']) &&
			dirname($file->path) != dirname($this->Root))
			return $this->GetVisible(new FileInfo($file->dir));

		//Altering this again, user ids are stored as keys, not values!
		//if there is a specific reason for them to be stored as values then
		//we'll need another solution. -- Xed

		if (!empty($file->info['access']))
			if (!empty($file->info['access'][$this->uid]))
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

	public $RenameTitle = 'Rename File / Folder';

	/**
	 * Returns the caption of a given thumbnail depending on caption display
	 * configuration.
	 * @param FileInfo $file File to gather information from.
	 * @return string Actual caption.
	 */
	function GetCaption($file)
	{
		if ($this->ShowTitle
			&& !empty($file->info['title']))
			return stripslashes($file->info['title']);
		else return $file->filename;
	}
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
	public $Sort = FM_SORT_TABLE;

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
	 * Allow downloading all packaged files as a zip file.
	 *
	 * @var bool
	 */
	public $AllowDownloadZip = false;

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

	public $ShowFolders = true;

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
		return $this->AllowMove || $this->AllowDelete || $this->AllowDownloadZip;
	}

	/**
	 * Turns on all allowances for administration usage.
	 */
	function AllowAll()
	{
		$this->AllowCreateDir =
		$this->AllowDelete =
		$this->AllowMove =
		$this->AllowDownloadZip =
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
		foreach (array_keys($fi->info['access']) as $id)
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
			foreach ($info['access'] as $id) $na[$id] = 1;
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
	 * Icon of this item, this should be depricated as it only applies
	 * to FilterGallery.
	 *
	 * @var string
	 */
	public $icon;
	/**
	 * Whether or not this file should be shown.
	 *
	 * @var bool
	 */
	public $show;

	function __toString()
	{
		return $this->filename;
	}

	/**
	 * Creates a new FileInfo from an existing file. Filter manages how this
	 * information will be handled, manipulated or displayed.
	 *
	 * @param string $source Filename to gather information on.
	 * @param array $filters Array of available filters.
	 */
	function FileInfo($source)
	{
		global $user_root;
		if (!file_exists($source))
			Error("FileInfo: File/Directory does not exist. ({$source})<br/>\n");

		if (!empty($user_root))
			$this->owned = strlen(strstr($source, $user_root)) > 0;

		$this->bitpos = 0;
		$this->path = $source;
		$this->dir = dirname($source);
		$this->filename = basename($source);
		$this->show = true;

		$finfo = $this->dir.'/.'.$this->filename;
		if (is_file($finfo) && file_exists($finfo))
		{
			$this->info = unserialize(file_get_contents($finfo));
			if (!isset($this->info))
				Error("Failed to unserialize: {$finfo}<br/>\n");
		}
		else $this->info = array();
	}

	/**
	 * Returns the filter that was explicitely set on this object, object's
	 * directory, or fall back on the default filter.
	 *
	 * @param string $path Path to file to get filter of.
	 * @param string $default Default filter to fall back on.
	 * @return FilterDefault Or a derivitive.
	 */
	static function GetFilter(&$fi, $root, $defaults)
	{
		$ft = $fi;

		while (is_file($ft->path) || empty($ft->info['type']))
		{
			if (is_in($ft->dir, $root)) $ft = new FileInfo($ft->dir);
			else
			{
				if (isset($defaults[0]))
					$fname = 'Filter'.$defaults[0];
				else
					$fname = 'FilterDefault';
				$f = new $fname();
				$f->GetInfo($fi);
				return $f;
			}
		}

		if (in_array($ft->info['type'], $defaults))
			$fname = 'Filter'.$ft->info['type'];
		else $fname = 'Filter'.$defaults[0];

		$f = new $fname();
		$f->GetInfo($fi);
		return $f;
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
		//Access is not stored in files, just directories.
		if (is_file($this->path)) unset($this->info['access']);
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
		if (is_dir($fi->path)) $fi->type = 'folder';
		else $fi->type = fileext($fi->filename);
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
			new FormInput('Description', 'text', 'info[title]',
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
		$this->UpdateMTime($target->path.$file);
	}

	/**
	 * This will rename the info file and update the virtual modified time
	 * accordingly, as well as handle moving files.
	 *
	 * @param FileInfo $fi Source file information.
	 * @param FileInfo $newname Destination file information.
	 */
	function Rename(&$fi, $newname)
	{
		$pinfo = pathinfo($newname);
		$finfo = "{$fi->dir}/.{$fi->filename}";
		$ddir = $pinfo['dirname'] == '.' ? $fi->dir : $pinfo['dirname'];
		if (file_exists($finfo))
			rename($finfo, $ddir.'/.'.$pinfo['basename']);
		rename($fi->path, $ddir.'/'.$pinfo['basename']);
		$fi->path = $ddir.'/'.$newname;
		$fi->filename = $newname;
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
		if (substr($fi->filename, 0, 2) == 't_') $fi->show = false;
		if (empty($fi->info['thumb_width'])) $fi->info['thumb_width'] = 200;
		if (empty($fi->info['thumb_height'])) $fi->info['thumb_height'] = 200;

		if (file_exists($fi->dir."/t_".$fi->filename))
			$fi->icon = "{$fi->dir}/t_{$fi->filename}";

		if (is_dir($fi->path))
		{
			$fs = glob($fi->path.'/.t_image.*');
			if (!empty($fs)) $fi->icon = $fs[0];
		}
		return $fi;
	}

	/**
	 * @param FileInfo $fi Associated file information.
	 */
	function Updated(&$fi, &$newinfo)
	{
		$upimg = GetVar('upimage');
		$img = GetVar('image');
		if (!empty($upimg['name']))
		{
			mkdir("timg");
			move_uploaded_file($upimg['tmp_name'], 'timg/'.$upimg['name']);
			$newimg = 'timg/'.$upimg['name'];
		}
		else if ($img == 1)
		{
			$files = glob("{$fi->path}.t_image.*");
			foreach ($files as $f) unlink($f);
		}
		else if (!empty($img)) $newimg = $fi->path.$img;
		if (!empty($newimg))
		{
			$this->ResizeFile($newimg,
				"{$fi->path}/.t_image",
				$newinfo['thumb_width'], $newinfo['thumb_height']);
		}
		if (!empty($upimg['name']))
		{
			unlink("timg/{$upimg['name']}");
			rmdir("timg");
		}

		if (is_dir($fi->path) && (
			$fi->info['thumb_width'] != $newinfo['thumb_width'] ||
			$fi->info['thumb_height'] != $newinfo['thumb_height']))
		{
			$this->UpdateThumbs($fi, $newinfo);
		}

		if (is_file($fi->path)) unset(
			$newinfo['thumb_width'],
			$newinfo['thumb_height'],
			$newinfo['thumb']
		);
	}

	function UpdateThumbs($fi, $info)
	{
		$dp = opendir($fi->path);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			if (substr($file, 0, 2) == 't_') continue;

			$fir = new FileInfo($fi->path.'/'.$file);

			if (is_dir($fi->path.'/'.$file))
			{
				$g = glob("$fir->path/._image.*");
				if (!empty($g))
					$this->ResizeFile($g[0], $fir->path.'/.'.
						filenoext('t'.substr(basename($g[0]), 1)),
						$info['thumb_width'], $info['thumb_height']);
				$this->UpdateThumbs($fir, $info);
			}
			else
			{
				$w = $info['thumb_width'];
				$h = $info['thumb_height'];
				$src = $fir->path;
				$dst = $fir->dir.'/t_'.filenoext($fir->filename);
				$this->ResizeFile($src, $dst, $w, $h);
			}
		}
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
			$selImages[0] = new SelOption('No Change');
			$selImages[1] = new SelOption('Remove');

			if (!empty($fm->files['files']))
			foreach ($fm->files['files'] as $fiImg)
			{
				if (substr($fiImg->filename, 0, 2) == 't_') continue;
				$selImages[$fiImg->filename] = new SelOption($fiImg->filename);
			}

			$new[] = new FormInput('Thumbnail Width', 'text',
				'info[thumb_width]', $fi->info['thumb_width']);
			$new[] = new FormInput('Thumbnail Height', 'text',
				'info[thumb_height]', $fi->info['thumb_height']);
			$new[] = new FormInput('Gallery Image', 'select',
				'image', $selImages);
			$new[] = new FormInput('or Upload', 'file',
				'upimage');
		}
		return array_merge(parent::GetOptions($fm, $fi, $default), $new);
	}

	/**
	 * This will update the thumbnail properly, after the parent filter
	 * handles the move.
	 *
	 * @param FileInfo $fi Source file information.
	 * @param string $newname Destination filename.
	 */
	function Rename(&$fi, $newname)
	{
		parent::Rename($fi, $newname);
		$thumb = $fi->dir.'/t_'.basename($fi->filename);
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
	 * @param FileInfo $target Destination folder.
	 */
	function Upload($file, $target)
	{
		parent::Upload($file, $target);
		$tdest = 't_'.substr($file, 0, strrpos($file, '.'));
		$this->ResizeFile($target->path.$file, $target->path.$tdest,
			$target->info['thumb_width'], $target->info['thumb_height']);
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
			$this->ResizeFile($file, $path.'t_'.filenoext($pinfo['basename']),
				$fi->info['thumb_width'], $fi->info['thumb_height']);
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
	 * Extension will be automatically appended to $dest filename.
	 */
	function ResizeFile($file, $dest, $nx, $ny)
	{
		$pinfo = pathinfo($file);
		$dt = $dest.'.'.$pinfo['extension'];

		switch (strtolower($pinfo['extension']))
		{
			case "jpg":
			case "jpeg":
				$img = imagecreatefromjpeg($file);
				$img = $this->ResizeImg($img, $nx, $ny);
				imagejpeg($img, $dt);
			break;
			case "png":
				$img = imagecreatefrompng($file);
				$img = $this->ResizeImg($img, $nx, $ny);
				imagepng($img, $dt);
			break;
			case "gif":
				$img = imagecreatefromgif($file);
				$img = $this->ResizeImg($img, $nx, $ny);
				imagegif($img, $dt);
			break;
		}
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

<?php

/**
 * @package Gallery
 */

require_once('a_file.php');

define('CAPTION_NONE',  0);
define('CAPTION_TITLE', 1);
define('CAPTION_FILE',  2);

class Gallery
{
	/**
	 * Whether or not to display the caption specified in the file manager.
	 * @var bool
	 */
	public $InfoCaption = true;

	/**
	 * Behavioral properties.
	 * @var GalleryBehavior
	 */
	public $Behavior;

	/**
	 * Display properties.
	 * @var GalleryDisplay
	 */
	public $Display;

	/**
	 * Root location of the images for this gallery.
	 * @var string
	 */
	private $root;

	/**
	 * Constructor, sets default properties, behavior and display.
	 * @param string $root Root location of images for this gallery.
	 */
	function Gallery($root)
	{
		$this->Behavior = new GalleryBehavior();
		$this->Display = new GalleryDisplay();
		$this->root = $root;
	}

	function TagHeader($t, $guts)
	{
		global $page_head;
		$page_head .= $guts;
	}

	function TagFolder($t, $guts)
	{
		$me = GetVar('q');

		$out = '';
		$vp = new VarParser();
		$dp = opendir($this->root.$this->path);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;

			$p = $this->root.$this->path.'/'.$file;
			if (!is_dir($p)) continue;

			$fi = new FileInfo($this->root.$this->path.'/'.$file);
			$this->f->GetInfo($fi);

			$du['editor'] = GetVar('editor');
			$du['galcf'] = GetVar('galcf', '').'/'.$file;
			$d['url'] = URL($me, $du);

			$d['name'] = $file;
			$d['icon'] = $fi->vars['icon'];
			$d['editor'] = GetVar('editor');

			$out .= $vp->ParseVars($guts, $d);
		}
		return $out;
	}

	function TagFile($t, $guts)
	{
		$out = '';
		$vp = new VarParser();

		foreach ($this->files['files'] as $ix => $fi)
		{
			$this->f->GetInfo($fi);
			if (!$fi->show || empty($fi->icon)) continue;

			$d['fullname'] = $fi->path;
			$d['idx'] = $ix;
			$d['name'] = $this->GetCaption($fi);
			$d['path'] = GetVar('galcf', '');
			$d['icon'] = $fi->icon;
			$d['desc'] = $this->GetCaption($fi);

			$out .= $vp->ParseVars($guts, $d);
		}
		return $out;

		/*if ($this->Behavior->PageCount != null)
		{
			$tot = GetFlatPage($files['files'], GetVar('cp'), $this->Behavior->PageCount);
		}
		else $tot = $files['files'];

		$ix = GetVar('cp')*$this->Behavior->PageCount;
		$body .= "<tr class=\"images\"><td>\n";

		foreach ($tot as $file)
		{
			if (isset($file->icon) && file_exists($file->icon))
			{
				if (isset($file->icon) && file_exists($file->icon))
				{
					$twidth = $file->info['thumb_width']+16;
					$theight = $file->info['thumb_height']+60;
					$url = URL($me, array(
						'view' => $ix++,
						'galcf' => "$path",
						'cp' => GetVar('cp')
					));
					$caption = $this->GetCaption($file);

				$body .= <<<EOF
<div class="gallery_cell" style="overflow: auto; width: {$twidth}px; height:{$theight}px">
<table class="gallery_shadow">
<tr><td>
<a href="{$url}#fullview">
<img src="{$path}/t_{$file->filename}" alt="thumb" /></a></td><td class="gallery_shadow_right">
</td></tr>
<tr>
<td class="gallery_shadow_bottom"></td>
<td class="gallery_shadow_bright"></td>
</tr>
</table><div class="gallery_caption">$caption</div></div>
EOF;
				}
			}
		}
		$body .= '</td>';*/
	}

	function TagImage($t, $guts)
	{
		$out = '';
		$view = GetVar('view');
		if (!isset($view)) return null;

		$out .= '  ';

		$out .= '</p>';
		$out .= '';
		return $guts;
	}

	function TagPage($t, $guts)
	{
		if ($this->Behavior->PageCount != null)
		{
			$args = array('galcf' => $this->path);
			return GetPages($this->files['files'], $this->Behavior->PageCount, $args);
		}
	}

	function TagPart($t, $guts, $attribs)
	{
		$this->$attribs['TYPE'] = $guts;
	}

	/**
	 * Returns the rendered gallery.
	 * @param string $path Current location, usually GetVar('galcf')
	 * @return string Rendered gallery.
	 */
	function Get()
	{
		global $me;
		$this->f = new FilterGallery();

		require_once('h_template.php');

		$path = GetVar('galcf');
		$fm = new FileManager('gallery', $this->root.$path, array('Gallery'), 'Gallery');
		$fm->Behavior->ShowAllFiles = true;
		$fm->View->Sort = $this->Display->Sort;
		$this->files = $fm->GetDirectory();

		$t = new Template();
		$this->path = $path;
		$t->ReWrite('breadcrumb', array('FileManager', 'TagBreadcrumb'));
		$t->ReWrite('header', array(&$this, 'TagHeader'));
		$t->ReWrite('folder', array(&$this, 'TagFolder'));
		$t->ReWrite('file', array(&$this, 'TagFile'));
		$t->ReWrite('image', array(&$this, 'TagImage'));
		$t->ReWrite('page', array(&$this, 'TagPage'));
		$t->ReWrite('part', array(&$this, 'TagPart'));

		$t->Set('disable_save', $this->Behavior->DisableSave);
		$t->Set('current', GetVar('view'));
		$t->Set('galcf', GetVar('galcf'));
		$t->Set('galme', URL($me));

		$tot = 0;
		foreach ($this->files['files'] as $f)
		{
			if (substr($f->filename, 0, 2) == 't_') continue;
			$tot++;
		}
		$t->Set('total', count($this->files['files']));

		//Page related
		$view = GetVar('view');

		if (isset($view))
		{
			$fi = $this->files['files'][$view];
			$t->Set('url', $fi->path);
			$t->Set('caption', $this->GetCaption($fi));
		}

		//Back Button
		if ($view > 0)
		{
			$args = array(
				'view' => $view-1,
				'galcf' => GetVar('galcf'),
			);
			if ($this->Behavior->PageCount > 0)
				$args['cp'] = floor(($view-1)/$this->Behavior->PageCount);

			$t->Set('butBack', GetButton(URL($me, $args).'#fullview',
				'back.png', 'Back', 'class="png"'));
		}
		else $t->Set('butBack', '');

		//Forward Button
		if ($view < count($this->files['files'])-1)
		{
			$args = array(
				'view' => $view+1,
				'galcf' => GetVar('galcf'),

			);
			if ($this->Behavior->PageCount > 0)
				$args['cp'] = floor(($view+1)/$this->Behavior->PageCount);

			$t->Set('butForward', GetButton(URL($me, $args).'#fullview', 'forward.png',
				'Forward', 'class="png"'));
		}
		else $t->Set('butForward', '');

		//Gallery settings
		$fig = new FileInfo($this->root);
		FileInfo::GetFilter($fig, $this->root, array('Gallery'));
		$t->Set('file_thumb_width', $fig->info['thumb_width']+10);
		$t->Set('file_thumb_height', $fig->info['thumb_height']+50);

		$fi = new FileInfo($this->root.$path);
		if ($path != $this->root) $t->Set('name', $this->GetCaption($fi));
		else $t->Set('name', '');

		return $t->ParseFile(l('temps/gallery.xml'));
	}

	/**
	 * Returns the caption of a given thumbnail depending on caption display
	 * configuration.
	 * @param FileInfo $file File to gather information from.
	 * @return string Actual caption.
	 */
	function GetCaption($file)
	{
		if ($this->InfoCaption
			&& !empty($file->info['title'])
			&& $this->Display->Captions == CAPTION_TITLE)
			return stripslashes($file->info['title']);
		else if ($this->Display->Captions == CAPTION_FILE)
			return str_replace('_', ' ', substr($file->filename, 0, strrpos($file->filename, '.')));
		else
			return str_replace('_', ' ', $file->filename);
	}
}

class GalleryDisplay
{
	/**
	 * How to display captions, can be CAPTION_NONE, CAPTION_TITLE and
	 * CAPTION_FILE.
	 * @var int
	 */
	public $Captions = CAPTION_TITLE;

	/**
	 * String to append on the left side of the caption, this also handles
	 * variables like templates: {{variable}}.
	 * @var string
	 */
	public $CaptionLeft = '';

	/**
	 * String to append on the right side of the caption. This also handles
	 * variables like templates: {{variable}}.
	 * @var string
	 */
	public $CaptionRight = '';

	/**
	 * Method of sorting this gallery, can be SORT_MANUAL or SORT_NONE.
	 * @var int
	 */
	public $Sort;
}

class GalleryBehavior
{
	/**
	 * When true, generates a bit of javascript to block right clicking
	 * on images. Easily bypassable but easily implemented as well.
	 * @var bool
	 */
	public $DisableSave = false;

	/**
	 * The amount of images to display per-page, everything else will be
	 * calculated.
	 * @var int Numeric amount of images per page.
	 */
	public $PageCount = null;
}

?>

<?php

require_once(dirname(__FILE__).'/a_file.php');

class Gallery
{
	public $InfoCaption = true;
	public $Behavior;
	public $Display;

	private $root;

	function Gallery($root)
	{
		$this->Behavior = new GalleryBehavior();
		$this->Display = new GalleryDisplay();
		$this->root = $root;
	}

	function GetPath($root, $path, $arg = 'cf', $sep = '/', $rootname = 'Home')
	{
		if ($path == $root) return null;
		global $me;

		$items = explode('/', substr($path, strlen($root)));
		$ret = null;
		$cpath = '';

		$ret .= "<a href=\"{$me}\">$rootname</a> $sep ";

		for ($ix = 0; $ix < count($items); $ix++)
		{
			if (strlen($items[$ix]) < 1) continue;
			$cpath = (strlen($cpath) > 0 ? $cpath.'/' : null).$items[$ix];
			$uri = URL($target, array('editor' => $this->name,
				$arg => $root.'/'.$cpath));
			$ret .= "<a href=\"{$uri}\">{$items[$ix]}</a>";
			if ($ix < count($items)-1) $ret .= " $sep \n";
		}
		return $ret;
	}

	function Get($path)
	{
		global $me;

		$body = <<<EOF
<!--[if lt IE 7.]>
<script defer type="text/javascript" src="js/pngfix.js"></script>
<![endif]-->
EOF;

		$GLOBALS['page_section'] = "View Gallery";

		$fm = new FileManager('gallery', $path, array('Gallery'), 'Gallery');
		$fm->Behavior->ShowAllFiles = true;
		$files = $fm->GetDirectory();

		$fi = new FileInfo($path);

		if (is_file($path)) return;
		$body .= "<table class=\"gallery_table\">\n";

		if ($path != $this->root)
		{
			if ($this->InfoCaption && isset($fi->info['title']))
				$name = htmlspecialchars($fi->info['title']);
			else $name = $fi->filename;
			$body .= "<tr><td colspan=\"3\"><a href=\"{$me}\">View Main Gallery</a> � {$name}</td></tr>";
		}

		if (!empty($files['dirs']))
		{
			$ix = 0;
			$body .= "<tr class=\"category_row\">";
			foreach ($files['dirs'] as $dir)
			{
				$imgs = glob("$path/{$dir->path}/t_*.jpg");
				$imgcount = is_array($imgs) ? count($imgs) : 0;

				if ($this->InfoCaption && !empty($dir->info['title']))
				{
					//$fi = new FileInfo("{$path}/{$filename}");
					$name = @$dir->info['title'];
				}
				else $name = $dir->filename;

				$body .= "<td class=\"gallery_cat\">� <a href=\"".URL($me, array('galcf' => $dir->path))."\">{$name}</a></td>\n";
				if ($ix++ % 3 == 2) $body .= "</tr><tr>\n";
			}
		}

		if (!empty($files['files']))
		{
			if ($this->Behavior->PageCount != null)
			{
				$tot = GetFlatPage($files['files'], GetVar('cp'), $this->Behavior->PageCount);
			}
			else $tot = $files['files'];

			$ix = GetVar('cp')*$this->Behavior->PageCount;
			$body .= "<tr class=\"images\"><td>\n";

			foreach ($tot as $file)
			{
				if (isset($file->info['thumb']) && file_exists($file->info['thumb']))
				{
					$twidth = $file->info['thumb_width']+16;
					$theight = $file->info['thumb_height']+32;
					$url = URL($me, array('view' => $ix++, 'galcf' => "$path", 'cp' => GetVar('cp')));
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
			$body .= '</td>';
		}
		$body .= "</tr></table>\n";
		if ($this->Behavior->PageCount != null)
		{
			$args = array('galcf' => $path);
			$body .= 'Page: '.GetPages($files['files'], $this->Behavior->PageCount, $args);
		}

		$view = GetVar('view');
		if (isset($view))
		{
			if ($this->Behavior->DisableSave)
			$body .= <<<EOF
<meta http-equiv="imagetoolbar" content="no">
<script type="text/javascript">
<!--
var message = "Saving images is not allowed.";
function click(e)
{
	if (document.all)
	{
		if (event.button == 2 || event.button == 3)
		{
			alert(message);
			return false;
		}
	}
	if (document.layers)
	{
		if (e.which == 3)
		{
			alert(message);
			return false;
		}
	}
}
if (document.layers)
{
	document.captureEvents(Event.MOUSEDOWN);
}
document.onmousedown = click;
// -->
</SCRIPT>
EOF;
			$GLOBALS['page_section'] = 'View Image';

			$imgurl = $path.'/'.$files['files'][$view]->filename;
			$vname = substr(strrchr($imgurl, '/'), 1);
			$body .= '<p class="gallery_nav">';
			if ($view > 0)
				$body .= GetButton(URL($me, array(
						'view' => $view-1,
						'galcf' => $path,
						'cp' => floor(($view-1)/$this->Behavior->PageCount)
					)).'#fullview', 'back.png', 'Back', 'class="png"');
			$body .= ' <b>Picture '.($view+1).' of '.count($files['files']).'</b> ';
			if ($view < count($files['files']))
				$body .= GetButton(URL($me, array(
					'view' => $view+1,
					'galcf' => $path,
					'cp' => floor(($view+1)/$this->Behavior->PageCount)
					)).'#fullview', 'forward.png', 'Forward', 'class="png"');
			$body .= '</p>';
			$body .= '<div class="gallery_cell">
<table class="gallery_shadow">
<tr><td><img id="fullview"
	src="'.$imgurl.'"
	alt="'.$vname.'" /></td>
<td class="gallery_shadow_right">&nbsp;&nbsp;</td>
</tr><tr>
<td class="gallery_shadow_bottom"></td>
<td class="gallery_shadow_bright"></td>
</tr></table></div>'.$this->GetCaption($files['files'][$view]);
		}

		return $body;
	}

	function GetCaption($file)
	{
		$vp = new VarParser();
		$d['image'] = $file->path;
		if ($this->InfoCaption
			&& !empty($file->info['title'])
			&& $this->Display->UseDisplayTitle)
			$name = $file->info['title'];
		else $name = $file->filename;
		return $vp->ParseVars($this->Display->CaptionLeft, $d).
			$name.
			$vp->ParseVars($this->Display->CaptionRight, $d);
	}
}

class GalleryDisplay
{
	public $UseDisplayTitle = true;
	public $CaptionLeft = '';
	public $CaptionRight = '';
}

class GalleryBehavior
{
	public $DisableSave = false;
	public $PageCount = null;
}

?>

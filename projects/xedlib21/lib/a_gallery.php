<?php

require_once(dirname(__FILE__).'/a_file.php');

class Gallery
{
	public $InfoCaption = true;
	public $Display;

	private $root;

	function Gallery($root)
	{
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
				$name = $fi->info['title'];
			else $name = $fi->filename;
			$body .= "<tr><td colspan=\"3\"><a href=\"{$me}\">View Main Gallery</a> » {$name}</td></tr>";
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

				$body .= "<td class=\"gallery_cat\">» <a href=\"".URL($me, array('galcf' => $dir->path))."\">{$name}</a></td>\n";
				if ($ix++ % 3 == 2) $body .= "</tr><tr>\n";
			}
		}

		if (!empty($files['files']))
		{
			$ix = 0;
			$body .= "<tr class=\"images\"><td>\n";
			foreach ($files['files'] as $file)
			{
				if (isset($file->info['thumb']) && file_exists($file->info['thumb']))
				{
					$twidth = $file->info['thumb_width']+16;
					$theight = $file->info['thumb_height']+32;
					$url = URL($me, array('view' => $file->filename, 'galcf' => "$path"));
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

		if ($view = GetVar('view'))
		{
			$GLOBALS['page_section'] = 'View Image';

			$vname = substr(strrchr($path.'/'.$view, '/'), 1);
			$body .= "<p><img id=\"fullview\" src=\"$path/$view\" alt=\"{$vname}\" /></p>\n";
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

?>

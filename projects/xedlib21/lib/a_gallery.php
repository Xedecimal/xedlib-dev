<?php

require_once(dirname(__FILE__).'/a_file.php');

class Gallery
{
	public $InfoCaption = true;

	private $root;

	function Gallery($root)
	{
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

		if (GetVar('ca') == "view")
		{
			$GLOBALS['page_section'] = 'View Image';
			$body = '<p><a href="'.$_SERVER['SCRIPT_NAME'].'">Return to Main Gallery</a> » '.
				substr(strrchr($path, '/'), 1).'</p>';
			$body .= "<img src=\"$path\">\n";
		}
		else
		{
			$body = "\n<!--[if lt IE 7.]>
<script defer type=\"text/javascript\" src=\"pngfix.js\"></script>
<![endif]-->\n";
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
						if ($this->InfoCaption)
						{
							//$fi = new FileInfo("{$path}/{$filename}");
							$name = @$file->info['title'];
						}
						else $name = str_replace('_', ' ', substr(basename($file->filename), 0, strpos(basename($file->filename), '.')));
						$twidth = $file->info['thumb_width']+16;
						$theight = $file->info['thumb_height']+16;
						$body .= "<div class=\"gallery_cell\" style=\"width: {$twidth}px; height:{$theight}px\"><table class=\"gallery_shadow\"><tr><td><a href=\"".URL($me, array('ca' => 'view', 'galcf' => "$path/$file->filename"))."\"><img src=\"$path/t_$file->filename\"></a></td><td class=\"gallery_shadow_right\"></td></tr><tr><td class=\"gallery_shadow_bottom\"></td><td class=\"gallery_shadow_bright\"></td></tr></table><p class=\"gallery_caption\">$name</p></div>\n";
						//if ($ix++ % 3 == 2) $body .= "</tr><tr class=\"image_row\">";
					}
				}
				$body .= '</td>';
			}
			$body .= "</tr></table>\n";
		}

		return $body;
	}
}

?>

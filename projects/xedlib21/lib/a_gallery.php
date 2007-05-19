<?php

require_once('a_file.php');

class Gallery
{
	public $InfoCaption = true;
	
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
			$body = '<p><a href="'.$me.'">Return to Main Gallery</a> � '.
				substr(strrchr($path, '/'), 1).'</p>';;
			$body .= "<img src=\"$path\">\n";
		}
		else
		{
			$GLOBALS['page_section'] = "View Gallery";

			$fm = new FileManager('gallery', $path, array('Gallery'), 'Gallery');
			$fm->Behavior->ShowAllFiles = true;
			$files = $fm->GetDirectory();

			$fi = new FileInfo($path);

			if (is_file($path)) return;
			$body = "<table class=\"gallery_table\">\n";

			if (!empty($files['dirs']))
			{
				$ix = 0;
				$body .= "<tr class=\"category_row\">";
				foreach ($files['dirs'] as $dir)
				{
					$imgs = glob("$path/$fp/t_*.jpg");
					$imgcount = is_array($imgs) ? count($imgs) : 0;
					$body .= "<td class=\"gallery_cat\">� <a href=\"".URL($me, array('galcf' => $dir->path))."\">{$dir->filename}</a></td>\n";
					if ($ix++ % 3 == 2) $body .= "</tr><tr>\n";
				}
			}

			if (!empty($files['files']))
			{
				$ix = 0;
				$body .= "<tr class=\"image_row\">\n";
				foreach ($files['files'] as $file)
				{
					if (file_exists($file->info['thumb']))
					{
						if ($this->InfoCaption)
						{
							require_once('xedlib/a_file.php');
							//$fi = new FileInfo("{$path}/{$filename}");
							$name = @$file->info['title'];
						}
						else $name = str_replace('_', ' ', substr(basename($file->filename), 0, strpos(basename($file->filename), '.')));
						$body .= "<td class=\"gallery_cell\"><table class=\"gallery_shadow\"><tr><td><a href=\"".URL($me, array('ca' => 'view', 'cf' => "$path/$filename"))."\"><img src=\"$path/t_$file->filename\"></a></td><td class=\"gallery_shadow_right\"></td></tr><tr><td class=\"gallery_shadow_bottom\"></td><td class=\"gallery_shadow_bright\"></td></tr></table><p class=\"gallery_caption\">$name</p></td>\n";
						if ($ix++ % 3 == 2) $body .= "</tr><tr class=\"image_row\">";
					}
				}
			}
			$body .= "</tr></table>\n";
		}

		return $body;
	}
}

?>

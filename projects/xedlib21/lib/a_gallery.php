<?php

class Gallery
{
	public $InfoCaption = true;

	function Get($path)
	{
		global $me;

		if (GetVar('ca') == "view")
		{
			$GLOBALS['page_section'] = 'View Image';
			$body = "<a href=\"$me?cf=".dirname($path)."\">Return to Gallery</a><br/>";
			$body .= "<img src=\"$path\">\n";
		}
		else
		{
			$GLOBALS['page_section'] = "View Gallery";

			if (is_file($path)) return;
			$body .= "<table class=\"gallery_table\" align=\"center\">\n";
			$body .= "<tr><td colspan=\"3\"><a href=\"{$me}\">View Main Gallery</a></td></tr>";
			if (!file_exists("photos")) mkdir("photos");
			$dp = opendir($path);
			$ix = 0;
			$body .= "<tr class=\"category_row\">";
			while ($fp = readdir($dp))
			{
				if ($fp[0] == '.') continue;
				if (!is_dir("$path/$fp")) continue;
				$imgs = glob("$path/$fp/t_*.jpg");
				$imgcount = is_array($imgs) ? count($imgs) : 0;
				$body .= "<td class=\"category\"><a href=\"".URL($me, array('cf' => "$path/$fp"))."\">{$fp}</a></td>\n";
				if ($ix++ % 3 == 2) $body .= "</tr><tr>\n";
			}
		
			$files = glob("{$path}/*.jpg");
			if (is_array($files))
			{
				$ix = 0;
				$body .= "<tr class=\"image_row\">\n";
				foreach ($files as $file)
				{
					$filename = basename($file);
					if (substr($filename, 0, 2) == "t_") continue;
					if (file_exists("{$path}/t_$filename"))
					{
						if ($this->InfoCaption)
						{
							require_once('xedlib/a_file.php');
							$fi = new FileInfo("{$path}/{$filename}");
							$name = $fi->info['title'];
						}
						else $name = str_replace('_', ' ', substr(basename($filename), 0, strpos(basename($filename), '.')));
						$body .= "<td class=\"image\"><a href=\"".URL($me, array('ca' => 'view', 'cf' => "$path/$filename"))."\"><img src=\"$path/t_$filename\" class=\"image\"></a><br/>$name</td>\n";
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
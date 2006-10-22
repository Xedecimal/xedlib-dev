<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN"
						"http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<title> PHP Converter </title>
	<style type="text/css">
	body { font-family: Verdana, sans-serif; }
	.outer_center { margin: auto; }
	.inner_center { text-align: center; }
	</style>
</head>
<body>
<form action="tool_convert.php" method="post">
<input type="hidden" name="ca" value="convert" />
<table class="outer_center">
	<tr>
		<td>Make PHP 4 Compatible</td>
		<td><input type="radio" checked="checked" name="php" value="php_4" /></td>
	</tr>
	<tr>
		<td>Make PHP 5 Compatible</td>
		<td><input type="radio" name="php" value="php_5" /></td>
	</tr>
	<tr>
		<td colspan="2" class="inner_center">
			<input type="submit" value="Convert" />
		</td>
	</tr>
</table>
</form>

<?php

$files = array(	   "a_calendar.php",
				   "a_code.php",
				   "a_editor.php",
				   "a_file.php",
				   "h_data.php",
				   "h_display.php",
				   "h_storage.php",
				   "h_template.php",
				   "h_utility.php");

//$files = array("h_data.php");
//NOTE:  we will need something different for h_template, becuase that has
//some public HTML stuff in their we don't want changed.

//NOTE: there is also a PHP version function, we could use to automate if even more...
//$ver = phpversion();

//NOTE:  use preg_replace('/$mySearchString/s', $myReplcementString, $myFileIntoString)

if ($_POST['ca'] == "convert")
{
	$fileContents;
	$newFileContents;

	switch($_POST['php'])
	{
		case "php_4":
			echo "converting to PHP Version 4\n";
			foreach($files as $file)
			{
				$fp = fopen($file, "r+");
				$fileContents = file_get_contents($file);
				fclose($fp);
				$nc1 = preg_replace('/public/s', "var", $fileContents);
				$nc2 = preg_replace('/\tpublic/s', "\tvar", $nc1);
				$nc3 = preg_replace('/^\tprivate ([^;]+);/m',  "\tvar ".'\1;', $nc2);
				$fp = fopen($file, "w+");
				fwrite($fp, $nc3);
				fclose($fp);
				
			}
		break;
		case "php_5":
			echo "converting to PHP Version 5\n";
			foreach($files as $file)
			{
				$fp = fopen($file, "r+");
				$fileContents = file_get_contents($file);
				fclose($fp);
				$nc1 = preg_replace('/var/s', "public", $fileContents);
				$nc2 = preg_replace('/\tvar/s', "\tpublic", $nc1);
				$fp = fopen($file, "w+");
				fwrite($fp, $nc2);
				fclose($fp);
			}
		break;
	}
}
?>
</body>
</html>
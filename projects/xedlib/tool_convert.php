<form action="php_convert.php" method="post">
<table align="center" border="2">
<tr>
	<td nowrap="nowrap">make PHP 4 compatible
	</td>
	<td nowrap="nowrap"><input type="radio" checked="checked" name="php" value="php_4" />
	</td>
</tr>
<tr>
	<td nowrap="nowrap">make PHP 5 compatible
	</td>
	<td nowrap="nowrap"><input type="radio" name="php" value="php_5" />
	</td>
</tr>

<tr>
	<td align="center" colspan="2"><input type="submit" name="btnSubmit" value="Convert" />
	</td>
</tr>
</table>
<input type="hidden" name="ca" value="convert" />
</form>




<?php 

$files = array("h_data.php", 
				   "h_data_mssql.php", 
				   "h_display.php",
				   "h_template.php",
				   "h_utility.php");
//NOTE:  we will need something different for h_template, becuase that has
//some public HTML stuff in their we don't want changed.

//NOTE: there is also a PHP version function, we could use to automate if even more...
//$ver = phpversion();

//NOTE:  use preg_replace('/$mySearchString/s', $myReplcementString, $myFileIntoString)


if($_POST['ca'] == "convert")
{
	$fileContents;
	$newFileContents;
	
	switch($_POST['php'])
	{
		case "php_4":
			echo "converting to PHP Version 4\n";
			foreach($files as $file)
			{
				
				$fileContents = file_get_contents($file);
				$newFileContents = preg_replace('/public/s', "var", $fileContents);
				file_put_contents($file, $newFileContents);
				$newFileContents = preg_replace('/\tpublic/s', "\tvar", $fileContents);
				file_put_contents($file, $newFileContents);
			}
		break;
		
		case "php_5":
			echo "converting to PHP Version 5\n";
			foreach($files as $file)
			{
				
				$fileContents = file_get_contents($file);
				$newFileContents = preg_replace('/var/s', "public", $fileContents);
				file_put_contents($file, $newFileContents);
				$newFileContents = preg_replace('/\tvar/s', "\tpublic", $fileContents);
				file_put_contents($file, $newFileContents);
			}
		break;
	}
	//foreach($files as $file)
}
?>
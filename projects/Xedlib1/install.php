<?

require_once("h_Data.php");
require_once("h_Utility.php");
require_once("h_Display.php");
require_once("h_Module.php");

$auth = LoadModule("Authentication");

$ca = GetVar("ca", "none");

if (file_exists("config.php"))
{
	@mysql_select_db($g_db);
	if (mysql_error())
	{
		echo "Database does not exist, creating it...<br/>\n";
		mysql_query("SQL CREATE DATABASE $g_db");
		if (mysql_error())
		{
			echo "Unable to create the database: " . mysql_error() . "<br/>\n";
		}
	}

	if (!$auth->authenticated) { echo $auth->Present(); die(); }
}

if ($ca == "install")
{
	$fp = fopen("config.php", "w");
	fwrite($fp, "<?\n");
	fwrite($fp, "\$g_host = \"" . GetVar("host") . "\";\n");
	fwrite($fp, "\$g_user = \"" . GetVar("user") . "\";\n");
	fwrite($fp, "\$g_pass = \"" . GetVar("pass") . "\";\n");
	fwrite($fp, "\$g_db   = \"" . GetVar("data") . "\";\n");
	fwrite($fp, "?>\n");
	fclose($fp);

	echo "Installation has been saved to config.php.<br/>\n";
	echo "Your database password is stored in plaintext within this file so make sure nobody who has local access to your filesystem can download, view or access this file.<br/>";
	echo "Now we will need to do some checks, so proceed back to the <a href=\"install.php\">installation</a> to make sure everything is in order.<br/>\n";
}
else
{
	$formInstall = new Form("formInstall");
	$formInstall->AddHidden("ca", "install");
	$formInstall->AddInput("Database Host:",     "text" ,    "host");
	$formInstall->AddInput("Database Username:", "text",     "user");
	$formInstall->AddInput("Database Password:", "password", "pass");
	$formInstall->AddInput("Database Name:",     "text",     "data");
	$formInstall->AddInput("",                   "submit",   "butSubmit", "Install");
	echo GetBox("Installation", $formInstall->Get('method="post"'));
}

?>

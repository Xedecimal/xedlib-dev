<?php
require_once("h_data.php");
require_once("h_display.php");
require_once("h_utility.php");
require_once("h_template.php");

$db = new Database("xldemo", "localhost", "root", "ransal");
$db->CheckInstall();

?>

<title> XedLib Demonstration. </title>
<link href="main.css" rel="stylesheet" type="text/css">
<body bgcolor="#FFFFFF">

<h2>DataSet</h2>

<?php

echo "Gathering data<br>\n";
$ds = new DataSet($db, "authentication");
//$ds->CheckInstall();
//$rows = $ds->Get();

?>

<h2> Display </h2>

<b>Tabs</b>
<?php

$tabMain = new Tabs();
if (GetVar("ct") == 1)
{
	$tabBody = "Contents of the second tab!";
}
else $tabBody = "Contents of the first tab.";
echo $tabMain->GetTabs(array("Tab 1", "Tab Two"), $tabBody);

?>

<br/><br/><b>Box</b>

<?php
echo GetBox("box_example", "The Title", "And the body", "boxtemp.html");
?>

<br/><br/><b>Table</b>

<?php
$tblTest = new Table("tblTest", array("Hello", "World"));
$tblTest->AddRow(array("0x0", "1x0"));
$tblTest->AddRow(array("0x1", "1x1"));
echo $tblTest->Get();
?>

<br/><br/><b>SortTable</b>

<?php
$tblSort = new SortTable("tblSort", array("Hello World"));
$tblSort->AddRow(array("0x0", "1x0"));
$tblSort->AddRow(array("0x1", "1x1"));
echo $tblSort->Get();
?>

<br/><br/><b>Form</b>

<?php
$form = new Form("Test Form", 'action="xlDemo.php"', "Submit Button");
$form->AddHidden("hiddenvar", "hiddenvalue");
$form->AddInput("Text here:", "text",   "value",     "Value",                                         "size=50",            null);
$form->AddInput("Area!:",     "area",   "body",      "And values! Just like any other input object!", 'rows="5" cols="38"', null);
$form->AddInput("",           "submit", "butSubmit", "Test it");

echo $form->Get();
?>

<br/><br/><b>Calendar</b>

<?php

$cal = new Calendar();
$cal->AddItem(0, time()+86400,  time()+345600, "One Day Ahead",    "http://www.google.com", "This entry is one day ahead and spans 5 days.");
$cal->AddItem(0, time()+172800, time()+259200, "Two Days Ahead",   "http://www.google.com", "This entry is two days ahead and spans 4 days.");
$cal->AddItem(0, time()+259200, time()+259200, "Three Days Ahead", "http://www.google.com", "This entry is three days ahead and spans 3 days.");
$cal->AddItem(0, time()+345600, time()+432000, "Four Days Ahead",  "http://www.google.com", "This entry is four days ahead and spans 2 days.");
$cal->AddItem(0, time()+432000, time()+432000, "Five Days Ahead",  "http://www.google.com", "This entry is five days ahead and spans 1 day.");
echo $cal->Get();
echo $cal->GetVert();

?>

<h2> Template </h2>

<?php
$t = new Template();
$t->set("box_title", "A title!");
$t->set("box_body", "And some bawday!");
echo $t->Get("template_box.html");
?>

<h2> Miscelaneous </h2>

<b> Date Offsets </b><br/><br/>

<?php

$d = time()-2678400;
echo GetDateOffset($d).'<br/>';
$d = time()-432000;
echo GetDateOffset($d).'<br/>';
$d = time()-1800;
echo GetDateOffset($d).'<br/>';

?>

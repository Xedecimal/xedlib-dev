<?php

require_once('h_main.php');
require_once('lib/a_billderator.php');

session_start();

$ca = GetVar('ca');

$dsTest = new DataSet($db, 'test');

echo <<<EOF
<html>
<head>

<title> Tier Test </title>
<link href="main.css" rel="stylesheet" type="text/css" />
<style type="text/css">
.menu
{
	float: left;
}
</style>

<script type="text/javascript" src="lib/js/helper.js"></script>

</head>
<body>
EOF;

echo '<h3>Construction</h3>';

isset($_SESSION['sessForm']) ? $b = $_SESSION['sessForm'] : $b = new Billderator('test');
$b->Prepare($ca);
echo $b->Get($me);

$_SESSION['sessForm'] = $b;

echo '<h3>Editor</h3>';

$ed = new EditorData('edTest', $dsTest);
$ed->Prepare($ca);
echo EditorData::GetUI($me, $ed->Get($me));

echo '<h3>Display</h3>';

echo <<<EOF
</body>
</html>
EOF;

?>
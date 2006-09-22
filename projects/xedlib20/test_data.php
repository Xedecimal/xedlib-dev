<?php

require_once('h_data.php');
require_once('h_display.php');

$db = new Database('test', 'localhost', 'root', 'ransal');
$ds = new DataSet($db, 'test');

$edTest = new EditorData(''));

$items = $ds->Get();
if (!empty($items)) foreach ($items as $item)
{
	echo "Item: {$item['name']}<br/>\n";
}

?>
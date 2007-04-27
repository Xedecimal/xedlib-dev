<?php

$db = new Database('mydatabase', 'localhost', 'root');
$ds = new DataSet($db, 'mytable');
$id = $ds->Add(array('name' => 'Value here'));
$ds->Remove(array('id' => $id));

?>
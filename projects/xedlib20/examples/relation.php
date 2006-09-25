<?php

$db = new Database('mysql://root:password@localhost/database');
$dsParent = new DataSet($db, 'parent');
$dsChild = new DataSet($db, 'child');

//`child` table has a column called 'parent', this is not the table name.
$dsParent->AddChild(new Relation($dsChild, 'id', 'parent'));

$id = $dsParent->Add(array('name' => 'My parent'));
$dsChild->Add(array('name' => 'My Child', 'parent' => $id));

//This will then remove the parent AND the child due to the association.
$dsParent->Remove(array('id' => $id));

?>
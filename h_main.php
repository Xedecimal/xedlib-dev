<?php

require_once('lib/classes/Server.php');
require_once('lib/classes/File.php');
require_once('lib/classes/data/Database.php');
require_once('lib/classes/data/DataSet.php');

$_d['settings']['database'] = 'mysqli://root:ransal@127.0.0.1/test';

$db = new Database();
$db->Open($_d['settings']['database']);

?>
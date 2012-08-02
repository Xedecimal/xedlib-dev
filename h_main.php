<?php

require_once('lib/classes/server.php');
require_once('lib/classes/file.php');
require_once('lib/classes/data/database.php');
require_once('lib/classes/data/data_set.php');

$_d['settings']['database'] = 'mysqli://root:ransal@127.0.0.1/test';

$db = new Database();
$db->Open($_d['settings']['database']);
$_d['db'] = $db;

?>
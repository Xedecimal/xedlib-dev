<?php

require_once('h_main.php');
require_once('lib/a_gallery.php');

$g = new Gallery('test/Gallery');
$page_title = 'Gallery Test';
$page_body = $g->Get(GetVar('galcf'));

$t = new Template();
echo $t->Get('template_test.html');

?>
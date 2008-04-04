<?php

require_once('h_main.php');
require_once('lib/a_gallery.php');

$g = new Gallery('test/Gallery');
$page_head .= <<<EOF
<link href="lib/css/shadowbox.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="lib/js/jquery.js"></script>
<script type="text/javascript" src="lib/js/shadowbox-jquery.js"></script>
<script type="text/javascript" src="lib/js/shadowbox.js"></script>
<script type="text/javascript">
$(document).ready(function () {
	Shadowbox.init({
		handleLgImages: 'drag'
	});
});
</script>
EOF;
$page_title = 'Gallery Test';
$page_body = $g->Get(GetVar('galcf'));

$t = new Template();
echo $t->Get('template_test.html');

?>
<?php

require_once('../lib/h_utility.php');

$doc = new DOMDocument();
$doc->load('output/index.xml');

$x = new DOMXPath($doc);

$classes = $x->evaluate('/root/class');

foreach ($classes as $class)
{
	varinfo($class);
}

?>
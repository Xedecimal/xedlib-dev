<?php

$cal = new Calendar();
$cal->AddItem(time(), time()+6000, 'Some content here.');
echo $cal->Get();

//Returns a calendar display.

?>
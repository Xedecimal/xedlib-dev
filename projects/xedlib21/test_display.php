<?php

require_once('lib/h_template.php');
require_once('lib/h_utility.php');
require_once('lib/h_display.php');
require_once('lib/a_calendar.php');
require_once('lib/a_validation.php');

global $me;

//An image to display next to all errors.
$imgError = '<img src="lib/images/error.png" style="vertical-align: text-bottom" alt="Error" />';

//Make sure that this validation matches anthing@anything.anything .
$vEmail = new Validation('email', '.+@.+\..+',
	$imgError.' Email address should be in the format name@address.com');

//Same for phone, matching any series of 3 numbers, 3 times, with anything between, matches
//(555) 555-5555
//555.555.5555
//5555555555
//etc
$vPhone = new Validation('phone', '.*([0-9]{3}).*([0-9]{3}).*([0-9]{4}).*',
	$imgError.' Invalid phone number.');

//Address is just forced to not be blank. (at least 1 char)
$vAddress = new Validation('address', '.+',
	$imgError.' You must enter an address');

//Parent validation, only if this one succeeds, will all children validations be checked.
$vContact = new Validation('contact', '[^0]+',
	$imgError.' You must select a contact.');

$cboxes = array(
	'You need to',
	'fill out one',
	'of these items'
);

$vChecks = new Validation('vchecks', array($cboxes, 2),
	$imgError.'You must select at least 2.');

$vContact->Add('email', $vEmail);
$vContact->Add('phone', $vPhone);
$vContact->Add('mail', $vAddress);

//Validation must be prepared. This generates nessecary spans and javascript to
//use the generated spans, you must always display the error value whether it
//has passed or not or the javascript will not work.
$ArrayV = null;
$array_passed = FormValidate('formArray', array($vEmail, $vPhone, $vAddress, $vChecks),
	$ArrayV, GetVar('cav') == 'send');
$RecurseV = null;
$recurse_passed = FormValidate('formRecurse', $vContact, $RecurseV, GetVar('car') == 'send');

//Array for a dropdown list, will be converted using ArrayToSelOptions().
$contacts = array(
	0 => 'None',
	'email' => 'Email',
	'phone' => 'Phone',
	'mail' => 'Mail'
);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Presentation Tests</title>
	<link href="main.css" type="text/css" rel="stylesheet" />
	<script type="text/javascript">
//<![CDATA[
<?=$ArrayV['js']?>
<?=$RecurseV['js']?>
//]]>
	</script>
</head>
<body>

<?
$frm = new Form('formArray');

//Associate the parent level validation object to this form, you should also
//be able to associate an array of validation objects, eg.
//$frm->Validation = array($v1, $v2, $v3).
$frm->Validation = array($vEmail, $vPhone, $vAddress, $vChecks);
$frm->Errors = $ArrayV['errors'];
$frm->AddHidden('cav', 'send');
$frm->AddInput(
	new FormInput('Email', 'text', 'email'),
	new FormInput('Phone',   'text', 'phone'),
	new FormInput('Address', 'text', 'address')
);
$frm->AddInput(new FormInput('Checkboxes:', 'checks', 'vchecks',
	ArrayToSelOptions($cboxes)));
$frm->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Send'));
echo GetBox('box_test', 'Form With Array Validation',
	$frm->Get('action="'.$me.'" method="post"'), 'templates/box.html');

$frm = new Form('formRecurse');
$frm->AddHidden('car', 'send');

//Associate the parent level validation object to this form, you should also
//be able to associate an array of validation objects, eg.
//$frm->Validation = array($v1, $v2, $v3).
$frm->Validation = $vContact;
$frm->Errors = $RecurseV['errors'];
$frm->AddInput(new FormInput('Contact method', 'select', 'contact',
	ArrayToSelOptions($contacts)));
$frm->AddInput(
	new FormInput('Email:', 'text', 'email'),
	new FormInput('Phone:',   'text', 'phone'),
	new FormInput('Address:', 'text', 'address'));
$frm->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Send'));
echo GetBox('box_test2', 'Form With Recursive Validation',
	$frm->Get('action="'.$me.'" method="post"'), 'templates/box.html');

//Widgets

$sels = array(
	new SelOption('Item 1'),
	new SelOption('Item 2'),
	new SelOption('Group 1', true),
	new SelOption('Item 3'),
	new SelOption('Item 4'),
	new SelOption('Item 5'),
	new SelOption('Group 2', true),
	new SelOption('Item 6'),
	new SelOption('Item 7')
);

$frm = new Form('frmItems');
$frm->AddInput(new FormInput('Array Based1', 'text', 'textinput[0]'));
$frm->AddInput(new FormInput('Array Based2', 'text', 'textinput[1]'));
$frm->AddInput(new FormInput('Password', 'password', 'pass'));
$frm->AddInput(new FormInput('File', 'file', 'file'));
$frm->AddInput(new FormInput('Yes / No<br/>', 'yesno', 'yesno'));
$frm->AddInput(new FormInput('Select', 'select', 'select', $sels));
$frm->AddInput(new FormInput('Check', 'checkbox', 'check'));
$frm->AddInput(new FormInput('Checks', 'checks', 'checks', $sels));
$frm->AddInput(new FormInput('Selects', 'selects', 'selects', $sels));
$frm->AddInput(new FormInput('Date', 'date', 'date'));
$frm->AddInput(new FormInput('Time', 'time', 'time'));
$frm->AddInput(new FormInput('DateTime', 'datetime', 'datetime'));
$frm->AddInput(new FormInput('Area', 'area', 'area', null, 'rows="5" cols="30"'));
$frm->AddInput(new FormInput('Spam Blocker', 'spamblock', 'blocker'));
$frm->AddInput(new FormInput('Submit', 'submit', 'submit', 'Submit'));

echo GetBox('box_test3', 'Testing widgets',
	$frm->Get('action="'.$me.'" method="post"'), 'templates/box.html');

//Calendar

$cal = new Calendar();

$tsnow = mktime(1, 1, 1, date('n'), date('j'), date('Y'));
$ts3ahead = mktime(1, 1, 1, date('n'), date('j')+3, date('Y'));
$ts3behind = mktime(1, 1, 1, date('n'), date('j')-3, date('Y'));

$cal->AddItem($tsnow, $ts3ahead, '4 day span ahead of now.');
$cal->AddItem($ts3behind, $tsnow, '4 day span behind now.');
$cal->AddItem($ts3behind, $ts3ahead, '8 day span behind to ahead.');

echo GetBox('box_test4', 'Calendar Horizontal', $cal->Get(), 'templates/box.html');
echo GetBox('box_test4', 'Calendar Vertical', $cal->GetVert(), 'templates/box.html');

?>

<h3> Source for the above. </h3>

<? highlight_file(__FILE__); ?>

</body>
</html>
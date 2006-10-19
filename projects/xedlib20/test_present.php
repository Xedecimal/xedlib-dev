<?php

require_once('lib/h_utility.php');
require_once('lib/h_display.php');

$imgError = '<img src="lib/images/error.png" style="vertical-align: text-bottom" alt="Error" />';

$vContact = new Validation('contact', '[^0]+', $imgError.' You must select a contact.');
$vContact->Add('email', new Validation('email', '.+@.+\..+',
	$imgError.' Email address should be in the format name@address.com'));
$vContact->Add('phone', new Validation('phone', '.*([0-9]{3}).*([0-9]{3}).*([0-9]{4}).*',
	$imgError.' Invalid phone number.'));
$vContact->Add('mail', new Validation('address', '.+',
	$imgError.' You must enter an address'));
$validation = FormValidate('formTest', $vContact, GetVar('ca') == 'send');
$errors = $validation['errors'];

$contacts = array(
	0 => 'None',
	'email' => 'Email',
	'phone' => 'Phone',
	'mail' => 'Mail'
);

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
						"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Presentation Tests</title>
	<link href="main.css" type="text/css" rel="stylesheet" />
	<script type="text/javascript">
<?=$validation['js']?>
	</script>
</head>
<body>
<h3>Form With Validation</h3>

<?

$frm = new Form('formTest');
$frm->Validation = $vContact;
$frm->AddInput('Contact method', 'select', 'contact', ArrayToSelOptions($contacts));
$frm->AddInput('Email:',   'text', 'email');
$frm->AddInput('Phone:',   'text', 'phone');
$frm->AddInput('Address:', 'text', 'address');
$frm->AddInput(null, 'submit', 'butSubmit', 'Send');
echo GetBox('box_test', 'Form Test', $frm->Get('action="'.$me.'" method="post"'));

?>
</body>
</html>
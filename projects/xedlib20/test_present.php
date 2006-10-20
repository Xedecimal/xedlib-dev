<?php

require_once('lib/h_utility.php');
require_once('lib/h_display.php');

//An image to display next to all errors.
$imgError = '<img src="lib/images/error.png" style="vertical-align: text-bottom" alt="Error" />';

//Parent validation, only if this one succeeds, will all children validations be checked.
$vContact = new Validation('contact', '[^0]+', $imgError.' You must select a contact.');

//Add a new validation to the contact, if the value of contact is 'email', then make sure
//that this validation matches anthing@anything.anything .
$vContact->Add('email', new Validation('email', '.+@.+\..+',
	$imgError.' Email address should be in the format name@address.com'));

//Same for phone, matching any series of 3 numbers, 3 times, with anything between, matches
//(555) 555-5555
//555.555.5555
//5555555555
//etc
$vContact->Add('phone', new Validation('phone', '.*([0-9]{3}).*([0-9]{3}).*([0-9]{4}).*',
	$imgError.' Invalid phone number.'));

//Address is just forced to not be blank. (at least 1 char)
$vContact->Add('mail', new Validation('address', '.+',
	$imgError.' You must enter an address'));

//Validation must be prepared. This generates nessecary spans and javascript to use
//the generated spans, you must always display the error value whether it has
//passed or not or the javascript will not work.
$validation = FormValidate('formTest', $vContact, GetVar('ca') == 'send');

//Store the errors from the validation
$errors = $validation['errors'];

//Array for a dropdown list, will be converted using ArrayToSelOptions().
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

//Associate the parent level validation object to this form, you should also
//be able to associate an array of validation objects, eg. $frm->Validation = array($v1, $v2, $v3).
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
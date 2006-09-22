<?php

require_once('lib/h_utility.php');
require_once('lib/h_display.php');

$imgError = '<img src="lib/images/error.png" style="vertical-align: text-bottom" alt="Error" />';

$vContact = new Validation('contact', '.+', $imgError.' You must select a contact.');
$vContact->Add('email', new Validation('email', '.+@.+\..+',
	$imgError.' Email address should be in the format name@address.com'));
$vContact->Add('phone', new Validation('phone', '.*([0-9]{3}).*([0-9]{3}).*([0-9]{4}).*',
	$imgError.' Invalid phone number.'));
$vContact->Add('mail', new Validation('address', '.+',
	$imgError.' You must enter an address'));
$validation = FormValidate('formContact', $vContact, GetVar('ca') == 'send');
$errors = $validation['errors'];

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
<h3>Form Validation</h3>
<form action="<?=$me?>">
Contact by:
<select id="contact" name="contact">
	<option value="">None</option>
	<option value="email">Email</option>
	<option value="phone">Phone</option>
	<option value="mail">Mail</option>
</select> <?=$errors['contact']?><br/>
Email: <input type="text" id="email" name="email" /> <?=$errors['email']?><br />
Phone: <input type="text" id="phone" name="phone" /> <?=$errors['phone']?><br />
Address: <input type="text" id="address" name="address" /> <?=$errors['address']?><br />
<input type="submit" value="Send" onclick="return formContact_check(1);" />
</form>
</body>
</html>
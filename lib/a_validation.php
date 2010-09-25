<?php

/**
 * Processes validation on a form by means of regular expressions in javascript
 * and server-side php.
 * @see Form
 * @example test_display.php
 */
class Validation
{
	/**
	 * Gets javascript code to do client-side validation.
	 *
	 * @param string $form Form containing validation to be used.
	 * @return string
	 */
	static function GetJS($valids)
	{
		$ret = <<<EOF

window.checks = [];

$(function () {

EOF;
		foreach ($valids as $id => $rexmsg)
		{
			list($rex, $msg) = $rexmsg;
			$jname = preg_replace('/(:|\.|\[|\])/', '\\\\\1',$id);
			$iname = str_replace('\\', '\\\\', $jname);
			$sel = "input[name=$jname]";
			$ret .= <<<EOF
window.checks['check_$sel'] = function () {
	$('#{$iname}_error').remove();
	if (!$(this).val().match($rex))
	{
		$('<span id="{$jname}_error">$msg</span>').addClass('form-error').insertAfter('$sel');
		$(window).scrollTop($(this).position().top)
		return false;
	}
	return true;
};

$('$sel').blur(window.checks['check_$sel']);

EOF;
		}
$ret .= <<<EOF
});

EOF;
		return $ret;
	}
}

?>
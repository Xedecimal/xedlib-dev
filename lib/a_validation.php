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
	static function GetJS($form)
	{
		$ret = <<<EOF
<script type="text/javascript">
$(function () {
EOF;
		foreach ($form->inputs as $in)
		{
			if ($in->valid)
			{
				$id = $in->atrs['ID'];
				$rex = $in->valid;
				$msg = $in->invalid;
				$ret .= <<<EOF
$('#$id').after('<div id="$id-error" class="form-error"></div>');
$('#$id').blur(function () {
	if (!$(this).val().match($rex))
		$('#$id-error').text('X $msg').addClass('form-error');
	else
		$('#$id-error').text('').removeClass('form-error');
});
EOF;
			}
		}
$ret .= <<<EOF
});
</script>
EOF;
		return $ret;
	}
}

?>
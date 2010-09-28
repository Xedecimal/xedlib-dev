<?php

class ModEmail extends Module
{
	public $Name = 'email';
	protected $_template;

	function __construct()
	{
		$this->CheckActive($this->Name);
		$this->_template = l('temps/email.xml');
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'submit')
		{
			$this->_from = GetVar('from');
			$t = new Template();
			$t->use_getvar = true;

			$headers[] = 'From: '.$this->_from;
			$headers[] = 'Reply-To: '.$this->_from;

			mail($this->_to, $this->_subject,
				$t->ParseFile($this->_email_template),
				implode($headers, "\r\n"));
		}
	}

	function Get()
	{
		if (!$this->Active) return;
		$t = new Template;
		$t->Set($this);
		return $t->ParseFile($this->_template);
	}
}

?>

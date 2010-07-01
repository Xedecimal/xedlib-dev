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

		if (@$_d['q'][1] == 'send')
		{
			$t = new Template();
			die($t->ParseFile($this->_email_template));
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

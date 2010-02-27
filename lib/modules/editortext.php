<?php

Module::RegisterModule('ModEditorText');

class ModEditorText extends Module
{
	/** @var EditorText */
	private $ed;

	function __construct()
	{
		$this->ed = new EditorText('editortext', 'content/faq.xml');
	}

	function Prepare()
	{
		return $this->ed->Prepare();
	}

	function Get()
	{
		return $this->ed->Get($GLOBALS['me']);
	}
}

?>

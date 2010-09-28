<?php

require_once('xedlib/a_file.php');

class ModFile extends Module
{
	protected $Name = 'file';
	protected $Text = 'Files';
	protected $Path = 'files';
	protected $Type = array('Default');

	function __construct()
	{
		global $_d;

		parent::__construct();

		$this->CheckActive($this->Name);

		if (!$this->Active) return;

		$this->fm = new FileManager('fmGallery', $this->Path, $this->Type);
		$this->fm->Behavior->Target = "{$_d['app_abs']}/{$this->Name}";
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if (ModUser::RequireAccess(2))
			$this->fm->Behavior->AllowAll();

		$this->fm->Prepare();
	}

	function Get()
	{
		global $_d;

		if (!$this->Active) return;

		# Administration

		return $this->fm->Get();
	}
}

?>

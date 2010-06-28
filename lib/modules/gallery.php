<?php

require_once('xedlib/a_file.php');

class ModGallery extends Module
{
	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'gallery') return;

		require_once('xedlib/a_gallery.php');
		$gal = new Gallery('galimg');
		global $me;

		$me .= $_d['q'][0];
		return $gal->Get(GetVar('galcf'));
	}
}

Module::Register('ModGallery');

class ModGalleryAdmin extends Module
{
	/**
	* Gallery based file manager instance.
	*
	* @var FileManager
	*/
	private $fm;

	function __construct()
	{
		global $me, $_d;
		if ($_d['q'][0] != 'gallery') return;

		$this->fm = new FileManager('fmGallery', 'galimg', array('Gallery'));
		$this->fm->Behavior->Target = $me.'/gallery';
	}

	function Link()
	{
		global $_d, $me;

		if ($_d['q'][0] == 'admin') $_d['user.login'] = true;
		if (!ModUser::RequireAccess(2)) return;

		$_d['nav.links']->AddChild(new TreeNode('Gallery', '{{app_abs}}/gallery'));
	}

	function Prepare()
	{
		global $_d;
		if ($_d['q'][0] != 'gallery') return;

		$this->fm->Behavior->AllowAll();
		$this->fm->Prepare();
	}

	function Get()
	{
		global $_d;
		if ($_d['q'][0] != 'gallery') return;

		if (!ModUser::RequireAccess(2)) return;
		return $this->fm->Get();
	}
}

Module::Register('ModGalleryAdmin');

?>

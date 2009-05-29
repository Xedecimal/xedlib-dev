<?php

Module::RegisterModule('ModUser');
Module::RegisterModule('ModUserAdmin');

class ModUser extends Module
{
	static function RequireAccess($level)
	{
		if ($GLOBALS['_d']['cl']['usr_access'] >= $level) return true;
		return false;
	}

	function __construct()
	{
		global $_d;

		require_once(dirname(__FILE__).'/../h_data.php');

		$_d['user.ds'] = new DataSet($_d['db'], 'user', 'usr_id');
	}

	function PreLink()
	{
		global $_d;

		$this->lm = new LoginManager('lmAdmin');
		$this->lm->AddDataSet($_d['user.ds'], 'usr_pass', 'usr_name');
		$_d['cl'] = $this->lm->Prepare();
	}

	function Link()
	{
		global $_d, $me;

		if (ModUser::RequireAccess(1))
			$_d['nav.links']['Log Out'] = "{$me}/{$this->lm->Name}/logout";
	}

	function Get()
	{
		global $_d;

		$out = null;

		if (ModUser::RequireAccess(1))
			$out .= "Welcome, {$_d['cl']['usr_name']}<br/>\n";
		else
		{
			$out .= $this->lm->Get();
			$out .= RunCallbacks(@$_d['user.callbacks.knee']);
			return GetBox('box_user', 'Login', $out);
		}
		return $out;
	}
}

class ModUserAdmin extends Module
{
	/**
	* Associated data editor for the user table.
	*
	* @var EditorData
	*/
	private $edUser;

	private $selAccess;

	function __construct()
	{
		global $_d, $me;

		require_once(dirname(__FILE__).'/../a_editor.php');
		require_once(dirname(__FILE__).'/../h_display.php');

		$this->selAccess = ArrayToSelOptions(array(1 => 'User', 2 => 'Admin'));
		$_d['user.ds']->Description = 'User';
		$_d['user.ds']->DisplayColumns = array(
			'usr_name' => new DisplayColumn('Name'),
			'usr_access' => new DisplayColumn('Access', 'socallback')
		);
		$_d['user.ds']->FieldInputs = array(
			'usr_name' => new FormInput('Name'),
			'usr_pass' => new FormInput('Password', 'password'),
			'usr_access' => new FormInput('Access', 'select', null,
				$this->selAccess)
		);
		$this->edUser = new EditorData('user', $_d['user.ds']);
		$this->edUser->Behavior->Target = $me.'/user';
		$this->edUser->Behavior->Search = false;
	}

	function Link()
	{
		global $_d;

		if (ModUser::RequireAccess(2))
			$_d['nav.links']['Users'] = $_d['tempurl'].'/user';
	}

	function Prepare()
	{
		global $_d;

		if (@$_d['q'][0] != 'user') return;
		$this->edUser->Prepare();
	}

	function Get()
	{
		global $_d;

		if (@$_d['q'][0] != 'user') return;
		return $this->edUser->GetUI();
	}
}

?>

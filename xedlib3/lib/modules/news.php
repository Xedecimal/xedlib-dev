<?php

Module::RegisterModule('ModNews');
Module::RegisterModule('ModNewsAdmin');

class ModNews extends Module
{
	public $Block = 'news';

	function __construct()
	{
		global $_d;
		if ($_d['q'][0] != $this->Name) return;

		$_d['news.ds'] = new DataSet($_d['db'], 'news', 'nws_id');
	}

	function TagNews($t, $g)
	{
		global $_d;
		if ($_d['q'][0] != $this->Name) return;

		if (empty($_d['q'][1]))
		{
			$items = $_d['news.ds']->Get();
			$vp = new VarParser();
			$ret = '';
			foreach ($items as $i) $ret .= $vp->ParseVars($g, $i);
			return $ret;
		}
	}

	function TagNewsDetail($t, $g)
	{
		global $_d;
		if ($_d['q'][0] != $this->Name) return;

		if (!empty($_d['q'][1]))
		{
			$item = $_d['news.ds']->GetOne(array('nws_id' => $_d['q'][1]));
			$vp = new VarParser();
			return $vp->ParseVars($g, $item);
		}
	}

	function Get()
	{
		$t = new Template();
		$t->ReWrite('news', array(&$this, 'TagNews'));
		$t->ReWrite('newsdetail', array(&$this, 'TagNewsDetail'));
		return $t->ParseFile('t_news.xml');
	}
}

class ModNewsAdmin extends Module
{
	/**
	* Associated news editor.
	*
	* @var EditorData
	*/
	private $edNews;

	function __construct()
	{
		require_once('xedlib/a_editor.php');
		global $_d;

		if (empty($_d['news.ds']))
			$_d['news.ds'] = new DataSet($_d['db'], 'news', 'nws_id');

		$this->edNews = new EditorData('edNews', $_d['news.ds']);
		$this->edNews->Behavior->Search = false;
	}

	function Link()
	{
		global $_d, $me;

		if (!ModUser::RequireAccess(2)) return;
		$_d['nav.links']['News'] = $me.'/news';
	}

	function Prepare()
	{
		global $_d;

		$_d['news.ds']->Description = 'News';
		$_d['news.ds']->DisplayColumns = array(
			'nws_title' => new DisplayColumn('Title')
		);
		$_d['news.ds']->FieldInputs = array(
			'nws_title' => new FormInput('Title'),
			'nws_body' => new FormInput('Body', 'area')
		);

		global $me;
		$this->edNews->Behavior->Target = $me.'/news';
		$this->edNews->Prepare();
	}

	function Get()
	{
		global $_d;
		if ($_d['q'][0] != 'news') return;
		return $this->edNews->GetUI('edNews');
	}
}

?>

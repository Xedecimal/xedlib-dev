<?php

class ModArticles extends Module
{
	public $Block = 'articles';
	public $Name = 'articles';

	protected $_template;

	function __construct()
	{
		global $_d;
		$this->_template = l('temps/mod_articles.xml');
	}

	function TagArticle($t, $g)
	{
		$vp = new VarParser();
		foreach ($this->_map as $k => $v)
		{
			if (is_array($v))
				$this->_article[$k] = RunCallbacks($v, $this->_article);
			else $this->_article[$k] = $this->_article[$v];
		}
		return $vp->ParseVars($g, $this->_article);
	}

	function TagArticles($t, $g)
	{
		$t->ReWrite('article', array($this, 'TagArticle'));
		if (isset($this->_source)) $this->_articles = $this->_source->Get();
		if (!empty($this->_articles))
		{
			foreach ($this->_articles as $a)
			{
				$this->_article = $a;
				@$ret .= $t->GetString($g);
			}
			return $ret;
		}
	}

	function Get()
	{
		$t = new Template();
		$t->ReWrite('articles', array($this, 'TagArticles'));
		$t->Set('foot', @$this->_foot);
		$t->Behavior->Bleed = false;
		return $t->ParseFile($this->_template);
	}
}

class ModArticle extends Module
{
	public $Block = 'article';
	public $Name = 'article';

	protected $_template;

	function __construct()
	{
		global $_d;
		$this->_template = l('temps/mod_article.xml');
	}

	function TagNews($t, $g)
	{
		global $_d;
		if ($_d['q'][0] != $this->Name) return;

		if (empty($_d['q'][1]))
		{
			$items = $_d['news.ds']->Get();
			$vp = new VarParser();
			$ret = null;
			foreach ($items as $i) $ret .= $vp->ParseVars($g, $i);
			return $ret;
		}
	}

	function TagNewsDetail($t, $g)
	{
		global $_d;
		if ($_d['q'][0] != $this->Name) return;

		$ci = $_d['q'][1];

		if (!empty($ci))
		{
			$query = array('match' => array('nws_id' => $ci));
			$item = $_d['news.ds']->GetOne($query);
			$vp = new VarParser();
			return $vp->ParseVars($g, $item);
		}
	}

	function Get()
	{
		$t = new Template();
		$t->ReWrite('newsdetail', array(&$this, 'TagNewsDetail'));
		return $t->ParseFile($this->_template);
	}
}

class ModArticleAdmin extends Module
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

		if (@$_d['q'][1] != 'news') return;

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
		$this->edNews->Behavior->Target = p('news');
		$this->edNews->Prepare();
	}

	function Get()
	{
		global $_d;
		return $this->edNews->GetUI('edNews');
	}
}

?>

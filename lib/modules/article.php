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
		if (isset($this->_source)) $this->_articles = $this->_source->Get(
			array('sort' => array($this->_source->id => 'DESC')));
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
		if (ModUser::RequireAccess(1)) return;
		$t = new Template($GLOBALS['_d']);
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
		if (empty($this->_source))
			$this->_source = new DataSet($_d['db'], $this->Name, $this->ID);
	}

	function TagNews($t, $g)
	{
		global $_d;
		if ($_d['q'][0] != $this->Name) return;

		if (empty($_d['q'][1]))
		{
			$items = $this->_source->Get();
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

		$ci = @$_d['q'][1];

		if (!empty($ci))
		{
			$query = array('match' => array($this->ID => $ci));
			$item = $this->_source->GetOne($query);
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

	protected $Name = 'news';
	protected $ID = 'nws_id';

	function __construct()
	{
		require_once('xedlib/a_editor.php');
		global $_d;

		if (empty($this->_source))
			$this->_source = new DataSet($_d['db'], $this->Name, $this->ID);
	}

	function Link()
	{
		global $_d, $me;

		if (!$this->Active) return;

		if (!ModUser::RequireAccess(2)) return;
		$_d['nav.links']['News'] = $me.'/news';
	}

	function Prepare()
	{
		global $_d;

		if (empty($this->_source->DisplayColumns))
		{
			$this->_source->Description = 'News';
			$this->_source->DisplayColumns = array(
				'nws_title' => new DisplayColumn('Title')
			);
			$this->_source->FieldInputs = array(
				'nws_title' => new FormInput('Title'),
				'nws_body' => new FormInput('Body', 'area')
			);
		}

		global $me;
		$this->edNews = new EditorData('edNews', $this->_source);
		$this->edNews->Behavior->Search = false;
		$this->edNews->Behavior->Target = p($this->Name);
		$this->edNews->Prepare();
	}

	function Get()
	{
		global $_d;

		if (!$this->Active) return;

		return $this->edNews->GetUI('edNews');
	}
}

?>

<?php

/**
 * @package Logging
 */

class LoggerAuth extends EditorHandler
{
	/**
	 * Enter description here...
	 *
	 * @var DataSet
	 */
	private $dsLog;

	/**
	 * User DataSet to identify actions to identities.
	 * @var DataSet
	 */
	private $dsUser;

	/**
	 * A series of possible actions that can be referenced later.
	 * @var array
	 */
	private $actions;

	public $name;

	/**
	 * Creates a new Logger.
	 * @param DataSet $dsLog DataSet that holds log information.
	 * @param DataSet $dsUser DataSet that holds identity information.
	 * @param array $actions A series of possible actions to log.
	 */
	function LoggerAuth($name, $dsLog, $dsUser, $actions = null)
	{
		if (!$dsLog instanceof DataSet) Error("LoggerAuth: Constructor requires
			argument 1 to be a DataSet.");
		if (!$dsUser instanceof DataSet) Error("LoggerAuth: Constructor requires
			argument 2 to be a DataSet.");

		$this->name = $name;
		$this->dsLog = $dsLog;
		$this->dsUser = $dsUser;
		$this->actions = $actions;
	}

	/**
	 * Method to log a single action.
	 * @param mixed $user Unique identifier of the source of the action.
	 * @param mixed $action Unique identifier of the action performed.
	 * @param string $target Target of the action.
	 */
	function Log($user, $action, $target)
	{
		$this->dsLog->Add(array(
			'log_date' => DeString('NOW()'),
			'log_user' => $user,
			'log_action' => $action,
			'log_target' => $target
		));
	}

	/**
	 * Trims the existing log information by date to the amount specified.
	 * @param int $count Amount of items to remain.
	 */
	function TrimByCount($count)
	{
		$this->dsLog->GetCustom("DELETE FROM {$this->dsLog->table}
			USING {$this->dsLog->table}
			LEFT JOIN (
				SELECT log_id FROM {$this->dsLog->table}
				ORDER BY log_date DESC
				LIMIT {$count}) AS dt
			ON {$this->dsLog->table}.log_id = dt.log_id
			WHERE dt.log_id IS NULL;");
	}

	function TrimByDate($ts)
	{
		$this->dsLog->GetCustom("DELETE FROM {$this->dsLog->table}
			WHERE log_date < '".TimestampToMySql($ts)."'");
	}

	/**
	 * Return a table of actions that have been recorded.
	 * @param int $count Amount of items to display per page.
	 * @return string Rendered table of actions.
	 */
	function Get($count = null, $user = null, $idcol = 'usr_id')
	{
		global $me;
		$match = $user != null ? array($idcol => $user) : null;
		$items = $this->dsLog->Get($match, array('log_date' => 'DESC'),
			$count != null ? array(0, $count) : null,
			array(new Join($this->dsUser, 'log_user = '.$this->dsUser->id, 'JOIN')));

		$tbl = new Table('tbl_logs', array('Date', 'User', 'Action', 'Target'));
		if (!empty($items))
		foreach ($items as $ix => $item)
		{
			$idlead = "{$this->name}_table:";
			$idend = ":{$ix}";

			$colDate = DateTimeCallback(MyDateTimestamp($item['log_date'], true));
			$colUser = '<a href="'.$me.'?editor=user&amp;ci='.$item['usr_id'].'">'.$item['usr_name'].'</a>';
			$colActn = isset($this->actions) ?
				$this->actions[$item['log_action']] : $item['log_action'];
			$colTrgt = $item['log_target'];

			$tbl->AddRow(array(
				array($colDate, array('id' => "{$idlead}log_date{$idend}")),
				array($colUser, array('id' => "{$idlead}usr_name{$idend}")),
				array($colActn, array('id' => "{$idlead}log_action{$idend}")),
				array($colTrgt, array('id' => "{$idlead}log_target{$idend}"))
			));
		}
		return $tbl->Get(array('class' => 'tablesorter', 'id' => $this->name.'_table'));
	}

	function GetFields(&$form, $id, $data)
	{
		if (!empty($id))
		{
			$form->AddInput(array('Actions' => $this->Get(null, $id)));
		}
	}
}

?>
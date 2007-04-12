<?php

class LoggerAuth
{
	/**
	 * Enter description here...
	 *
	 * @var DataSet
	 */
	private $dsLog;
	private $dsUser;
	private $actions;

	function LoggerAuth($dsLog, $dsUser, $actions = null)
	{
		if (!$dsLog instanceof DataSet) Error("LoggerAuth: Constructor requires
			argument 1 to be a DataSet.");
		if (!$dsUser instanceof DataSet) Error("LoggerAuth: Constructor requires
			argument 2 to be a DataSet.");

		$this->dsLog = $dsLog;
		$this->dsUser = $dsUser;
		$this->actions = $actions;
	}

	function Log($user, $action, $target)
	{
		$this->dsLog->Add(array(
			'log_date' => DeString('NOW()'),
			'log_user' => $user,
			'log_action' => $action,
			'log_target' => $target
		));
	}

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
		//$this->dsLog->Remove(null, array('DATE' => 'DESC'), array(0, 5));
	}

	function Get($count)
	{
		$items = $this->dsLog->Get(null, array('log_date' => 'DESC'),
			array(0, $count), array(new Join($this->dsUser, 'log_user = usr_id', 'JOIN')));

		$tbl = new Table('tbl_logs', array('User', 'Action', 'Target'));
		if (!empty($items))
		foreach ($items as $item)
		{
			$tbl->AddRow(array($item['usr_name'],
				isset($this->actions) ? $this->actions[$item['log_action']] : $item['log_action'], $item['log_target']));
		}
		return $tbl->Get();
	}
}

?>
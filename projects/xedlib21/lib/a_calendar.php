<?php

/**
 * @package Calendar
 */

/**
 * A simple to use calander display.
 *
 * @example doc\examples\calendar.php
 * @access public
 */
class Calendar
{
	/**
	 * Array of events in this calendar.
	 *
	 * @var array
	 */
	private $events;

	/**
	 * Array of dates in this calendar.
	 *
	 * @var array
	 */
	private $dates;

	/**
	 * Array indexed by day in this calendar.
	 *
	 * @var array
	 */
	private $datesbyday;

	/**
	 * Body of each day. Uses the template identifiers {ts} to include a
	 * timestamp. The rest are processed regularly.
	 *
	 * @var string
	 */
	public $daybody;

	/**
	* Adds an item to the calendar to be displayed in a given period of time
	*
	* @access public
	* @param int $tsfrom Item beginning time.
	* @param int $tsto Item end time.
	* @param string $body Body of this cell.
	*/
	function AddItem($tsfrom, $tsto, $body)
	{
		$fromyear  = date("Y", $tsfrom);
		$frommonth = date("n", $tsfrom);
		$fromday   = date("j", $tsfrom);
		$toyear    = date("Y", $tsto);
		$tomonth   = date("n", $tsto);
		$today     = date("j", $tsto);
		$fromkey   = mktime(0, 0, 0, $frommonth, $fromday, $fromyear);
		$tokey     = mktime(0, 0, 0, $tomonth, $today, $toyear);

		$this->events[count($this->events)] = array($tsfrom, $tsto, $body);
		$DaysSpanned = ($tokey - $fromkey) / 86400;

		for ($ix = 0; $ix <= $DaysSpanned; $ix++)
		{
			//Store reference to timestamp.
			$key = $fromkey+(86400*$ix);
			$this->dates[$key][] = count($this->events)-1;

			//Store reference to day.
			$keyday = date("n-j-Y", $key);
			$this->datesbyday[$keyday][] = count($this->events)-1;
		}
	}

	function TagMonth($t, $guts)
	{
		return $guts;
	}

	function TagWeek($t, $guts, $attribs)
	{
		$ret = null;
		$this->pad = $this->month->Pad;
		foreach ($this->month->Weeks as $ix => $w)
		{
			$this->curweek = $ix;
			$tweek = new Template();
			$tweek->ReWrite('day',  array(&$this, 'TagDay'));
			$tweek->ReWrite('pad',  array(&$this, 'TagPad'));
			$ret .= $tweek->GetString($guts);
		}
		return $ret;

		$vp = new VarParser();
		$ret = '';
		foreach ($this->month->Days as $day)
		{
			$dayts = $day->TimeStamp;
			if ($day->StartWeek) $ret .= "\t<tr>\n";
			$ret .= "\t\t<td valign=\"top\" class=\"CalendarDay\">\n";
			$ret .= "\t\t\t<div class=\"CalendarDayTitle\">\n";
			$ret .= "\t\t\t{$day->Day}</div>\n";
			$ret .= $vp->ParseVars($this->daybody, array('ts' => $dayts));

			if (isset($this->dates[$dayts]))
			{
				foreach ($this->dates[$dayts] as $eventid)
				{
					$event = $this->events[$eventid];
					$ret .= "\t\t\t\n";
					$ret .= "\t\t\t{$event[2]}</p>\n";
				}
			}
			$ret .= "\t\t</td>\n";
			if ($day->LastDay) $ret .= "\t\t<td class=\"CalendarPadding\" colspan=\"".(6 - $day->WeekDay)."\">&nbsp;</td></tr>\n";
			if ($day->EndWeek) $ret .= "\t</tr>\n";
		}
		return $ret;
	}

	function TagPad($t, $guts, $attribs)
	{
		if (empty($this->pad)) return;
		$vp = new VarParser();
		$d['amount'] = $this->pad;
		return $vp->ParseVars($guts, $d);
	}

	function TagDay($t, $guts)
	{
		$ret = null;
		$tday = new Template();
		$tday->ReWrite('event', array(&$this, 'TagEvent'));
		foreach ($this->month->Weeks[$this->curweek] as $ix => $d)
		{
			$this->pad = $d->LastDay ? 6-$d->WeekDay : 0;
			$this->curday = $d;
			$tday->Set('day', $d->Day);
			$ret .= $tday->GetString($guts);
		}
		return $ret;
	}

	function TagEvent($t, $guts)
	{
		$tevent = new Template();
		$key = mktime(0, 0, 0, $this->month->Month, $this->curday->Day, $this->month->Year);
		if (!empty($this->dates[$key]))
		foreach ($this->dates[$key] as $k)
		{
			$tevent->Set('title', $this->events[$k][2]);
			return $tevent->GetString($guts);
		}
	}

	/**
	* Gets an html rendered calander display relative to the given
	* timestamp.
	* @param int $timestamp Time to display the calendar relavant to.
	* @return string Rendered html calendar in normal form.
	*/
	function Get($timestamp = null)
	{
		global $me, $cs;
		require_once('h_template.php');

		$vp = new VarParser();

		$t = new Template();

		$month = GetVar('calmonth', date("n", time()));
		$year = GetVar('calyear', date("Y", time()));
		$t->Set('month', $month);
		$t->Set('year', $year);

		$this->ts = $timestamp != null ? $timestamp : mktime(0, 0, 0, $month, 1, $year);
		$this->month = new CalendarMonth($this->ts);

		$t->Set('cs', $cs);

		$t->ReWrite('input', 'TagInput');
		$t->ReWrite('form', 'TagForm');
		$t->ReWrite('month', array(&$this, 'TagMonth'));
		$t->ReWrite('week', array(&$this, 'TagWeek'));

		return $t->Get(dirname(__FILE__).'/temps/calendar_horiz.xml');
	}

	/**
	 * A different style of calandar display, displays every event horizontally
	 * instead of just one month.
	 * @return string
	 */
	function GetVert()
	{
		global $me;

		$curdate = 0;
		if (is_array($this->dates))
		{
			foreach ($this->dates as $key => $date)
			{
				//if ($key < $curdate) echo "Date invalid? from ($curdate) to ($key)<br/>\n";
				$curdate = $key;
			}
		}
		//$thists = GetVar("ts", time());
		//$ret = "<table class=\"CalendarYear\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
		$ret = "";
		$yearx = 0;
		$curyear = -1;
		$monthx = 0;
		$curmonth = -1;
		$dayx = 0;
		$curday = -1;
		if (!is_array($this->dates)) return null;
		foreach ($this->dates as $key => $eventids)
		{
			$year  = date("Y", $key);
			$month = date("n", $key);
			$day   = date("j", $key);

			if ($year != $curyear || $month != $curmonth)
			{
				//Pad in the rest of the week.
				if ($curday != -1)
				{
					if ($dayx < 7) $ret .= str_repeat("\n\t\t\t<td>&nbsp;</td>\n", 7-$dayx);
					$dayx = 0;
				}
			}

			if ($year != $curyear)
			{
				//Terminate the last month
				if ($curmonth != -1) $ret .= "\t</tr></table><img src=\"/images/pixel_red.gif\" width=\"482\" height=\"1\" />";
				//Begin the next year.
				//if ($yearx % 2 == 0) $id = "odd";
				//else $id = "even";
				//$ret .= "\t<tr>\n\t\t<td class=\"CalendarYearTitle\" id=\"$id\">$year</td>\n\t</tr><tr>\n";
				$curyear = $year;
				$yearx++;
				$curmonth = -1;
			}

			if ($month != $curmonth)
			{
				//New month in same year
				if ($curmonth != -1)
				{
					//Terminate the last month
					$ret .= "</td></tr></table>\n";
				}
				//New month in new year
				//else $ret .= "\t<td>";

				//Begin the next month
				if ($monthx % 2 == 0) $class = "CalendarMonthOdd";
				else $class = "CalendarMonthEven";
				$ret .= "<table border=\"0\" class=\"{$class}\" cellspacing=\"2\" cellpadding=\"3\" width=\"100%\">\n";
				$ret .= "\t<tr>\n";
				$ret .= "\t\t<td colspan=\"7\">\n";
				$ret .= "\t\t\t<b>" . date("F", $key) . " $year</b>\n";
				$ret .= "\t\t</td>\n";
				$ret .= "\t</tr>\n";
				$curmonth = $month;
				$monthx++;
				$curday = -1;
			}

			if ($day != $curday)
			{
				if ($curday != -1) $ret .= "\n\t\t</td>\n";
				else $ret .= "\t<tr>\n";
				if ($dayx % 7 == 0 && $curday != -1) $ret .= "\t</tr><tr>\n";
				$ret .= "\t\t<td class=\"CalendarDay\" valign=\"top\">\n";
				$ret .= "\t\t\t<div class=\"CalendarDayTitle\">$day</div>\n";
				$curday = $day;
				$dayx++;
			}

			foreach ($eventids as $eventid)
			{
				$event = $this->events[$eventid];

				//Calendar day content.
				$ret .= "\t\t\t<p class=\"CalendarDayBody\">\n";
				$ret .= stripslashes($event[2]).'</p>';
			}
		}

		//Pad in the rest of the last week.
		if ($curday != -1)
		{
			$ret .= "\t\t</td>\n";
			if ($dayx < 3) $ret .= "\t\t<td colspan=\"". (3 - $dayx) . "\">&nbsp;</td>\n";
			$dayx = 0;
		}

		$ret .= "\t</tr>\n";
		$ret .= "</table>\n";

		return $ret;
	}
}

/**
 * A single month for use by the Calendar class.
 * @see Calendar
 */
class CalendarMonth
{
	/**
	 * Enter description here...
	 * Year this month is on.
	 * @var int
	 */
	public $Year;
	/**
	 * Numeric month. 1 - 12
	 *
	 * @var int
	 */
	public $Month;
	/**
	 * Amount of blank days at start.
	 *
	 * @var int
	 */
	public $Pad;
	/**
	 * Array of CalendarDay objects.
	 *
	 * @var int
	 */
	public $Days;

	/**
	 * Enter description here...
	 *
	 * @param int $timestamp Timestamp
	 */
	function CalendarMonth($timestamp)
	{
		$this->Year = date('Y', $timestamp);
		$this->Month = date('n', $timestamp);
		$this->Pad = date('w', mktime(0, 0, 0, $this->Month, 1, $this->Year));

		$daycount = date('t', $timestamp);
		$week = 0;
		for ($ix = 1; $ix < $daycount+1; $ix++)
		{
			$d = new CalendarDay(mktime(0, 0, 0, $this->Month, $ix, $this->Year));
			$this->Weeks[$week][] = $d;
			if ($d->EndWeek) $week++;
		}
	}
}

/**
 * Enter description here...
 */
class CalendarDay
{
	/**
	 * This day's timestamp.
	 *
	 * @var int
	 */
	public $TimeStamp;

	/**
	 * true if this day is the first weekday.
	 *
	 * @var bool
	 */
	public $StartWeek;

	/**
	 * true if this day is the last weekday.
	 *
	 * @var bool
	 */
	public $EndWeek;

	/**
	 * Day of the month.
	 *
	 * @var int
	 */
	public $Day;

	/**
	 * Day of the week. Useful for collecting information for month displays.
	 *
	 * @var int
	 */
	public $WeekDay;

	/**
	 * True if this is the last day of the month.
	 *
	 * @var bool
	 */
	public $LastDay;

	/**
	 * Enter description here...
	 *
	 * @param int $timestamp Timestamp of this day.
	 */
	function CalendarDay($timestamp)
	{
		$this->TimeStamp = $timestamp;
		if (date('w', $timestamp) == 0) $this->StartWeek = true;
		if (date('w', $timestamp) == 6) $this->EndWeek = true;
		$this->Day = date('j', $timestamp);
		$this->WeekDay = date('w', $timestamp);
		if (date('t', $timestamp) == date('j', $timestamp)) $this->LastDay = true;
	}
}

?>
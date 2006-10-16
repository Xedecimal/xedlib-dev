<?php

/**
 * @package Calendar
 */

/**
 * Name: Calendar
 * A simple to use calander display.
 * 
 * <code>
 * $cal = new Calendar();\n
 * $cal->AddItem(time(), time()+6000, 'Some content here.');\n
 * echo $cal->Get();\n
 * </code>
 */
class Calendar
{
	public $events; //!< Array of events in this calendar.
	public $dates; //!< Array of dates in this calendar.
	public $datesbyday; //!< Array indexed by day in this calendar.
	public $daybody; //!< Body of the current day?

	/**
	* Adds an item to the calendar to be displayed in a given period of time
	* @param $tsfrom int Item beginning time.
	* @param $tsto int Item end time.
	* @param $body string Body of this cell.
	*/
	function AddItem($tsfrom, $tsto, $body)
	{
		$fromyear  = gmdate("Y", $tsfrom);
		$frommonth = gmdate("n", $tsfrom);
		$fromday   = gmdate("j", $tsfrom);
		$toyear    = gmdate("Y", $tsto);
		$tomonth   = gmdate("n", $tsto);
		$today     = gmdate("j", $tsto);
		$fromkey   = gmmktime(0, 0, 0, $frommonth, $fromday, $fromyear);
		$tokey     = gmmktime(0, 0, 0, $tomonth, $today, $toyear);

		$this->events[count($this->events)] = array($tsfrom, $tsto, $body);
		$DaysSpanned = ($tokey - $fromkey) / 86400;

		for ($ix = 0; $ix <= $DaysSpanned; $ix++)
		{
			//Store reference to timestamp.
			$key = $fromkey+(86400*$ix);
			if (!isset($this->dates[$key])) $this->dates[$key] = array();
			$this->dates[$key][] = count($this->events)-1;

			//Store reference to day.
			$keyday = gmdate("j-m-Y", $key);
			if (!isset($this->datesbyday[$keyday])) $this->datesbyday[$keyday] = array();
			$this->datesbyday[$keyday][] = count($this->events)-1;
		}
	}

	/**
	* Gets an html rendered calander display relative to the given
	* timestamp.
	* @param $timestamp int Time to display the calendar relavant to.
	*/
	function Get($timestamp = null)
	{
		global $me, $cs;
		$vp = new VarParser();

		if ($timestamp != null)
		{
			$thismonth = gmdate("n", $timestamp);
			$thisyear = gmdate("Y", $timestamp);
		}
		else
		{
			$thismonth = GetVar('calmonth', gmdate("n"));
			$thisyear = GetVar('calyear', gmdate("Y"));
		}

		$ts = gmmktime(0, 0, 0, $thismonth, 1, $thisyear); //Get timestamp for first day of this month.

		$month = new CalendarMonth($ts);

		//$off = gmdate("w", $ts); //Gets the offset of the first  day of this month.
		//$days = gmdate("t", $ts); //Get total amount of days in this month.
$ret = <<<EOF
<form action="$me" method="post">
<div><input type="hidden" name="cs" value="$cs" /></div>
<table border="0" width="100%" cellspacing="0">
	<tr class="CalendarHead">
		<td valign="top" colspan="7">
EOF;
$ret .= "			Year: " . GetYearSelect("calyear", $thisyear) . "\n";
$ret .= "			Month: " . GetMonthSelect("calmonth", $thismonth) . "\n";
$ret .= <<<EOF
			<input type="submit" value="Go" />
		</td>
	</tr>
	<tr class="CalendarWeekTitle">
		<td>Sunday</td>
		<td>Monday</td>
		<td>Tuesday</td>
		<td>Wednesday</td>
		<td>Thursday</td>
		<td>Friday</td>
		<td>Saturday</td>
	</tr>
	<tr>
		<td class="CalendarPadding" colspan="{$month->Pad}">&nbsp;</td>

EOF;

		foreach ($month->Days as $day)
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
					$ret .= "\t\t\t<p class=\"CalendarDayBody\">\n";
					$ret .= "\t\t\t{$event[2]}\n";
				}
			}
			$ret .= "\t\t</td>\n";
			if ($day->LastDay) $ret .= "\t\t<td class=\"CalendarPadding\" colspan=\"".(6 - $day->WeekDay)."\">&nbsp;</td>\n";
			if ($day->EndWeek) $ret .= "\t</tr>\n";
		}
		$ret .= "</table></form>\n";
		return $ret;
	}

	/**
	 * A different style of calandar display, displays
	 * every event horizontally instead of just one
	 * month.
	 */
	function GetVert()
	{
		global $me;

		$curdate = 0;
		if (is_array($this->dates))
		{
			foreach ($this->dates as $key => $date)
			{
				if ($key < $curdate) echo "Date invalid? from ($curdate) to ($key)<br/>\n";
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
			$year  = gmdate("Y", $key);
			$month = gmdate("n", $key);
			$day   = gmdate("j", $key);

			if ($year != $curyear || $month != $curmonth)
			{
				//Pad in the rest of the week.
				if ($curday != -1)
				{
					if ($dayx < 7) $ret .= str_repeat("\t\t\t<td>&nbsp;</td>\n", 7-$dayx);
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
				$ret .= "\t\t\t<b>" . gmdate("F", $key) . " $year</b>\n";
				$ret .= "\t\t</td>\n";
				$ret .= "\t</tr>\n";
				$curmonth = $month;
				$monthx++;
				$curday = -1;
			}

			if ($day != $curday)
			{
				if ($curday != -1) $ret .= "\t\t</td>\n";
				else $ret .= "\t<tr>\n";
				if ($dayx % 7 == 0 && $curday != -1) $ret .= "\t</tr><tr>\n";
				$ret .= "\t\t<td class=\"CalendarDay\" valign=\"top\">\n";
				$ret .= "\t\t\t<p class=\"CalendarDayTitle\">$day</p>\n";
				$curday = $day;
				$dayx++;
			}

			foreach ($eventids as $eventid)
			{
				$event = $this->events[$eventid];

				//Calendar day content.
				$ret .= "\t\t\t<p class=\"CalendarDayBody\">\n";
				$ret .= stripslashes($event[2]);
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
 * Enter description here...
 */
class CalendarMonth
{
	public $Year; //!<Year this month is on.
	public $Month; //!<Numeric month.
	public $Pad; //!<Amount of blank days at start.
	public $Days; //!<Array of CalendarDay objects.

	/**
	 * Enter description here...
	 *
	 * @param $timestamp int Timestamp
	 * @return CalendarMonth
	 */
	function CalendarMonth($timestamp)
	{
		$this->Year = gmdate('Y', $timestamp);
		$this->Month = gmdate('n', $timestamp);
		$this->Pad = gmdate('w', $timestamp);
		$daycount = gmdate('t', $timestamp);

		for ($ix = 1; $ix < $daycount+1; $ix++)
		{
			$this->Days[] = new CalendarDay(gmmktime(0, 0, 0, $this->Month, $ix, $this->Year));
		}
	}
}

/**
 * Enter description here...
 */
class CalendarDay
{
	public $TimeStamp; //!< This day's timestamp.
	public $StartWeek; //!< true if this day is the first weekday.
	public $EndWeek; //!< true if this day is the last weekday.
	public $Day; //!< Day of the month.
	public $WeekDay; //!< Day of the week.
	public $LastDay; //!< True if this is the last day of the month.

	/**
	 * Enter description here...
	 *
	 * @param $timestamp int Timestamp of this day.
	 * @return CalendarDay
	 */
	function CalendarDay($timestamp)
	{
		$this->TimeStamp = $timestamp;
		if (gmdate('w', $timestamp) == 0) $this->StartWeek = true;
		if (gmdate('w', $timestamp) == 6) $this->EndWeek = true;
		$this->Day = gmdate('j', $timestamp);
		$this->WeekDay = gmdate('w', $timestamp);
		if (gmdate('t', $timestamp) == gmdate('j', $timestamp)) $this->LastDay = true;
	}
}

?>
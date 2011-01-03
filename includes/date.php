<?php
/**
 * Various date functions
 */
class Date
{
	/**
	 * Get the difference between two dates in words (eg. "2 days ago", "1 year ago")
	 *
	 * Algorithm is based off that used by MooTools - http://mootools.net/docs/more/Types/Date.Extras#Date:timeDiffInWords
	 * @param	int		First timestamp to compare
	 * @param	int		Second timestamp to compare. If not specified, compares first timestamp to today
	 * @return	string	Difference in words
	 */
	static function timeDiffInWords($from, $to = null)
	{
		if ($to == null)
			$to = time();
			
		$delta = $to - $from;

		$units = array(
			'minute' => 60,
			'hour' => 60,
			'day' => 24,
			'week' => 7,
			'month' => 52 / 12,
			'year' => 12,
			'eon' => INF,
		);
		
		$messages = array(
			'lessThanMinuteAgo' => 'less than a minute ago',
			'minuteAgo' => 'about a minute ago',
			'minutesAgo' => '{delta} minutes ago',
			'hourAgo' => 'about an hour ago',
			'hoursAgo' => 'about {delta} hours ago',
			'dayAgo' => '1 day ago',
			'daysAgo' => '{delta} days ago',
			'weekAgo' => '1 week ago',
			'weeksAgo' => '{delta} weeks ago',
			'monthAgo' => '1 month ago',
			'monthsAgo' => '{delta} months ago',
			'yearAgo' => '1 year ago',
			'yearsAgo' => '{delta} years ago',
		);
		
		$message = 'lessThanMinuteAgo';
		
		foreach ($units as $unit => $interval)
		{
			if ($delta < 1.5 * $interval)
			{
				// Approximately 1 unit ago (1 hour, 1 day, 1 week, ...)
				if ($delta > 0.75 * $interval)
				{
					$message = $unit . 'Ago';
				}
				break;
			}
			$delta /= $interval;
			$message = $unit . 'sAgo';
		}
		
		$delta = round($delta);
		return str_replace('{delta}', $delta, $messages[$message]);
	}
}
<?php
/*
 * Social Feeds
 * Copyright (C) 2011, Daniel Lo Nigro (Daniel15) <daniel at dan.cx>
 * 
 * This file is part of Social Feeds.
 * 
 * Social Feeds is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Social Feeds is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Social Feeds.  If not, see <http://www.gnu.org/licenses/>.
 */
 
/**
 * StumbleUpon feed reader
 * TODO: Inherit from FeedSource_Rss (rss.php) instead of the (small amount of) code duplication!!
 */
class FeedSource_Stumbleupon extends FeedSource
{
	const FEED_URL = 'http://rss.stumbleupon.com/user/%s/favorites';
	public function doUpdate()
	{
		$url = sprintf(self::FEED_URL, $this->username);
		
		$data = simplexml_load_file($url);		
		$latest_id = $this->latest_id;

		foreach ($data->channel->item as $item)
		{
			$time = strtotime($item->pubDate);
			
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;
			
			$this->saveToDB($time, $time, $item->title, null, $item->link, null);
			
			$latest_id = max($latest_id, $time);
		}

		$this->latest_id = $latest_id;
	}
	
	public function loadFromDB($row)
	{
		return (object)array(
			'id' => $row->id,
			'text' => 'Shared: <a href="' . $row->url . '"  rel="nofollow" target="_blank">' . $row->text . '</a>',
			'url' => $row->url,
			'date' => $row->date,
			'type' => 'stumbleupon',
		);
	}
}
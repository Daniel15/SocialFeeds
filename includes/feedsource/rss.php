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
 * Implementation of generic RSS feed reader
 */
class FeedSource_Rss extends FeedSource
{
	public function doUpdate()
	{
		$data = simplexml_load_file($this->username);
		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;
		
		foreach ($data->channel->item as $item)
		{
			$time = strtotime($item->pubDate);
			
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;
				
			// Grab the link - But replace with original link if FeedBurner
			$link = $item->link;
			if (!empty($item->children('http://rssnamespace.org/feedburner/ext/1.0')->origLink))
				$link = $item->children('http://rssnamespace.org/feedburner/ext/1.0')->origLink;
				
			$this->saveToDB($time, $time, $item->title, (string)$item->description, $link, null);
				
			$latest_id = max($latest_id, $time);
		}
		
		$this->latest_id = $latest_id;
	}
	
	public function loadFromDB($row)
	{
		return (object)array(
			'id' => $row->id,
			'text' => $this->extra_params['prefix'] . '<a href="' . $row->url . '" rel="nofollow">' . $row->text . '</a>',
			'subtext' => 'via <a href="' . $this->extra_params['url'] . '">' . $this->extra_params['name'] . '</a>',
			'description' => $row->description,
			'url' => $row->url,
			'date' => $row->date,
			'type' => 'rss',
		);
	}
}
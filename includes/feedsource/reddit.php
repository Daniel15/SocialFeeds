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
 * Implementation of Reddit "liked" articles feed reader
 */
class FeedSource_Reddit extends FeedSource
{
	const API_URL = 'http://www.reddit.com/user/%s/liked/.json';
	
	public function doUpdate()
	{
		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;
		
		$url = sprintf(self::API_URL, $this->username);
		
		$data = json_decode(file_get_contents($url));
		
		foreach ($data->data->children as $item)
		{
			$item = $item->data;
			
			$date = $item->created_utc;
			
			$this->saveToDB($date, $date, $item->title, null, $item->url, array(
				'permalink' => 'http://www.reddit.com' . $item->permalink,
				'subreddit' => $item->subreddit,
				'author' => $item->author,
				'is_self' => $item->is_self,
			));
			
			$latest_id = max($latest_id, $date);
		}
		
		$this->latest_id = $latest_id;
	}

	public function loadFromDB($row)
	{
		$result = (object)array(
			'id' => $row->id,
			'text' => 'Liked: <a href="' . $row->url . '" rel="nofollow" target="_blank">' . $row->text . '</a>',
			'subtext' => 'Posted by ' . $row->extra_data['author'] . ' to ' . $row->extra_data['subreddit'], 
			'url' => $row->extra_data['permalink'],
			'date' => $row->date,
			'type' => 'reddit',
		);
		
		return $result;
	}
}
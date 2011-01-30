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
 * Implementation of Last.FM feed reader
 */
class FeedSource_Lastfm extends FeedSource
{
	const API_BASE_URL = 'http://ws.audioscrobbler.com/2.0/';
	
	public function doUpdate()
	{
		$url = self::API_BASE_URL . '?' . http_build_query(array(
			'method' => 'user.getLovedTracks',
			'api_key' => Config::LASTFM_API_KEY,
			'user' => $this->username
		), null, '&');
		
		$data = simplexml_load_file($url);

		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;
		
		foreach ($data->lovedtracks->track as $track)
		{
			$time_attribs = $track->date->attributes();
			$time = (int)$time_attribs['uts'];
			
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;
				
			$this->saveToDB($time, $time, $track->name, null, 'http://' . $track->url, array(
				'artist' => (string)$track->artist->name,
				'artist_url' => (string)$track->artist->url,
			));
			
			$latest_id = max($latest_id, $time);
		}
		
		$this->latest_id = $latest_id;
	}
	
	public function loadFromDB($row)
	{
		return (object)array(
			'id' => $row->id,
			'text' => 'Loved: <a href="' . $row->url . '"  rel="nofollow">' . $row->text . '</a> by <a href="' . $row->extra_data['artist_url'] . '" rel="nofollow">' . $row->extra_data['artist'] . '</a>',
			'url' => $row->url,
			'date' => $row->date,
			'type' => 'lastfm',
		);
	}
}
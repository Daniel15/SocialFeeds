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
 * YouTube feed reader
 */
class FeedSource_Youtube extends FeedSource
{
	const API_URL = 'http://gdata.youtube.com/feeds/api/users/%s/events';
	
	public function doUpdate()
	{
		$querystring = array(
			'v' => 2,
			'key' => Config::YOUTUBE_API_KEY,
			'inline' => 'true',
			'max-results' => 50,
		);
		
		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;
		
		// If we have a minimum date, use it
		if ($this->latest_id > 0)
		{
			$querystring['published-min'] = gmdate('Y-m-d\TH:i:s\Z', $this->latest_id + 1);
		}
		
		$url = sprintf(self::API_URL, $this->username) . '?' . http_build_query($querystring, null, '&');
		$data = simplexml_load_string(str_replace('xmlns=', 'ns=', file_get_contents($url)));
		
		foreach ($data->entry as $entry)
		{
			$time = strtotime($entry->updated);
			
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;
				
			$extra_data = array(
				'entry_type' => $this->getEntryType($entry)
			);
			
			switch ($extra_data['entry_type'])
			{
				case 'video_rated':
					$temp = $entry->children('http://gdata.youtube.com/schemas/2007')->rating->attributes();
					$extra_data['rating'] = (string)$temp['value'];
					// This intentionally falls through to the next "case" statement.
					// No break statement required here!
					
				case 'video_shared':
				case 'video_favorited':
					$videodata = $this->getVideoData($entry);
					$mediadata = $videodata->children('http://search.yahoo.com/mrss/')->group;
					
					$title = (string)$mediadata->title;
					$url = $mediadata->player;
					list($url) = $url->attributes();
					// Note: This is the old (Flash) embed URL.
					$extra_data['embed'] = (string)$this->getEmbedUrl($videodata);
					$extra_data['user'] = (string)$videodata->author->name;
					$extra_data['id'] = (string)$videodata->id;
					
					break;
					
				case 'user_subscription_added':
					$title = (string)$entry->children('http://gdata.youtube.com/schemas/2007')->username;
					$url = $this->getProfileUrl((string)$title);
					
					break;
					
				// Ignore all others for now!
				default:
					echo 'Ignoring ', $extra_data['entry_type'], "\n";
					continue 2;
			}
			
			$latest_id = max($latest_id, $time);
			$this->saveToDB($time, $time, $title, null, $url, $extra_data);
		}
		
		$this->latest_id = $latest_id;
	}
	
	public function loadFromDB($row)
	{
		$result = array(
			'id' => $row->id,
			'text' => '<a href="' . $row->url . '"  rel="nofollow" target="_blank">' . $row->text . '</a>',
			'url' => $row->url,
			'date' => $row->date,
			'type' => 'youtube',
		);
		
		// What type of post is this?
		switch ($row->extra_data['entry_type'])
		{
			case 'video_rated':
				$result['text'] = 'Liked a video: ' . $result['text'];
				break;
				
			case 'video_favorited':
				$result['text'] = 'Favourited a video: ' . $result['text'];
				break;
				
			case 'video_shared':
				$result['text'] = 'Shared a video: ' . $result['text'];
				break;
				
			case 'user_subscription_added':
				$result['text'] = 'Subscribed to ' . $result['text'] . ' on YouTube';
				break;
		}
		
		if (substr($row->extra_data['entry_type'], 0, 6) == 'video_')
		{
			$result['subtext'] = 'by <a href="' . $this->getProfileUrl($row->extra_data['user']) . '" rel="nofollow" target="_blank">' . $row->extra_data['user'] . '</a>';
			$result['description'] = $this->getEmbedCode($row);
		}
		
		return (object)$result;
	}
	
	protected static function getEntryType($entry)
	{
		$temp = $entry->xpath('./category[@scheme="http://gdata.youtube.com/schemas/2007/userevents.cat"]');
		return (string)$temp[0]['term'];
	}
	
	protected static function getVideoData($entry)
	{
		list($videodata) = $entry->xpath('./link[@rel="http://gdata.youtube.com/schemas/2007#video"]');
		return $videodata->entry;
	}

	// Note: This is the old (Flash) embed URL..
	protected static function getEmbedUrl($videodata)
	{
		list($temp) = $videodata->xpath('./content[@type="application/x-shockwave-flash"]');
		return $temp[0]['src'];
	}
	
	protected static function getProfileUrl($profile)
	{
		return 'http://www.youtube.com/profile/' . $profile;
	}
	
	protected static function getEmbedCode($row)
	{
		//return '<object width="480" height="385"><param name="movie" value="' . $embedUrl . '"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="' . $embedUrl . '" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="480" height="385"></embed></object>';

		// Unfortunately YouTube only provides the old Flash embed URL via its API, they don't provide the iframe URL
		// We have to build that manually :(
		return '<iframe width="480" height="385" src="http://www.youtube.com/embed/' . static::getVideoID($row) . '" frameborder="0" allowfullscreen></iframe>';
	}

	protected static function getVideoID($row)
	{
		// Check if we have an ID just sitting in the data.
		// Boy, that'd be nice.
		if (!empty($row->extra_data['id']))
		{
			// Woohoo!
			return $row->extra_data['id'];
		}

		// Nope, so we have to try determine it based om the video URL
		// Parse its querystring and get the "v" parameter.
		$querystring = parse_url($row->url, PHP_URL_QUERY);
		$params = [];
		parse_str($querystring, $params);
		return $params['v'];
	}
}
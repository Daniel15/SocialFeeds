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
 
class FeedSource_Twitter extends FeedSource
{
	const API_URL = 'http://api.twitter.com/1/statuses/user_timeline/%s.json';
	public function doUpdate()
	{
		$url = sprintf(self::API_URL, $this->username) . '?' . http_build_query(array(
			'include_rts' => 1,
			'count' => 200,
			'since_id' => $this->latest_id,
		), null, '&');
		
		$data = array_reverse(json_decode(file_get_contents($url)));
		foreach ($data as $item)
		{
			$this->saveToDB($item->id_str, strtotime($item->created_at), $item->text, null, null, array(
				'source' => $item->source,
				'reply' => !empty($item->in_reply_to_status_id_str),
				'in_reply_to_status' => $item->in_reply_to_status_id_str,
				'in_reply_to_user' => $item->in_reply_to_screen_name,
			), $this->shouldShowTweet($item));
		}
		
		if (count($data) != 0)
			$this->latest_id = $data[count($data) - 1]->id_str;
	}
	
	protected static function shouldShowTweet($tweet)
	{
		// Hide YouTube (since that's imported already)
		if (strpos($tweet->source, 'youtube') !== false && strpos($tweet->source, 'Google') !== false)
			return false;
		// FourSquare is already imported
		if (strpos($tweet->source, 'foursquare') !== false)
			return false;
		// Last.fm already imported
		if (strpos($tweet->source, 'lastfmlove') !== false)
			return false;
			
		return true;
	}
	
	public function loadFromDB($row)
	{
		$result = (object)array(
			'id' => $row->id,
			'text' => $row->text,
			'subtext' => 'via ' . $row->extra_data['source'],
			'url' => $this->getTweetUrl($this->username, $row->original_id),
			'date' => $row->date,
			'type' => 'twitter',
		);
		
		// Check if it's in reply to something
		if ($row->extra_data['reply'])
		{
			$result->subtext .= ' in reply to <a href="http://twitter.com/' . $row->extra_data['in_reply_to_user'] . '/statuses/' .  $row->extra_data['in_reply_to_status']. '">' . $row->extra_data['in_reply_to_user'] . '</a>';
		}
		
		// "Linkify" the links
		$result->text = preg_replace('~(http://\S+)~i', '<a href="$1">$1</a>', $result->text);
		// Linkify Twitter profiles
		$result->text = preg_replace('~@([A-Z0-9]+)~i', '<a href="http://www.twitter.com/$1" title="Twitter profile for $1"  rel="nofollow">@$1</a>', $result->text);
		// Linkify Twitter hashtags
		$result->text = preg_replace('~#(\S+)~i', '<a href="http://search.twitter.com/search?q=%23$1" title="Twitter hashtag \'$1\'"  rel="nofollow">#$1</a>', $result->text);
		
		return (object)$result;
	}
	
	protected static function getTweetUrl($username, $tweetId)
	{
		return 'http://twitter.com/' . $username . '/statuses/' . $tweetId;
	}
}
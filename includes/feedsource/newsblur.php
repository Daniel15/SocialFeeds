<?php
/*
 * Social Feeds
 * Copyright (C) 2013, Daniel Lo Nigro (Daniel15) <daniel at dan.cx>
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
 * Newsblur feed reader
 */
class FeedSource_Newsblur extends FeedSource
{
	/**
	 * URL to the NewsBlur shared stories API
	 */
	const API_URL = 'http://newsblur.com/social/stories/%s/';

	/**
	 * Update the feed with all the latest items
	 */
	public function doUpdate()
	{
		$this->latest_id = (int)$this->latest_id;
		$this->prev_latest_id = $this->latest_id;

		$url = sprintf(self::API_URL, $this->username);
		$stream = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => "User-Agent: Daniel15-SocialFeeds/1.0 (socialfeeds@dan.cx; http://github.com/Daniel15/SocialFeeds)"
			]
		]);
		$data = json_decode(file_get_contents($url, false, $stream));

		foreach ($data->stories as $item)
		{
			$time = strtotime($item->shared_date);

			// Skip it if it's older than the latest item
			if ($time <= $this->prev_latest_id)
				continue;

			// See if this user made a comment on it. If so, get their first comment
			//$my_comments = array_filter($item->public_comments, function($comment) { return $comment->user_id == $this->username; });
			$oldest_comment = null;
			if (!empty($item->public_comments))
			{
				foreach ($item->public_comments as $comment)
				{
					if ($comment->user_id == $this->username && ($oldest_comment == null || $comment->date < $oldest_comment->date))
					{
						$oldest_comment = $comment;
					}
				}
			}

			$description = $oldest_comment == null ? null : $oldest_comment->comments;

			$this->saveToDB($time, $time, $item->story_title, $description, $item->story_permalink, null);
			$this->latest_id = max($this->latest_id, $time);
		}
	}

	/**
	 * Load a feed item from the database.
	 */
	public function loadFromDB($row)
	{
		return (object)array(
			'id' => $row->id,
			'text' => 'Shared: <a href="' . $row->url . '"  rel="nofollow" target="_blank">' . $row->text . '</a>',
			'url' => $row->url,
			'date' => $row->date,
			'type' => 'newsblur',
		);
	}
}
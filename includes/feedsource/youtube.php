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
	const API_URL = 'https://www.googleapis.com/youtube/v3/%s';

	public function doUpdate()
	{
		$querystring = array(
			'part' => 'snippet,contentDetails',
			'channelId' => $this->username,
			'maxResults' => 50,
		);
		$this->latest_id = (int)$this->latest_id;

		// If we have a minimum date, use it
		if ($this->latest_id > 0)
		{
			$querystring['publishedAfter'] = gmdate('Y-m-d\TH:i:s\Z', $this->latest_id + 1);
		}

		do {
			$data = $this->callAPI('activities', $querystring);
			$this->doUpdatePage($data);
			// Go through all pages until completed
			$querystring['pageToken'] = empty($data->nextPageToken) ? null : $data->nextPageToken;
		} while (!empty($querystring['pageToken']));
	}

	private function doUpdatePage($data)
	{
		$liked = [];
		$subscribed = [];

		foreach ($data->items as $entry)
		{
			$time = strtotime($entry->snippet->publishedAt);
		 	$this->latest_id = max($this->latest_id, $time);

			switch ($entry->snippet->type)
			{
				case 'like':
				 	// Scumbag YouTube API, important information like the name of the
					// channel that posted the video is totally missing from the API
					// response, so we need to do a separate API request to get it.
					$liked[] = [
						'time' => $time,
						'video_id' => $entry->contentDetails->like->resourceId->videoId,
					];
					break;

				case 'subscription':
					// As above, the API response doesn't even contain the *name* of the
					// channel that was subscribed to. Thanks Google.
					$subscribed[] = [
						'channel_id' => $entry->contentDetails->subscription->resourceId->channelId,
						'time' => $time,
					];
					break;

				// Ignore all others for now!
				default:
					echo 'Ignoring ', $entry->snippet->type, "\n";
					continue 2;
			}
		}

		$this->insertLikedVideos($liked);
		$this->insertSubscribedChannels($subscribed);
	}

	private function insertLikedVideos($raw_data)
	{
		$video_ids = array_map(function($data) { return $data['video_id']; }, $raw_data);
		$videos = self::indexByID($this->callAPI('videos', [
			'part' => 'player,snippet',
			'id' => implode(',', $video_ids),
		]));

		foreach ($raw_data as $raw_datum)
		{
			if (empty($videos[$raw_datum['video_id']]))
			{
				echo 'Skipping unknown video "' . $raw_datum['video_id'] . "\"\n";
				continue;
			}

			$video = $videos[$raw_datum['video_id']];
			// YouTube API v3 doesn't include video URL like v2 does.
			// Hardcoding URL formats is my favourite thing ever. Thanks Google.
			$url = 'https://www.youtube.com/watch?v=' . $raw_datum['video_id'];
			$this->saveToDB(
				$raw_datum['time'] . '_' . $raw_datum['video_id'],
				$raw_datum['time'],
				$video->snippet->title,
				null,
				$url,
				[
					'entry_type' => 'v3_video_liked',
					'embed_html' => $video->player->embedHtml,
					'channel_id' => $video->snippet->channelId,
					'channel_name' => $video->snippet->channelTitle,
					'video_id' => $raw_datum['video_id'],
				]);
		}
	}

	private function insertSubscribedChannels($raw_data)
	{
		$channel_ids = array_map(function($data) { return $data['channel_id']; }, $raw_data);
		$channels = self::indexByID($this->callAPI('channels', [
			'part' => 'snippet',
			'id' => implode(',', $channel_ids),
		]));

		foreach ($raw_data as $raw_datum)
		{
			if (empty($channels[$raw_datum['channel_id']]))
			{
				echo 'Skipping unknown channel "' . $raw_datum['channel_id'] . "\"\n";
				continue;
			}

			$channel = $channels[$raw_datum['channel_id']];
			$this->saveToDB(
				$raw_datum['time'] . '_' . $raw_datum['channel_id'],
				$raw_datum['time'],
				$channel->snippet->title,
				$channel->snippet->description,
				self::getChannelURL($raw_datum['channel_id']),
				[
					'entry_type' => 'v3_channel_subscribed',
					'channel_id' => $raw_datum['channel_id'],
				]
			);
		}
	}

	private function callAPI($endpoint, $params)
	{
		$params['key'] = Config::YOUTUBE_API_KEY;
		$url = sprintf(self::API_URL, $endpoint) . '?' . http_build_query($params, null, '&');
		return json_decode(file_get_contents($url));
	}

	private static function indexByID($data)
	{
		$output = [];
		foreach ($data->items as $item)
		{
			$output[$item->id] = $item;
		}
		return $output;
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
			// Modern (API v3) types
			case 'v3_video_liked':
				$result['text'] = 'Liked a video: ' . $result['text'];
				$result['subtext'] = 'by <a href="' . self::getChannelURL($row->extra_data['channel_id']) . '" rel="nofollow" target="_blank">' . $row->extra_data['channel_name'] . '</a>';
				$result['description'] = $row->extra_data['embed_html'];
				break;

			case 'v3_channel_subscribed':
			case 'user_subscription_added':
				$result['text'] = 'Subscribed to ' . $result['text'] . ' on YouTube';
				break;

			// Legacy (API v2) types
			case 'video_rated':
				$result['text'] = 'Liked a video: ' . $result['text'];
				break;

			case 'video_favorited':
				$result['text'] = 'Favourited a video: ' . $result['text'];
				break;

			case 'video_shared':
				$result['text'] = 'Shared a video: ' . $result['text'];
				break;

		}

		// Legacy
		if (substr($row->extra_data['entry_type'], 0, 6) == 'video_')
		{
			$result['subtext'] = 'by <a href="' . $this->getProfileUrl($row->extra_data['user']) . '" rel="nofollow" target="_blank">' . $row->extra_data['user'] . '</a>';
			$result['description'] = $this->getEmbedCode($row);
		}

		return (object)$result;
	}

	protected static function getChannelURL($id) {
		return 'https://www.youtube.com/channel/' . $id;
	}

	// Legacy profile URLs
	protected static function getProfileUrl($profile)
	{
		return 'http://www.youtube.com/profile/' . $profile;
	}

	protected static function getEmbedCode($row)
	{
		// Unfortunately, with API v2, YouTube only provided the old Flash embed URL via its API, they didnt provide the iframe URL
		// We have to build that manually :(
		// This is only for legacy data, API v3 does return the embed code in the response.
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

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

use Abraham\TwitterOAuth\TwitterOAuth;

class FeedSource_Twitter extends FeedSource
{
	const API_URL = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
	public function doUpdate()
	{
		$connection = new TwitterOAuth(
			$this->extra_params['consumer_key'],
			$this->extra_params['consumer_secret'],
			$this->extra_params['access_token'], 
			$this->extra_params['access_token_secret']
		);
		$data = $connection->get('statuses/user_timeline', [
			'screen_name' => $this->username,
			'include_rts' => 1,
			'count' => 3200,
			'since_id' => $this->latest_id,
		]);
		$data = array_reverse($data);

		foreach ($data as $item)
		{
			$extra_data = [
				'source' => $item->source,
				'reply' => !empty($item->in_reply_to_status_id_str),
				'in_reply_to_status' => $item->in_reply_to_status_id_str,
				'in_reply_to_user' => $item->in_reply_to_screen_name,
			];

			// Was it a retweet? Grab the original tweet's text, in case it was truncated
			if (!empty($item->retweeted_status))
			{
				$text = $item->retweeted_status->text;
				$extra_data['entities'] = $item->retweeted_status->entities;
				$extra_data['retweet_user'] = $item->retweeted_status->user->screen_name;
			}
			else
			{
				$text = $item->text;
				$extra_data['entities'] = $item->entities;
			}

			$this->saveToDB($item->id_str, strtotime($item->created_at), $text, null, null, $extra_data, $this->shouldShowTweet($item));
		}

		if (count($data) != 0)
			$this->latest_id = $data[count($data) - 1]->id_str;
	}

	/**
	 * Only used for tweets where the entities are not stored.
	 */
	protected static function processTextWithoutEntities($text)
	{
		// "Linkify" the links
		$text = preg_replace('~(https?://\S+)~i', '<a href="$1">$1</a>', $text);
		// Linkify Twitter profiles
		$text = preg_replace('~@([A-Z0-9]+)~i', static::getUserHTML('$1'), $text);
		// Linkify Twitter hashtags
		$text = preg_replace('~#(\S+)~i', static::getHashtagHTML('$1'), $text);
		return $text;
	}

	protected static function processEntities($tweet) {
		if (empty($tweet->extra_data['entities'])) {
			// It's an old tweet from before entities were stored.
			return static::processTextWithoutEntities($tweet->text);
		}

		$original_entities = $tweet->extra_data['entities'];
		$entities = [];
		if (!empty($original_entities->urls)) {
			foreach ($original_entities->urls as $url) {
				$entities[$url->indices[0]] = [
					'text' => '<a href="' . $url->expanded_url . '" rel="nofollow">' . $url->display_url . '</a>',
					'start' => $url->indices[0],
					'end' => $url->indices[1],
				];
			}
		}

		if (!empty($original_entities->hashtags)) {
			foreach ($original_entities->hashtags as $hashtag) {
				$entities[$hashtag->indices[0]] = [
					'text' => static::getHashtagHTML($hashtag->text),
					'start' => $hashtag->indices[0],
					'end' => $hashtag->indices[1],
				];
			}
		}

		if (!empty($original_entities->user_mentions)) {
			foreach ($original_entities->user_mentions as $mention) {
				$entities[$mention->indices[0]] = [
					'text' => static::getUserHTML($mention->screen_name, $mention->name),
					'start' => $mention->indices[0],
					'end' => $mention->indices[1],
				];
			}
		}

		$text = $tweet->text;
		// Reverse order so character offsets always make sense
		krsort($entities);
		foreach ($entities as $entity) {
			$text = substr_replace(
				$text,
				$entity['text'],
				$entity['start'],
				$entity['end'] - $entity['start']
			);
		}

		return $text;
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
		// Blog already imported
		if (strpos($tweet->source, 'Daniel15\'s Blog') !== false)
			return false;

		// Hide @mentions by default
		if ($tweet->text[0] == '@')
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
			$result->subtext .= ' in reply to <a href="https://twitter.com/' . $row->extra_data['in_reply_to_user'] . '/statuses/' .  $row->extra_data['in_reply_to_status']. '">' . $row->extra_data['in_reply_to_user'] . '</a>';
		}

		$result->text = static::processEntities($row);
		if (!empty($row->extra_data['retweet_user'])) {
			$result->text = 'RT ' . static::getUserHTML($row->extra_data['retweet_user']) . ': ' . $result->text;
		}

		return (object)$result;
	}

	protected static function getUserHTML($username, $realname = null)
	{
		return '<a href="https://twitter.com/' . $username . '" title="Twitter profile for ' . ($realname ?: $username) . '"  rel="nofollow">@' . $username . '</a>';
	}

	protected static function getHashtagHTML($hashtag) {
		return '<a href="https://twitter.com/search?q=%23' . $hashtag .
			'" title="Twitter hashtag \'' . $hashtag . '\'"  rel="nofollow">#' .
			$hashtag . '</a>';
	}

	protected static function getTweetUrl($username, $tweetId)
	{
		return 'https://twitter.com/' . $username . '/statuses/' . $tweetId;
	}
}

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
 * Github feed reader. This is INCOMPLETE
 * TODO: Finish it.
 */
class FeedSource_Github extends FeedSource
{
	// TOOD: Ensure this sends a User-Agent
	const API_URL = 'https://api.github.com/users/%s/events';

	/**
	 * Update the feed with all the latest items
	 */
	public function doUpdate()
	{
		//$url = sprintf(self::API_URL, $this->username);
		$url = '/var/www/socialfeeds/github2.json';

		$data = json_decode(file_get_contents($url));
		$this->latest_id = (int)$this->latest_id;

		foreach ($data as $item)
		{
			$time = strtotime($item->created_at);
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;

			$processMethod = 'process' . $item->type;
			// Ensure we actually know what this event type is
			if (!method_exists($this, $processMethod))
			{
				echo 'Ignoring unknown event "' . $item->type . "\"\n";
				print_r($item);
				continue;
			}

			$url = '';
			$text = '';
			$visible = true;
			$extra_data = [
				'type' => $item->type,
			];

			// Check if it's associated with a repository
			if (!empty($item->repo->id))
			{
				$extra_data['repo'] = [
					'id' => empty($item->repo->id) ? null : $item->repo->id,
					'name' => $item->repo->name,
					'url' => static::getRepoUrlFromApiUrl($item->repo->url),
				];
			}

			$this->$processMethod($item, $url, $text, $visible, $extra_data);
			$this->saveToDB($item->id, $time, $text, null, $url, $extra_data, $visible);
			$this->latest_id = max($this->latest_id, $time);
		}
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	private function processCreateEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$text = $item->payload->ref;
		$extra_data += [
			'ref_type' => $item->payload->ref_type,
		];
	}

	private function processFollowEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->target->html_url;
		// Show username if real name is not specified
		$text = empty($item->payload->target->name) ? $item->payload->target->login : $item->payload->target->name;
		$extra_data += [
			'login' => $item->payload->target->login,
		];
	}

	private function processForkEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->forkee->html_url;

		// Github's docs are incorrect; older repositories don't seem to have the "full_name" property.
		if (empty($item->payload->forkee->full_name))
			$text = $item->payload->forkee->owner->login . '/' . $item->payload->forkee->name;
		else
			$text = $item->payload->forkee->full_name;
	}

	private function processGistEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->gist->html_url;
		$text = $item->payload->gist->description;
		$extra_data += [
			'action' => $item->payload->action,
		];
	}

	// These are saved but hidden right now... Not sure if they add much value in a social feed.
	private function processIssueCommentEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->issue->html_url;
		$text = $item->payload->issue->title;
		$extra_data += [
			'number' => $item->payload->issue->number,
		];

		$visible = false;
	}

	private function processIssuesEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->issue->html_url;
		$text = $item->payload->issue->title;
		$extra_data += [
			'action' => $item->payload->action,
			'number' => $item->payload->issue->number,
		];
	}

	private function processPullRequestEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->pull_request->html_url;
		$text = $item->payload->pull_request->title;
		$extra_data += [
			'state' => $item->payload->pull_request->state,
			'number' => $item->payload->pull_request->number,
		];
	}

	private function processPushEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$extra_data += [
			'head' => $item->payload->head,
			'commits' => []
		];

		foreach ($item->payload->commits as $commit)
		{
			$extra_data['commits'][] = [
				'sha' => $commit->sha,
				'message' => $commit->message,
				'url' => static::getRepoUrlFromApiUrl($commit->url),
			];
		}
	}

	private function processWatchEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = static::getRepoUrlFromApiUrl($item->repo->url);
		$text = $item->repo->name;
		$extra_data += [
			'action' => $item->payload->action,
		];
	}

	// Don't really care about these
	private function processCommitCommentEvent($item, &$url, &$text, &$visible, &$extra_data) {}
	private function processDeleteEvent($item, &$url, &$text, &$visible, &$extra_data) {}
	private function processPullRequestReviewCommentEvent($item, &$url, &$text, &$visible, &$extra_data) {}
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Load a feed item from the database.
	 */
	public function loadFromDB($row)
	{
		throw new Exception('TODO');
		// TODO: Implement loadFromDB() method.
	}

	/**
	 * A bit of a hack to convert a Github repository API URL (eg. http://api.github.com/repos/Daniel15/SocialFeeds) intp
	 * the public website URL.
	 *
	 * @static
	 * @param $apiUrl API URL for the repository
	 * @return string Github repository URL
	 */
	private static function getRepoUrlFromApiUrl($apiUrl)
	{
		return str_replace(['api.', '/repos'], '', $apiUrl);
	}
}
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
	/**
	 * URL to the user events feed from GitHub's API
	 */
	const API_URL = 'https://api.github.com/users/%s/events';
	/**
	 * Number of items per page
	 */
	const ITEMS_PER_PAGE = 30;
	/**
	 * Maximum page number for the API endpoint
	 */
	const MAX_PAGE = 10;

	/**
	 * Update the feed with all the latest items
	 */
	public function doUpdate()
	{
		$this->latest_id = (int)$this->latest_id;
		$this->prev_latest_id = $this->latest_id;

		$page = 1;
		do
		{
			$new_items = $this->doUpdatePage($page);
			$page++;
		}
		// Keep iterating while we're adding a full page of items. Since we added a full page, there's a chance there
		// could be more new items on the next page.
		while ($new_items == static::ITEMS_PER_PAGE && $page <= static::MAX_PAGE);
	}

	private function doUpdatePage($page)
	{
		$new_items = 0;

		$url = sprintf(self::API_URL, $this->username) . '?page=' . $page;
		$stream = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => "User-Agent: Daniel15-SocialFeeds/1.0 (socialfeeds@dan.cx; http://github.com/Daniel15/SocialFeeds)"
			]
		]);
		$data = json_decode(file_get_contents($url, false, $stream));

		foreach ($data as $item)
		{
			$time = strtotime($item->created_at);

			// Skip it if it's older than the latest item
			if ($time <= $this->prev_latest_id)
				continue;

			$new_items++;

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

			// If the processing method returns false, this event shouldn't be saved
			if (!$this->$processMethod($item, $url, $text, $visible, $extra_data))
				continue;

			$this->saveToDB($item->id, $time, $text, null, $url, $extra_data, $visible);
			$this->latest_id = max($this->latest_id, $time);
		}

		return $new_items;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	private function processCreateEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		if ($item->payload->ref_type == 'repository')
		{
			$text = $item->repo->name;
			$url = static::getRepoUrlFromApiUrl($item->repo->url);
		}
		else
		{
			$text = $item->payload->ref;
			// Github doesn't supply the branch or tag URL in the API :(
			$url = static::getRepoUrlFromApiUrl($item->repo->url) . '/tree/' . $text;
		}

		$extra_data += [
			'ref_type' => $item->payload->ref_type,
		];
		return true;
	}

	private function processFollowEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->target->html_url;
		// Show username if real name is not specified
		$text = empty($item->payload->target->name) ? $item->payload->target->login : $item->payload->target->name;
		$extra_data += [
			'login' => $item->payload->target->login,
		];
		return true;
	}

	private function processForkEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->forkee->html_url;

		// Github's docs are incorrect; older repositories don't seem to have the "full_name" property.
		if (empty($item->payload->forkee->full_name))
			$text = $item->payload->forkee->owner->login . '/' . $item->payload->forkee->name;
		else
			$text = $item->payload->forkee->full_name;
		return true;
	}

	private function processGistEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->gist->html_url;
		$text = $item->payload->gist->description;
		$extra_data += [
			'action' => $item->payload->action,
		];
		return true;
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
		return true;
	}

	private function processIssuesEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->issue->html_url;
		$text = $item->payload->issue->title;
		$extra_data += [
			'action' => $item->payload->action,
			'number' => $item->payload->issue->number,
		];
		return true;
	}

	private function processPullRequestEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = $item->payload->pull_request->html_url;
		$text = $item->payload->pull_request->title;
		$extra_data += [
			'state' => $item->payload->pull_request->state,
			'number' => $item->payload->pull_request->number,
		];
		return true;
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
		return true;
	}

	private function processWatchEvent($item, &$url, &$text, &$visible, &$extra_data)
	{
		$url = static::getRepoUrlFromApiUrl($item->repo->url);
		$text = $item->repo->name;
		$extra_data += [
			'action' => $item->payload->action,
		];
		return true;
	}

	// Don't really care about these
	private function processCommitCommentEvent($item, &$url, &$text, &$visible, &$extra_data) {
		return false;
	}
	private function processDeleteEvent($item, &$url, &$text, &$visible, &$extra_data) {
		$visible = false;
		return true;
	}
	private function processPullRequestReviewCommentEvent($item, &$url, &$text, &$visible, &$extra_data) {
		return false;
	}
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Load a feed item from the database.
	 */
	public function loadFromDB($row)
	{
		$link = '<a href="' . $row->url . '" rel="nofollow">' . $row->text . '</a>';
		$description = '';

		switch ($row->extra_data['type'])
		{
			case 'CreateEvent':
				if ($row->extra_data['ref_type'] == 'branch')
				{
					$text = 'Created new branch ' . $link . ' on <a href="' . $row->extra_data['repo']['url'] . '" rel="nofollow">' . $row->extra_data['repo']['name'] . '</a>';
				}
				elseif ($row->extra_data['ref_type'] == 'tag')
				{
					$text = 'Created new tag ' . $link . ' for <a href="' . $row->extra_data['repo']['url'] . '" rel="nofollow">' . $row->extra_data['repo']['name'] . '</a>';
				}
				elseif ($row->extra_data['ref_type'] == 'repository')
				{
					$text = 'Created new repository ' . $link;
				}
				break;
			case 'FollowEvent':
				$text = 'Started following ' . $link;
				break;
			case 'ForkEvent':
				$text = 'Forked ' . $row->extra_data['repo']['name'] . ' to ' . $link;
				break;
			case 'GistEvent':
				$text = ucfirst($row->extra_data['action']) . 'd a Gist: ' . $link;
				break;
			case 'IssuesEvent':
				$text = ucfirst($row->extra_data['action']) . ' issue ' . $link;
				break;
			case 'PullRequestEvent':
				$text = ucfirst($row->extra_data['state']) . ' pull request ' . $link . ' for <a href="' . $row->extra_data['repo']['url'] . '" rel="nofollow">' . $row->extra_data['repo']['name'] . '</a>';
				break;
			case 'PushEvent':
				if (count($row->extra_data['commits']) == 1)
				{
					$text = 'Pushed to ' . $row->extra_data['repo']['name'] . ': <a href="' . $row->extra_data['commits'][0]['url'] . '" rel="nofollow">' . $row->extra_data['commits'][0]['message'] . '</a>';
				}
				else
				{
					$text = 'Pushed ' . count($row->extra_data['commits']) . ' commits to <a href="' . $row->extra_data['repo']['url'] . '" rel="nofollow">' . $row->extra_data['repo']['name'] . '</a>';
					$description = '<ul>';
					foreach ($row->extra_data['commits'] as $commit)
					{
						$description .= '<li><a href="' . $commit['url'] . '" rel="nofollow">' . $commit['message'] . '</a></li>';
					}
					$description .= '</ul>';
				}
				break;
			case 'WatchEvent':
				$text = 'Started watching ' . $link;
				break;

			// Any unrecognised events. Should never happen as unrecognised events are not saved to the DB
			default:
				$text = $row->extra_data['type'] . ': ' . $row->text;
				break;
		}

		return (object)array(
			'id' => $row->id,
			'text' => $text,
			'description' => $description,
			'url' => $row->url,
			'date' => $row->date,
			'type' => 'github',
		);
	}

	/**
	 * A bit of a hack to convert a Github repository API URL (eg. http://api.github.com/repos/Daniel15/SocialFeeds) into
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

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
	const API_URL = 'https://api.github.com/users/%s/events';

	/**
	 * Update the feed with all the latest items
	 */
	public function doUpdate()
	{
		//$url = sprintf(self::API_URL, $this->username);
		$url = '/var/www/socialfeeds/github2.json';

		$data = json_decode(file_get_contents($url));

		foreach ($data as $item)
		{
			$time = strtotime($item->created_at);
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;

			$url = '';
			$text = '';
			$visible = true;
			$extra_data = [
				'type' => $item->type,
				'repo' => $item->repo,
			];

			// TODO: Split this into separate methods and use factory method pattern
			switch ($item->type)
			{
				case 'PushEvent':
					$extra_data += [
						'head' => $item->payload->head,
						'commits' => []
					];

					foreach ($item->payload->commits as $commit)
					{
						$extra_data['commits'][] = [
							'sha' => $commit->sha,
							'message' => $commit->message,
							'url' => $commit->url
						];
					}

					break;

				case 'IssuesEvent':
					$url = $item->payload->issue->url;
					$text = $item->payload->issue->title;
					$extra_data += [
						'action' => $item->payload->action,
						'number' => $item->payload->issue->number,
					];
					break;

				case 'PullRequestEvent':
					$url = $item->payload->pull_request->url;
					$text = $item->payload->pull_request->title;
					$extra_data += [
						'state' => $item->payload->pull_request->state,
						'number' => $item->payload->pull_request->number,
					];
					break;

				case 'WatchEvent':
					$url = $item->repo->url;
					$text = $item->repo->name;
					$extra_data += [
						'action' => $item->payload->action,
					];
					break;

				case 'FollowEvent':
					$url = $item->payload->target->html_url;
					// Show username if real name is not specified
					$text = empty($item->payload->target->name) ? $item->payload->target->login : $item->payload->target->name;
					$extra_data += [
						'login' => $item->payload->target->login,
					];
					break;

				case 'ForkEvent':
					$url = $item->payload->forkee->html_url;

					// Github's docs are incorrect; older repositories don't seem to have the "full_name" property.
					if (empty($item->payload->forkee->full_name))
						$text = $item->payload->forkee->owner->login . '/' . $item->payload->forkee->name;
					else
						$text = $item->payload->forkee->full_name;

					break;

				case 'CreateEvent':
					$text = $item->payload->ref;
					$extra_data += [
						'ref_type' => $item->payload->ref_type,
					];
					break;

				case 'GistEvent':
					$url = $item->payload->gist->html_url;
					$text = $item->payload->gist->description;
					$extra_data += [
						'action' => $item->payload->action,
					];
					break;

				// These are saved but hidden right now... Not sure if they add much value in a social feed.
				case 'IssueCommentEvent':
					$url = $item->payload->issue->url;
					$text = $item->payload->issue->title;
					$extra_data += [
						'number' => $item->payload->issue->number,
					];

					$visible = false;
					break;

				// Don't really care about these
				case 'DeleteEvent':
				case 'PullRequestReviewCommentEvent':
				case 'CommitCommentEvent':
					continue;

				default:
					echo 'Ignoring unknown event "' . $item->type . "\"\n";
					print_r($item);
					break;
			}

			throw new Exception('TODO: Save to database');
		}

		die();
	}

	/**
	 * Load a feed item from the database.
	 */
	public function loadFromDB($row)
	{
		throw new Exception('TODO');
		// TODO: Implement loadFromDB() method.
	}
}
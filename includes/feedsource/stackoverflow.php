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
 * Implementation of StackOverflow feed source
 */
class FeedSource_Stackoverflow extends FeedSource
{
	const API_URL = 'http://api.stackoverflow.com/1.1/users/%s/timeline';
	
	public function doUpdate()
	{
		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;
			
		$url = sprintf(self::API_URL, $this->username) . '?' . http_build_query(array(
			'fromdate' => $latest_id + 1
		), null, '&');

		$data = json_decode(file_get_contents($url));
		
		// Check if there's actually data first!
		if (empty($data->user_timelines))
			return;
		
		foreach ($data->user_timelines as $item)
		{
			// Ignore unknown post types
			if (!in_array($item->timeline_type, array('askoranswered', 'comment', 'accepted')))
				continue;
				
			$this->saveToDB($item->creation_date, $item->creation_date, $item->description, null, null, array(
				'type' => $item->timeline_type,
				'action' => $item->action,
				'post_id' => $item->post_id,
				'post_type' => $item->post_type,
			));
			
			$latest_id = max($latest_id, $item->creation_date);
		}
		
		$this->latest_id = $latest_id;
	}

	public function loadFromDB($row)
	{
		$result = (object)array(
			'id' => $row->id,
			'date' => $row->date,
			'type' => 'stackoverflow',
			'url' => $this->getQuestionUrl($row->extra_data['post_id']),
		);
		
		if ($row->extra_data['type'] == 'comment')
		{
			$result->text = 'commented on <a href="' . $this->getQuestionUrl($row->extra_data['post_id']) . '" rel="nofollow">' . $row->text . '</a>';
		}
		elseif ($row->extra_data['type'] == 'askoranswered')
		{			
			$result->text = $row->extra_data['action'] . ' <a href="' . $this->getQuestionUrl($row->extra_data['post_id']) .  '" rel="nofollow">' . $row->text . '</a>';
		}
		elseif ($row->extra_data['type'] == 'accepted')
		{
			$result->text = 'accepted answer to <a href="' . $this->getQuestionUrl($row->extra_data['post_id']) .  '" rel="nofollow">' . $row->text . '</a>';
		}
		
		return $result;
	}
	
	protected static function getQuestionUrl($questionId)
	{
		return 'http://stackoverflow.com/questions/' . $questionId;
	}
}
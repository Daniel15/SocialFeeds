<?php
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
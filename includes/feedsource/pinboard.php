<?php
/**
 * Implementation of Pinboard.in feed reader
 */
class FeedSource_Pinboard extends FeedSource
{
	public function doUpdate()
	{
		$url = 'http://feeds.pinboard.in/rss/u:' . $this->username;
		$data = simplexml_load_file($url);
		// Temporary hack until Pinboard fix the character encoding issues
		/*$data = utf8_encode(file_get_contents($url));
		$data = simplexml_load_string($data);*/

		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;

		foreach ($data->item as $item)
		{
			$dc = $item->children('http://purl.org/dc/elements/1.1/');			
			$time = strtotime($dc->date);
			
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;
			
			$this->saveToDB($time, $time, $item->title, trim((string)$item->description), $item->link, null);
			
			$latest_id = max($latest_id, $time);
		}
		
		$this->latest_id = $latest_id;
	}
	
	public function loadFromDB($row)
	{
		return (object)array(
			'id' => $row->id,
			'text' => 'Shared: <a href="' . $row->url . '"  rel="nofollow">' . $row->text . '</a>',
			'subtext' => null,
			'description' => $row->description,
			'url' => $row->url,
			'date' => $row->date,
			'type' => 'pinboard',
		);
	}
}
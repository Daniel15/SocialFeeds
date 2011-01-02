<?php
/**
 * Implementation of generic RSS feed reader
 */
class FeedSource_Rss extends FeedSource
{
	public function doUpdate()
	{
		$data = simplexml_load_file($this->username);
		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;
		
		foreach ($data->channel->item as $item)
		{
			$time = strtotime($item->pubDate);
			
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;
				
			// Grab the link - But replace with original link if FeedBurner
			$link = $item->link;
			if (!empty($item->children('http://rssnamespace.org/feedburner/ext/1.0')->origLink))
				$link = $item->children('http://rssnamespace.org/feedburner/ext/1.0')->origLink;
				
			$this->saveToDB($time, $time, $item->title, (string)$item->description, $link, null);
				
			$latest_id = max($latest_id, $time);
		}
		
		$this->latest_id = $latest_id;
	}
	
	public function loadFromDB($row)
	{
		return (object)array(
			'id' => $row->id,
			'text' => $this->extra_params['prefix'] . '<a href="' . $row->url . '" rel="nofollow">' . $row->text . '</a>',
			'subtext' => 'via <a href="' . $this->extra_params['url'] . '">' . $this->extra_params['name'] . '</a>',
			'description' => $row->description,
			'url' => $row->url,
			'date' => $row->date,
			'type' => 'rss',
		);
	}
}
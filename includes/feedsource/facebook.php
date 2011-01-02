<?php
/*class FeedSource_Facebook extends FeedSource
{
	public function doUpdate()
	{
		//$data = json_decode(file_get_contents('http://graph.facebook.com/' . $this->username . '/posts/?access_token=' . urlencode($this->extra_params['access_token'])));
		$data = json_decode(file_get_contents('c:/temp/facebook.json'));
		
		//protected function saveToDB($original_id, $date, $text, $description, $url, $extra)
		
		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;
		
		foreach ($data->data as $item)
		{
			$time = strtotime($item->created_time);
			
			// Skip it if it's older than the latest item
			if ($time <= $this->latest_id)
				continue;
			$latest_id = max($latest_id, $time);
				
			// Figure out what to use based on type
			$link = null;
			$actions = array();
			switch ($item->type)
			{
				case 'video':
					$text = $item->name;
					$link = $item->source;
					
					if ($item->attribution == 'YouTube')
					{
						$text = $item->caption . ': <a href="' . $link . '">' . $text . '</a>';
					}
					break;
					
				case 'link':
					$text = $item->name;
					if (!empty($item->message))
						$text = $item->message;
						
					// If the caption is longer, it's probably better
					if (strlen($item->caption) > strlen($text))
						$text = $item->caption;
						
					$link = $item->link;
					break;
					
				default:
					$text = $item->message;
					break;
			}
			
			// Grab all the actions
			foreach ($item->actions as $action)
			{
				$actions[$action->name] = $action->link;
			}
			
			// If we don't have a link but do have a comments link, use that
			if ($link == null && isset($actions['Comment']))
				$link = $actions['Comment'];
				
			echo 'id = ' . $item->id . "\n";
			echo 'date = ' . $time . "\n";
			echo 'text = ' . str_replace("\n", ' ', $text) . "\n";
			echo 'link = ' . $link . "\n";
			echo 'actions = ' . serialize($actions) . "\n";
			if (!empty($item->attribution))
				echo 'attrib = ' . $item->attribution . "\n";
			
			if (!empty($item->description))
				echo 'desc = ' . $item->description;
			
			echo "\n";
		}
		
		//$this->latest_id = $latest_id;
	}
	
	public function loadFromDB($row)
	{
		throw new Exception('todo');
	}
}*/
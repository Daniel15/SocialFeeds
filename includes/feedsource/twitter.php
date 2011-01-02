<?php
class FeedSource_Twitter extends FeedSource
{
	const API_URL = 'http://api.twitter.com/1/statuses/user_timeline/%s.json';
	public function doUpdate()
	{
		$url = sprintf(self::API_URL, $this->username) . '?' . http_build_query(array(
			'include_rts' => 1,
			'count' => 200,
			'since_id' => $this->latest_id,
		), null, '&');
		
		$data = array_reverse(json_decode(file_get_contents($url)));
		foreach ($data as $item)
		{
			$this->saveToDB($item->id_str, strtotime($item->created_at), $item->text, null, null, array(
				'source' => $item->source,
				'reply' => !empty($item->in_reply_to_status_id_str),
				'in_reply_to_status' => $item->in_reply_to_status_id_str,
				'in_reply_to_user' => $item->in_reply_to_screen_name,
			), $this->shouldShowTweet($item));
		}
		
		if (count($data) != 0)
			$this->latest_id = $data[count($data) - 1]->id_str;
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
			$result->subtext .= ' in reply to <a href="http://twitter.com/' . $row->extra_data['in_reply_to_user'] . '/statuses/' .  $row->extra_data['in_reply_to_status']. '">' . $row->extra_data['in_reply_to_user'] . '</a>';
		}
		
		// "Linkify" the links
		$result->text = preg_replace('~(http://\S+)~i', '<a href="$1">$1</a>', $result->text);
		// Linkify Twitter profiles
		$result->text = preg_replace('~@([A-Z0-9]+)~i', '<a href="http://www.twitter.com/$1" title="Twitter profile for $1"  rel="nofollow">@$1</a>', $result->text);
		// Linkify Twitter hashtags
		$result->text = preg_replace('~#(\S+)~i', '<a href="http://search.twitter.com/search?q=%23$1" title="Twitter hashtag \'$1\'"  rel="nofollow">#$1</a>', $result->text);
		
		return (object)$result;
	}
	
	protected static function getTweetUrl($username, $tweetId)
	{
		return 'http://twitter.com/' . $username . '/statuses/' . $tweetId;
	}
}
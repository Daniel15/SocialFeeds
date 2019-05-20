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
 * All feed sources should inherit from this class.
 * Override doUpdate() to handle updating the feed data, and loadFromDB($row) to handle formatting a
 * row loaded from the database.
 */
abstract class FeedSource
{
	protected $db;
	protected $id;
	protected $username;
	protected $extra_params;
	protected $latest_id;
	protected $save_query;
	
	protected $inserted = 0;

	public function __construct($info)
	{
		$this->db = Database::getDB();
		$this->id = $info->id;
		$this->username = $info->username;
		$this->extra_params = unserialize($info->extra_params);
		$this->latest_id = $info->latest_id;
	}
	
	/**
	 * Update this particular source
	 */
	public function update()
	{
		echo 'Running update for ID ', $this->id, ' (', get_class($this), ')... ';
		$start = microtime(true);
		
		$this->save_query = $this->db->prepare('
			INSERT INTO items (source_id, original_id, date, text, description, extra_data, url, visible)
			VALUES (:source_id, :original_id, FROM_UNIXTIME(:date), :text, :description, :extra_data, :url, :visible)');
		
		try
		{
			$this->doUpdate();
			echo 'Update done, ', $this->inserted, ' items added. Update took ', round(microtime(true) - $start, 2) , " seconds\n";
		}
		catch (Exception $ex)
		{
			echo 'Update FAILED! Something bad happened!! ', $ex, "\n";
		}
		
		$this->db->prepare('
			UPDATE sources
			SET latest_id = :latest_id, last_update = NOW()
			WHERE id = :id')
			->execute(array(':latest_id' => $this->latest_id, ':id' => $this->id));
	}
	
	/**
	 * Save a new entry to the DB
	 * TODO: Make this take an array as a parameter, instead of individual parameters
	 * @param	string		The ID of the post on the original site (eg. Tweet ID on Twitter)
	 * @param	int			UNIX timestamp of the post
	 * @param	string		Main text of the feed item
	 * @param	string		Description or subtext of the feed item
	 * @param	string		URL relevant to the item
	 * @param	array		Any extra data required for displaying the item
	 * @param	bool		Whether to display the feed item, or hide it. Defaults to true (display it)
	 */
	protected function saveToDB($original_id, $date, $text, $description, $url, $extra, $visible = true)
	{		
		try
		{
			$this->save_query->execute(array(
				':source_id' => $this->id,
				':original_id' => $original_id,
				':date' => $date,
				':text' => $text,
				':description' => $description,
				':url' => $url,
				':extra_data' => serialize($extra),
				':visible' => $visible ? 1 : 0,
			));
		
			$this->inserted++;
		}
		catch (Exception $ex)
		{
			echo 'An insert FAILED! ', $ex, "\n";
		}
	}
	
	// Override these in FeedSources
	/**
	 * Update the feed with all the latest items
	 */
	public abstract function doUpdate();
	
	/**
	 * Load a feed item from the database. Should return an object with data
	 * TODO: Document return type
	 */
	public abstract function loadFromDB($row);
	
	// Helper functions
	/**
	 * Create a new instance of a source based off a DB row (Factory method)
	 */
	public static function factory($info)
	{
		$class_name = 'FeedSource_' . ucfirst($info->type);
		return new $class_name($info);
	}
}
?>
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
 
header('Content-Type: text/plain');
require 'includes/core.php';

echo "\n --- Starting update at ", date('Y-m-d g:i:s A'), " ---\n";

$db = Database::getDB();
$result = $db->query('
	SELECT id, type, username, extra_params, latest_id
	FROM sources
	WHERE active = 1');
	
while ($row = $result->fetchObject())
{
	$feed = FeedSource::factory($row);
	$feed->update();
}

/*echo 'Deleting duplicates... ';
$duplicates = FeedSources::removeDuplicateItems();
echo $duplicates, " duplicates removed.\n";*/

echo ' --- Finished update at ', date('Y-m-d g:i:s A'), " ---\n";
?>
<?php
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
<?php
require 'includes/core.php';

// Do we have a count?
$count = min(!empty($_REQUEST['count']) ? (int) $_REQUEST['count'] : 10, 100);
$before_date = !empty($_REQUEST['before_date']) ? (int) $_REQUEST['before_date'] : null;

header('Content-type: application/json');
echo json_encode(FeedSources::loadItems($count, $before_date));
?>
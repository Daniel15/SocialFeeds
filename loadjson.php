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
require 'includes/core.php';

// Do we have a count?
$count = min(!empty($_REQUEST['count']) ? (int) $_REQUEST['count'] : 10, 100);
$before_date = !empty($_REQUEST['before_date']) ? (int) $_REQUEST['before_date'] : null;

header('Content-type: application/json');
echo json_encode(FeedSources::loadItems($count, $before_date), JSON_PARTIAL_OUTPUT_ON_ERROR);
?>

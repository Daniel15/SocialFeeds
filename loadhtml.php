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
header('Content-Type: text/html; charset=utf-8');
?><ul id="socialfeed"><?php
require 'includes/core.php';

// Do we have a count?
$count = min(!empty($_REQUEST['count']) ? (int) $_REQUEST['count'] : 25, 100);
$before_date = !empty($_REQUEST['before_date']) ? (int) $_REQUEST['before_date'] : null;

$items = FeedSources::loadItems($count, $before_date);
$oldest_item = $items[count($items) - 1]->date;

foreach ($items as $item)
{
	echo '
	<li id="feeditem_', $item->id, '" class="feeditem source-', $item->type, '" data-date="', $item->date, '">
		', $item->text;
		
	if (!empty($item->description))
	{
		echo '
		<blockquote>', $item->description, '</blockquote>';
	}
	
	echo '
		<ul class="meta" title="Via ', ucfirst($item->type), '">
			<li class="date" title="', date('jS F Y g:i:s A', $item->date), '">', $item->relativeDate, '</li>';
		
	// Any sub text?
	if (!empty($item->subtext))
	{
		echo '
			<li class="subtext">', $item->subtext, '</li>';
	}
	
	// Any link?
	if (!empty($item->url))
	{
		echo '
			<li><a href="', $item->url, '" target="_blank">View</a></li>';
	}
			
	echo '
		</ul>';
	
	echo '
	</li>';
}
?>

</ul>
<a href="feed.htm?before_date=<?php echo $oldest_item ?>" id="loadMore">View more!</a>
<ul id="socialfeed"><?php
require 'includes/core.php';

// Do we have a count?
$count = min(!empty($_REQUEST['count']) ? (int) $_REQUEST['count'] : 30, 100);
$before_date = !empty($_REQUEST['before_date']) ? (int) $_REQUEST['before_date'] : null;

$items = FeedSources::loadItems($count, $before_date);
$oldest_item = $items[count($items) - 1]->date;

foreach ($items as $item)
{
	echo '
	<li id="feeditem_', $item->id, '" class="feeditem source-', $item->type, '">
		', $item->text;
		
	if (!empty($item->description))
	{
		echo '
		<blockquote>', $item->description, '</blockquote>';
	}
	
	echo '
		<ul class="meta" title="Via ', ucfirst($item->type), '">
			<li class="date" title="', date('jS F Y g:i:s A', $item->date), '">', Date::timeDiffInWords($item->date), '</li>';
		
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
<a href="feed-nojs.htm?before_date=<?php echo $oldest_item ?>">View more!</a>
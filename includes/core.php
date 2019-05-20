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

define('BASEDIR', __DIR__);
set_include_path(BASEDIR . PATH_SEPARATOR . get_include_path());

/**
 * Load the class specified 
 * @param	string		Class to load
 */
function autoload($className)
{
	/* We're using the built-in autoloader as it's faster than a PHP implementation.
	 * Replace underscores with slashes to use a directory structure (FeedSources_Twitter -> includes/FeedSources/Twitter.php)
	 */
	$filename = strtolower(str_replace('_', '/', $className));
	return spl_autoload($filename);
}

spl_autoload_register('autoload');

require __DIR__.'/../vendor/autoload.php';
?>
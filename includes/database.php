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
class Database
{
	protected static $hostname;
	protected static $db;
	protected static $dbname;
	protected static $username;
	protected static $password;
	
	public static function setDetails($hostname, $dbname, $username, $password)
	{
		self::$hostname = $hostname;
		self::$dbname = $dbname;
		self::$username = $username;
		self::$password = $password;
	}
	
	public static function getDB()
	{
		if (self::$db == null)
		{
			self::$db = new PDO('mysql:host=' . self::$hostname . ';dbname=' . self::$dbname, self::$username, self::$password);
			self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		
		return self::$db;
	}
}

Database::setDetails('socialfeeds', 'root', 'password');
?>

<?php
class Database
{
	protected static $db;
	protected static $dbname;
	protected static $username;
	protected static $password;
	
	public static function setDetails($dbname, $username, $password)
	{
		self::$dbname = $dbname;
		self::$username = $username;
		self::$password = $password;
	}
	
	public static function getDB()
	{
		if (self::$db == null)
		{
			self::$db = new PDO('mysql:host=localhost;dbname=' . self::$dbname, self::$username, self::$password, array(PDO::ATTR_PERSISTENT => true));
			self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		
		return self::$db;
	}
}

Database::setDetails('socialfeeds', 'root', 'password');
?>
<?php

namespace Rss\Util\Db;

/**
 *
 * @author user
 *        
 */
class PostgreSQL {
	
	protected static $PostgreSQL;
	
	/**
	 * Returns a PDO connection for shared use
	 * 
	 * @return \PDO
	 */
	public static function getPDO($postgresql) {
		
		global $logger;
		$dbopts = parse_url($postgresql);
		$dbname = ltrim($dbopts["path"],'/');
		$host = $dbopts["host"];
		$username = $dbopts["user"];
		$password = $dbopts["pass"];
	
		try {
			if (! self::$PostgreSQL) {
				self::$PostgreSQL = new \PDO ( "pgsql:dbname=$dbname;host=$host", $username, $password );
				self::$PostgreSQL->setAttribute ( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
			}
			return self::$PostgreSQL;
		} catch (\Exception $e) {
			$m = 'Error connecting to PostgreSQL ' . $e->getCode () . ' ' . $e->getMessage ();
			$logger->logError ( $m );
			exit ( 1 );
		}

	} // function getPDO()
} // class PostgreSQL

?>
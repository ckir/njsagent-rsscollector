<?php

namespace Rss\Util\Db;

class MySQL {
	protected static $MySQL;
	
	/**
	 * Returns a PDO connection for shared use
	 *
	 * @return \PDO
	 */
	public static function getPDO($mysql) {
	
	    global $logger;
	    $dbopts = parse_url($mysql);
	    $dbname = ltrim($dbopts["path"],'/');
	    $host = $dbopts["host"];
	    $port = $dbopts["port"];
	    $username = $dbopts["user"];
	    $password = $dbopts["pass"];
	    $dsn = $dbopts["scheme"] . ":host=$host;port=$port;dbname=$dbname";
	
	    try {
			if (! self::$MySQL) {
				self::$MySQL = new \PDO ( $dsn, $username, $password, array (
						\PDO::ATTR_TIMEOUT => 10000 
				) );
				self::$MySQL->setAttribute ( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
				self::$MySQL->exec ( "SET CHARACTER SET utf8" );
			}
			return self::$MySQL;
	    } catch (\Exception $e) {
	        $m = 'Error connecting to MySQL ' . $e->getCode () . ' ' . $e->getMessage ();
	        $logger->logError ( $m );
	        exit ( 1 );
	    }
	
	} // function getPDO()	

} // class MySQL
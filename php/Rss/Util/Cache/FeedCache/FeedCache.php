<?php

namespace Rss\Util\Cache\FeedCache;

/**
 *
 * @author user
 *        
 */
class FeedCache {
	protected $logger;
	protected $cacheremote;
	protected $cacheremoteinsert = "INSERT INTO cache (title, link, stemmed, metaphone) values (?, ?, ?, ?)";
	protected $cacheremoteclear = "TRUNCATE TABLE cache RESTART IDENTITY";
	protected $cachelocal;
	protected $cachelocalinsert = "INSERT OR IGNORE INTO cachedb (title, link, stemmed, metaphone) values (?, ?, ?, ?)";
	protected $cachelocalcopy = "INSERT OR IGNORE INTO cachedb (itemid, title, link, stemmed, metaphone, ts) values (?, ?, ?, ?, ?, ?)";
	protected $cachelocalsearch = "SELECT COUNT(link) AS found FROM cachedb WHERE (link = ?) OR (metaphone = ?) OR SIMILARITY(stemmed, ?) OR ALLTERMS(stemmed, ?)";
	protected $cachelocalclear = "DELETE FROM cachedb; VACUUM";
	
	/**
	 */
	function __construct($postgresql) {
		global $logger;
		$this->logger = $logger;
		
		// Connect to remote cache database
		$this->cacheremote = \Rss\Util\Db\PostgreSQL::getPDO ( $postgresql );
		$this->cacheremoteinsert = $this->cacheremote->prepare ( $this->cacheremoteinsert );
		
		$this->cachelocal = $this->cache_local_connect ();
		$this->cache_local_copy ();
	} // function __construct
	
	private function fail($message) {
		$this->logger->logError ( $message );
		exit ( 1 );
	}
	
	/**
	 * Connect to local sqlite db
	 *
	 * @param string $locking_mode        	
	 * @return \PDO
	 */
	private function cache_local_connect($locking_mode = 'NORMAL') {
		try {
			$table = <<<EOD
CREATE TABLE "cachedb" (
"itemid"  INTEGER PRIMARY KEY AUTOINCREMENT,
"title"  TEXT,
"link"  TEXT,
"stemmed"  TEXT,
"metaphone"  TEXT,
"ts"  DATETIME DEFAULT (datetime( 'now', 'utc'))
);
EOD;
			$cachelocal = new \PDO ( 'sqlite::memory:' );
			$cachelocal->setAttribute ( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
			$cachelocal->setAttribute ( \PDO::ATTR_TIMEOUT, 20 );
			$cachelocal->exec ( "PRAGMA locking_mode = $locking_mode" );
			$cachelocal->exec ( $table );
			
			// Create custom function for similar titles
			$cachelocal->sqliteCreateFunction ( 'similarity', array (
					'Rss\Util\Cache\FeedCache\FeedCache',
					'SIMILARITY' 
			), 2 );
			
			// Create custom function for all terms in a title included in a published one.
			$cachelocal->sqliteCreateFunction ( 'allterms', array (
					'Rss\Util\Cache\FeedCache\FeedCache',
					'ALLTERMS' 
			), 2 );
			
			return $cachelocal;
		} catch ( \Exception $e ) {
			$m = 'Error connecting to sqlite ' . $e->getCode () . ' ' . $e->getMessage ();
			$this->fail ( $m );
		}
	} // function cache_local_connect
	
	/**
	 */
	private function cache_local_copy() {
		try {
			$remoterecords = $this->cacheremote->query ( 'SELECT * FROM cache' );
			$remoterecords = $remoterecords->fetchAll ( \PDO::FETCH_ASSOC );
			$cachelocalcopy = $this->cachelocal->prepare ( $this->cachelocalcopy );
			foreach ( $remoterecords as $row ) {
				$cachelocalcopy->bindParam ( 1, $row ['id'], \PDO::PARAM_STR );
				$cachelocalcopy->bindParam ( 2, $row ['title'], \PDO::PARAM_STR );
				$cachelocalcopy->bindParam ( 3, $row ['link'], \PDO::PARAM_STR );
				$cachelocalcopy->bindParam ( 4, $row ['stemmed'], \PDO::PARAM_STR );
				$cachelocalcopy->bindParam ( 5, $row ['metaphone'], \PDO::PARAM_STR );
				$cachelocalcopy->bindParam ( 6, $row ['foundtimestamp'], \PDO::PARAM_STR );
				$cachelocalcopy->execute ();
			}
			$this->logger->logTrace ( 'Copied ' . count ( $remoterecords ) . ' records from remote.' );
			return true;
		} catch ( \Exception $e ) {
			$m = $row ['id'] . ',' . $row ['title'] . ',' . $row ['link'] . ',' . $row ['stemmed'] . ',' . $row ['metaphone'] . ',' . $row ['foundtimestamp'];
			$m = 'Error copying (' . $m . ') to sqlite ' . $e->getCode () . ' ' . $e->getMessage ();
			$this->fail ( $m );
		}
	} // function cache_local_copy
	
	/**
	 *
	 * @param string $title        	
	 * @param string $link        	
	 * @param string $stemmed        	
	 * @param string $metaphone        	
	 * @return boolean
	 */
	private function cache_insert($title, $link, $stemmed, $metaphone) {
		$link = $this->get_link ( $link );
		return $this->cache_remote_insert ( $title, $link, $stemmed, $metaphone ) && $this->cache_local_insert ( $title, $link, $stemmed, $metaphone );
	}
	
	/**
	 *
	 * @param string $title        	
	 * @param string $link        	
	 * @param string $stemmed        	
	 * @param string $metaphone        	
	 * @return boolean
	 */
	private function cache_local_insert($title, $link, $stemmed, $metaphone) {
		try {
			$cachelocalinsert = $this->cachelocal->prepare ( $this->cachelocalinsert );
			$cachelocalinsert->bindParam ( 1, $title, \PDO::PARAM_STR );
			$cachelocalinsert->bindParam ( 2, $link, \PDO::PARAM_STR );
			$cachelocalinsert->bindParam ( 3, $stemmed, \PDO::PARAM_STR );
			$cachelocalinsert->bindParam ( 4, $metaphone, \PDO::PARAM_STR );
			
			$cachelocalinsert->execute ();
			
			return true;
		} catch ( \Exception $e ) {
			$m = $title . ',' . $link . ',' . $stemmed . ',' . $metaphone;
			$m = 'Error inserting (' . $m . ') to sqlite' . $e->getCode () . ' ' . $e->getMessage ();
			$this->fail ( $m );
		}
	} // function cache_local_insert
	
	/**
	 *
	 * @param string $title        	
	 * @param string $link        	
	 * @param string $stemmed        	
	 * @param string $metaphone        	
	 * @return boolean
	 */
	private function cache_remote_insert($title, $link, $stemmed, $metaphone) {
		if (! gettype ( $this->cacheremoteinsert ) == 'object') {
			return false;
		}
		try {
			$this->cacheremoteinsert->bindParam ( 1, $title, \PDO::PARAM_STR );
			$this->cacheremoteinsert->bindParam ( 2, $link, \PDO::PARAM_STR );
			$this->cacheremoteinsert->bindParam ( 3, $stemmed, \PDO::PARAM_STR );
			$this->cacheremoteinsert->bindParam ( 4, $metaphone, \PDO::PARAM_STR );
			$this->cacheremoteinsert->execute ();
			return true;
		} catch ( \Exception $e ) {
			$m = $title . ',' . $link . ',' . $stemmed . ',' . $metaphone;
			$m = 'Error inserting (' . $m . ') to PostgreSQL ' . $e->getCode () . ' ' . $e->getMessage ();
			$this->fail ( $m );
		}
	} // function cache_remote_insert
	

	/**
	 * @param array $article
	 * @return boolean
	 */
	public function cache_search($article) {
		$article ['link'] = $this->get_link ( $article ['link'] );
		$link = $this->cachelocal->quote ( $article ['link'] );
		$metaphone = $this->cachelocal->quote ( $article ['titlemetaphone'] );
		$stemmed = $this->cachelocal->quote ( $article ['titlestemmed'] );
		$cachelocalsearch = "SELECT COUNT(link) AS found FROM cachedb WHERE (link = $link) OR (metaphone = $metaphone) OR SIMILARITY(stemmed, $stemmed) OR ALLTERMS(stemmed, $stemmed)";
		
		try {
			$cachelocalsearch = $this->cachelocal->query ( $cachelocalsearch );
			$results = $cachelocalsearch->fetchAll ( \PDO::FETCH_ASSOC );
			$results = ( int ) $results [0] ['found'];
		} catch ( \Exception $e ) {
			$m = $article ['link'] . ',' . $article ['titlemetaphone'] . ',' . $article ['titlestemmed'] . ',' . $article ['titlestemmed'];
			$m = "Can't search (" . $m . ") in local cache because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logWarn ( $m );
			return true;
		}
		if ($results > 0) {
			// Found in cache
			return true;
		}
		// Not Found in cache
		$updated = $this->cache_insert ( $article ['title'], $article ['link'], $article ['titlestemmed'], $article ['titlemetaphone'] );
		return false;
	} // function cache_search
	
	/**
	 *
	 * @param string $link        	
	 * @return number boolean
	 */
	public function cache_search_link($link) {
		$link = $this->get_link ( $link );
		$link = $this->cachelocal->quote ( $link );
		$cachelocalsearch = "SELECT COUNT(link) AS found FROM cachedb WHERE (link = $link)";
		
		try {
			$cachelocalsearch = $this->cachelocal->query ( $cachelocalsearch );
		} catch ( \Exception $e ) {
			$m = "Can't search (" . $link . ") link in local cache because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->fail ( $m );
		}
		
		$results = $cachelocalsearch->fetchAll ( \PDO::FETCH_ASSOC );
		$results = ( int ) $results [0] ['found'];
		return $results;
	} // function cache_search_link
	
	/**
	 *
	 * @return boolean
	 */
	public function cache_clear() {
		return $this->cache_local_clear () && $this->cache_remote_clear ();
	}
	
	/**
	 *
	 * @return boolean
	 */
	private function cache_remote_clear() {
		try {
			$this->cacheremote->exec ( $this->cacheremoteclear );
			return true;
		} catch ( \Exception $e ) {
			$m = "Can't clean remote cache because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
			return false;
		}
	} // function cache_remote_clear
	
	/**
	 *
	 * @return boolean
	 */
	private function cache_local_clear() {
		try {
			$this->cachelocal->exec ( $this->cachelocalclear );
			return true;
		} catch ( \Exception $e ) {
			$m = "Can't clean local cache because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
			return false;
		}
	} // function cache_local_clear
	
	/**
	 * Custom sqlite function to calculate published titles similarity.
	 *
	 * @param string $dbStemmed        	
	 * @param string $stemmed        	
	 * @return boolean
	 */
	public static function similarity($dbStemmed, $stemmed) {
		if (! $dbStemmed) {
			return false;
		}
		
		$similarity = similar_text ( $dbStemmed, $stemmed, $percent );
		
		if ($percent >= 80) {
			return true;
		}
		return false;
	} // function similarity
	
	/**
	 * Custom sqlite function to calculate
	 * if all words of the shortest string included in the
	 * longest string return true else return false.
	 *
	 * @param string $terma        	
	 * @param string $termb        	
	 * @return boolean
	 */
	public static function allterms($terma, $termb) {
		// Convert the shortest string into terms
		if (strlen ( $terma ) > strlen ( $termb )) {
			$regex = self::toRegexAnd ( $termb );
			$match = preg_match ( $regex, $terma );
			if ($match) {
				return true;
			} else {
				return false;
			}
		} else {
			$regex = self::toRegexAnd ( $terma );
			$match = preg_match ( $regex, $termb );
			if ($match) {
				return true;
			} else {
				return false;
			}
		}
	} // function allterms
	
	/**
	 * Returns a regular expression for matching all terms in $string using logical AND.
	 * This is sqlite version
	 * $regex .= '(?=.*\b' . $word . '\b)'; (PHP version)
	 * $regex .= '(\?=.*\b' . $word . '\b)'; (Mysql version)
	 *
	 * @param unknown $string        	
	 * @param unknown $excludes        	
	 * @return string
	 */
	private static function toRegexAnd($string, $excludes = array()) {
		$regex = '/^';
		$words = preg_split ( '/((^\p{P}+)|(\p{P}*\s+\p{P}*)|(\p{P}+$))/', $string, - 1, PREG_SPLIT_NO_EMPTY );
		$words = array_diff ( $words, $excludes );
		$words = array_unique ( $words );
		foreach ( $words as $word ) {
			$regex .= '(?=.*\b' . $word . '\b)';
		}
		$regex .= '.*$/';
		return $regex;
	} // function toRegexAnd
	
	/**
	 *
	 * @param string $link        	
	 * @return string
	 */
	private function get_link($link) {
		// Google & Bing news are collections from multiply sources.
		// We keep the description but set the link to final url.
		$linkparts = parse_url ( $link );
		
		// Yahoo adds some tracking at the end. Lets remove it
// 		if (preg_match ( '/yahoo/', $linkparts ['host'] )) {
// 			$link = explode ( ';', $link );
// 			$link = $link [0];
// 			if (isset ( $linkparts ['path'] )) {
// 				$path = preg_match ( "/RU=.*\/RK/", $linkparts ['path'], $matches );
// 				$matches = $matches [0];
// 				$matches = substr ( $matches, 3 );
// 				$matches = substr ( $matches, 0, - 3 );
// 				$validator = new \Zend\Validator\Uri ();
// 				if ($validator->isValid ( $matches )) {
// 					$link = $matches;
// 				}
// 			}
// 		}
		
		// Google news are collections from multiply sources.
		// We keep the description but set the link to final url.
		if (preg_match ( '/google/', $linkparts ['host'] )) {
			if (isset ( $linkparts ['query'] )) {
				parse_str ( $linkparts ['query'], $query );
				$link = null;
				if (isset ( $query ['q'] )) {
					$link = $query ['q'];
				}
				if (isset ( $query ['url'] )) {
					$link = $query ['url'];
				}
			}
		}
		
		// Bing news are collections from multiply sources.
		// We keep the description but set the link to final url.
		if (preg_match ( '/bing/', $linkparts ['host'] )) {
			if (isset ( $linkparts ['query'] )) {
				parse_str ( $linkparts ['query'], $query );
				if (isset ( $query ['url'] )) {
					$link = $query ['url'];
				} else {
					$link = null;
				}
			}
		}
		
		$validator = new \Zend\Validator\Uri ();
		if (! $validator->isValid ( $link )) {
			return null;
		}
		
		return $link;
	} // function get_link
} // class FeedCache

?>
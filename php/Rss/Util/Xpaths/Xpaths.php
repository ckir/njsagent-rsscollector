<?php

namespace Rss\Util\Xpaths;

/**
 *
 * @author user
 *        
 */
class Xpaths {
	protected $logger;
	protected $db;
	protected $xpath = "SELECT * FROM xpaths WHERE (site = ?) LIMIT 1";
	protected $insert = "INSERT INTO xpaths (site) values (?)";
	protected $clean = "DELETE FROM xpaths WHERE (ts < now() - interval '30 days') AND (posts < 10)";
	protected $update = "UPDATE xpaths SET posts = posts + 1  WHERE (site = ?)";
	protected $xpfailed = "UPDATE xpaths SET xpathfailed = xpathfailed + 1  WHERE (site = ?)";
	
	/**
	 */
	function __construct($postgresql) {
		global $logger;
		$this->logger = $logger;
		$this->db = \Rss\Util\Db\PostgreSQL::getPDO ( $postgresql );
		$this->xpath = $this->db->prepare ( $this->xpath );
		$this->insert = $this->db->prepare ( $this->insert );
		$this->update = $this->db->prepare ( $this->update );
		$this->xpfailed = $this->db->prepare ( $this->xpfailed );
		$this->db->exec($this->clean);
	}
	
	/**
	 *
	 * @param string $link        	
	 * @return boolean
	 */
	public function get_xpath($link) {
		try {
			$host = $this->get_host ( $link );
			$this->xpath->bindParam ( 1, $host, \PDO::PARAM_STR );
			$this->xpath->execute ();
			$results = $this->xpath->fetchAll ( \PDO::FETCH_ASSOC );
			if (isset ( $results [0] ) && (count($results [0] > 0))) {
				return $results [0] ['xpath'];
			}
			return false;
		} catch ( \Exception $e ) {
			$m = $host;
			$m = "Can't search xpath for (" . $m . ") because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
			return null;
		}
	} // function get_xpath
	
	/**
	 *
	 * @param string $link        	
	 * @return boolean
	 */
	public function update_posts($link) {
		try {
			$host = $this->get_host ( $link );
			$this->update->bindParam ( 1, $host, \PDO::PARAM_STR );
			$this->update->execute ();
			return true;
		} catch ( \Exception $e ) {
			$m = $host;
			$m = "Can't update posts for (" . $m . ") because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
			return false;
		}
	} // function update_posts
	
	/**
	 * @param unknown $link
	 * @return boolean
	 */
	public function xpfailed($link) {
		try {
			$host = $this->get_host ( $link );
			$this->xpfailed->bindParam ( 1, $host, \PDO::PARAM_STR );
			$this->xpfailed->execute ();
			return true;
		} catch ( \Exception $e ) {
			$m = $host;
			$m = "Can't update xpfailed for (" . $m . ") because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
			return false;
		}
	} // function xpfailed	
	
	/**
	 *
	 * @param string $host        	
	 * @return boolean
	 */
	public function add_host($link) {
		$host = $this->get_host ( $link );
		try {
			$this->insert->bindParam ( 1, $host, \PDO::PARAM_STR );
			$this->insert->execute ();
			return true;
		} catch ( \Exception $e ) {
			$m = $host;
			$m = "Can't add (" . $m . ") to xpaths because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
			return false;
		}
	} // function add_host
	
	/**
	 *
	 * @param string $link        	
	 * @return mixed
	 */
	private function get_host($link) {
		$host = parse_url ( $link, PHP_URL_HOST );
		return $host;
	} // function get_host
} // class Xpaths

?>
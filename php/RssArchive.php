<?php

/** 
 * @author user
 * 
 */
class RssArchive {
	protected $logger;
	protected $db;
	protected $qryinsert = 'INSERT INTO archive (date) VALUES (?)';
	protected $qryselect = 'SELECT content FROM archive WHERE (date = ?)';
	protected $qryupdate = 'UPDATE archive SET content=? WHERE (date = ?)';
	
	/**
	 */
	function __construct($postgresql) {
		try {
			global $logger;
			$this->logger = $logger;
			$this->db = \Rss\Util\Db\PostgreSQL::getPDO ( $postgresql );
			$this->qryinsert = $this->db->prepare ( $this->qryinsert );
			$this->qryselect = $this->db->prepare ( $this->qryselect );
			$this->qryupdate = $this->db->prepare ( $this->qryupdate );
		} catch ( \Exception $e ) {
			$m = 'Error connecting to PostgreSQL ' . $e->getCode () . ' ' . $e->getMessage () . ' ' . $e->getFile ();
			$this->logger->logError ( $m );
		}
	} // function __construct
	
	/**
	 *
	 * @param unknown $feed        	
	 * @return boolean
	 */
	public function handle($feed) {
		$now = date ( "Y-m-d", time () );
		try {
			$this->qryselect->bindParam ( 1, $now );
			$this->qryselect->execute ();
			$results = $this->qryselect->fetchAll ( \PDO::FETCH_ASSOC );
		} catch ( \Exception $e ) {
			$m = 'Error selecting PostgreSQL ' . $e->getCode () . ' ' . $e->getMessage () . ' ' . $e->getFile ();
			$this->logger->logError ( $m );
			return false;
		}
		
		if (is_array ( $results ) && count ( $results ) == 0) {
			try {
				$this->qryinsert->bindParam ( 1, $now );
				$this->qryinsert->execute ();
			} catch ( \Exception $e ) {
				$m = 'Error inserting to PostgreSQL ' . $e->getCode () . ' ' . $e->getMessage () . ' ' . $e->getFile ();
				$this->logger->logError ( $m );
				return false;
			}
		}
		
		try {
			$content = null;
			if (count ( $results ) > 0) {
				$results = $results [0];
				$content = json_decode ( $results ["content"], true );
			}
			
			if (empty ( $content )) {
				$content = array ();
			}
			
			$content [] [date ( "Y-m-d H:i:s", time () )] = $feed;
			$content = json_encode ( $content );
			$this->qryupdate->bindParam ( 1, $content );
			$this->qryupdate->bindParam ( 2, $now );
			$this->qryupdate->execute ();
		} catch ( Exception $e ) {
			$m = 'Error updating PostgreSQL ' . $e->getCode () . ' ' . $e->getMessage () . ' ' . $e->getFile ();
			$this->logger->logError ( $m );
			return false;
		}
		return true;
	} // function handle
} // class RssArchive

?>
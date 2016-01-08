<?php
namespace Rss\Feed\Reader;

class ReadFailures {
	protected $logger;
	protected $db;
	protected $update = "UPDATE feeds SET feedfailures = feedfailures + 1, feedfailuresreason = ? WHERE feedurl = ? RETURNING feedurl, feedfailures";
	protected $deactivate = "UPDATE feeds SET feedenabled = 0 WHERE feedurl = ?";
	
	function __construct($postgresql) {
		global $logger;
		$this->logger = $logger;
		
		$this->db = \Rss\Util\Db\PostgreSQL::getPDO ( $postgresql );
		$this->update = $this->db->prepare ( $this->update );
		$this->deactivate = $this->db->prepare ( $this->deactivate );
	} // function __construct
	
	/**
	 * @param unknown $url
	 */
	public function update_failure($url, $reason) {

		try {
			$this->update->bindParam ( 1, $reason, \PDO::PARAM_STR );
			$this->update->bindParam ( 2, $url, \PDO::PARAM_STR );
			$this->update->execute ();
			$this->logger->logWarn("Updated failures for $url");
			$results = $this->update->fetchAll ( \PDO::FETCH_ASSOC );
			foreach ( $results as $result ) {
				if ($result ['feedfailures'] > 10) {
					$this->deactivate->bindParam ( 1, $url, \PDO::PARAM_STR );
					$this->deactivate->execute ();
					$this->logger->logError("Deactivated feed $url");					
				}
			}
		} catch ( \Exception $e ) {
			$m = "Can't update feed failures for ($url) because " . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
		}
	} // update_failure
} // class ReadFailures



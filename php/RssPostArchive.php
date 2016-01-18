<?php
class PostArchive {
	protected $logger;
	protected $db;
	protected $qryupsert = 'INSERT INTO collector (collector_date, collector_jdata) VALUES (DEFAULT, ?::jsonb) ON CONFLICT (collector_date) DO UPDATE set collector_jdata=jsonb_set(collector.collector_jdata::jsonb, \'{data,2147483647}\', ?::jsonb, true)';
	
	/**
	 *
	 * @param object $logger        	
	 */
	function __construct($postgresql) {
		try {
			global $logger;
			$this->logger = $logger;
			$this->db = \Rss\Util\Db\PostgreSQL::getPDO ( $postgresql );
			$this->qryupsert = $this->db->prepare ( $this->qryupsert );
		} catch ( \Exception $e ) {
			$m = 'Error connecting to PostgreSQL ' . $e->getCode () . ' ' . $e->getMessage () . ' ' . $e->getFile ();
			$this->logger->logError ( $m );
		}
	} // function __construct
	
	/**
	 * Save posting data to database
	 *
	 * @param array $data        	
	 * @return boolean
	 */
	public function insert($data) {
		try {
			
			if (! is_null ( $data ["published"] )) {
				$published = strtotime ( $data ["published"] );
				$published = date ( "Y-m-d H:i:s", $published );
			} else {
				$published = null;
			}
			if (isset($data ['labels']) && is_array($data ['labels'])) {
				$tags = implode ( ",", $data ['labels'] );
			} else {
				$tags = "Uncategorized";
			}
			
			$record = array('id' => $data ["id"], 'published' => $published, 'url' => $data ["url"], 'selfLink' => $data ["selfLink"], 'title' => $data ["title"], 'labels' => $tags, 'ts' => date ( "Y-m-d H:i:s", $published ));
			$record = json_encode($record);

			$this->qryupsert->bindParam ( 1, $record, PDO::PARAM_STR );
			$this->qryupsert->bindParam ( 2, $record, PDO::PARAM_STR );
			$this->qryupsert->execute ();
			return true;
		} catch ( \Exception $e ) {
			$m = 'Error writing (' . $record . ') to database ' . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
			return false;
		}
	} // function insert($data)
} // class PostArchive
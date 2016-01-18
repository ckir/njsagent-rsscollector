<?php
class PostArchive {
	protected $logger;
	protected $db;
	protected $insert = 'INSERT INTO `posts`(`id`, `published`, `url`, `selfLink`, `title`, `labels`) VALUES (?,?,?,?,?,?)';
	
	/**
	 *
	 * @param object $logger        	
	 */
	function __construct() {
		global $logger;
		$this->logger = $logger;
		$this->db =  \Rss\Util\Db\MySQL::getPDO ( getenv("RSSCOLLECTOR_MYSQL") );
		$this->insert = $this->db->prepare ( $this->insert );
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
			
			$this->insert->bindParam ( 1, $data ["id"], PDO::PARAM_INT );
			$this->insert->bindParam ( 2, $published, PDO::PARAM_STR );
			$this->insert->bindParam ( 3, $data ["url"], PDO::PARAM_STR );
			$this->insert->bindParam ( 4, $data ["selfLink"], PDO::PARAM_STR );
			$this->insert->bindParam ( 5, $data ["title"], PDO::PARAM_STR );
			$this->insert->bindParam ( 6, $tags, PDO::PARAM_STR );
			$this->insert->execute ();
			return true;
		} catch ( \Exception $e ) {
			$m = 'Error writing (' . json_encode ( $data ) . ') to database ' . $e->getCode () . ' ' . $e->getMessage ();
			$this->logger->logError ( $m );
			return false;
		}
	} // function insert($data)
} // class PostArchive
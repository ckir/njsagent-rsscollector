<?php

namespace Rss\Feed\Reader;

/**
 * A class to consume RSS and Atom feeds of any version,
 * including RDF/RSS 1.0, RSS 2.0, Atom 0.3 and Atom 1.0.
 * Performance is assisted in three ways. First of all,
 * Zend\Feed\Reader\Reader supports caching using Zend\Cache
 * to maintain a copy of the original feed XML. This allows you
 * to skip network requests for a feed URI if the cache is valid.
 * Second, the Feed and Entry level API is backed by an
 * internal cache (non-persistent) so repeat API calls for the same feed
 * will avoid additional DOM or XPath use.
 * Thirdly, importing feeds from a URI can take advantage of
 * HTTP Conditional GET requests which allow servers to issue
 * an empty 304 response when the requested feed has not changed
 * since the last time you requested it. In the final case,
 * an instance of Zend\Cache will hold the last received feed
 * along with the ETag and Last-Modified header values sent in
 * the HTTP response.
 *
 * @author user
 *        
 */
class Read {
	protected $logger;
	protected $postgresql;
	/**
	 */
	public function __construct($postgresql) {
		global $logger;
		$this->logger = $logger;
		$this->postgresql = $postgresql;
	}
	
	/**
	 * Import a feed by providing a URI
	 *
	 * @param string $uri
	 *        	The URI to the feed
	 * @param string $format
	 *        	The output format. Possible values xml or json (default).
	 * @return array
	 */
	public function feedRead($uri, $format = "json", $fixencoding = true) {		
		$zuri = \Zend\Uri\UriFactory::factory ( $uri );
		if (! $zuri->isValid ()) {
			$this->logger->logWarn ( "Invalid Uri ($uri)" );
			return false;
		}
		
		$cache = \Zend\Cache\StorageFactory::factory ( array (
				'adapter' => array (
						'name' => 'filesystem',
						'options' => array (
								'cache_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'cache',
								'ttl' => 3600 
						) 
				),
				'plugins' => array (
						array (
								'name' => 'serializer',
								'options' => array () 
						) 
				) 
		) );
		
		\Zend\Feed\Reader\Reader::setCache ( $cache );
		\Zend\Feed\Reader\Reader::useHttpConditionalGet ();
		
		// Change to firefox agent
		$httpClient = \Zend\Feed\Reader\Reader::getHttpClient ();
		$httpClient->setOptions ( array (
				'useragent' => 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0',
				'timeout' => 25 
		) );
		
		\Zend\Feed\Reader\Reader::setHttpClient ( $httpClient );
		
		// Import feed
		try {
			$feed = \Zend\Feed\Reader\Reader::import ( $uri );
			$httpClient = \Zend\Feed\Reader\Reader::getHttpClient ();
			$httpClientResponse = $httpClient->getResponse ();
			$feedencoding = $feed->getEncoding ();
			if (! $feedencoding) {
				$feedencoding = 'utf-8';
			}
			$feedResponse = array (
					'feed' => $uri,
					'statuscode' => $httpClientResponse->getStatusCode (),
					'headers' => $httpClientResponse->getHeaders ()->toArray (),
					'encoding' => $feedencoding 
			);
		} catch ( \Exception $e ) {
			$this->logger->logWarn ( "Zend feed reader cannot fetch: ($uri) because : " . $e->getMessage () . " trying casperjs" );
			$command = 'casperjs ' . __DIR__ . '/html.js --cookies-file=cookies.txt --url=' . escapeshellarg($uri);
			exec ( $command, $response, $return_var );
			$response = $this->strip_json ( $response );
			if (($return_var !== 0) || (! isset ( $response ['content'] )) || empty ( $response ['content'] )) {
				$this->logger->logWarn ( "Cannot fetch: ($uri) exit code $return_var", array (
						'command' => $command,
						'response' => $response 
				) );
				$failed = new \Rss\Feed\Reader\ReadFailures ( $this->postgresql );
				$failed->update_failure ( $uri, json_encode(array('zend' => $e->getMessage(), 'casper' => $response)) );
				return false;
			}
			try {
				$feed = \Zend\Feed\Reader\Reader::importString ( $response ['content'] );
				$feedencoding = $feed->getEncoding ();
				if (! $feedencoding) {
					$feedencoding = 'utf-8';
				}
				$feedResponse = array (
						'feed' => $uri,
						'statuscode' => $response['http']['status'],
						'headers' => $response['http'],
						'encoding' => $feedencoding
				);
			} catch ( \Exception $e ) {
				$this->logger->logWarn ( "Cannot parse feed content from ($uri) because " . $e->getMessage () );
// 				$failed = new \Rss\Feed\Reader\ReadFailures ( $this->postgresql );
// 				$failed->update_failure ( $uri );
				return false;
			}
		}
		
		// Fix relative links
		$newdata = $this->fix_links ( $feed, $uri );
		// Fix encoding errors
		if ($fixencoding) {
			$newdata = $this->fix_encoding ( $newdata );
		}
		
		// Return XML
		if ($format === "xml") {
			$feedResponse ['data'] = $newdata;
			
			return $feedResponse;
		}
		
		// Reload fixed data
		try {
			$feed = \Zend\Feed\Reader\Reader::importString ( $newdata );
		} catch (\Exception $e) {
			$this->logger->logWarn ( "Cannot parse corrected feed content from ($uri) because " . $e->getMessage () );
			return false;
		}
		
		
		$data = array (
				'title' => $feed->getTitle (),
				'link' => $feed->getLink (),
				'date' => $this->get_feed_date($feed),
				'description' => $feed->getDescription (),
				'language' => $feed->getLanguage (),
				'entries' => array () 
		);
		
		foreach ( $feed as $entry ) {
			if (is_object ( $entry )) {
				$DateCreated = $entry->getDateCreated ();
				if (is_object ( $DateCreated )) {
					$DateCreated = $DateCreated->getTimestamp ();
				} else {
					$DateCreated = $data['date'];
				}
				$DateModified = $entry->getDateModified ();
				if (is_object ( $DateModified )) {
					$DateModified = $DateModified->getTimestamp ();
				} else {
					$DateModified = $data['date'];
				}
				if (empty($DateModified) ) {
					$DateModified = time();
				}
				if (empty($DateCreated) ) {
					$DateCreated = time();
				}								

				$description = $entry->getDescription();
				$content = $entry->getContent ();
				if (empty($description)) {
					$description = $content;
				}
				if (empty($content)) {
					$content = $description;
				}
					
				$edata = array (
						'title' => $entry->getTitle (),
						'description' => $description,
						'dateCreated' => $DateCreated,
						'dateModified' => $DateModified,
						// 'authors' => $entry->getAuthors (),
						'link' => $entry->getLink (),
						'content' => $content,
						'feed' => $uri 
				);
				if ($this->check_missing($edata) ) {
					$data ['entries'] [] = $edata;
				} else {
					$this->logger->logTrace("Missing data from feed $uri " . json_encode($edata, JSON_UNESCAPED_UNICODE));
				}
			}
		}
		
		// Return array
		$feedResponse ['data'] = $data;
		
		return $feedResponse;
	} // function getFeed
	
	/**
	 * Replace smart quotes
	 *
	 * @param string $xml        	
	 * @return string
	 */
	private function fix_encoding($xml) {
		// Fix text
		$doc = new \DOMDocument ( '1.0' );
		@$doc->loadXML ( $xml );
		$xpath = new \DOMXpath ( $doc );
		foreach ( $xpath->query ( '//text()' ) as $node ) {
			if (preg_match ( '/â€/', $node->nodeValue )) {
				$node->nodeValue = \Rss\Util\Helpers\Helpers::fix_smartquotes ( $node->nodeValue );
			}
		}
		
		return $doc->saveXML ();
	} // function fix_encoding
	
	/**
	 *
	 * @param Zend\Feed\Reader\Feed\Rss $feed        	
	 * @return string
	 */
	private function fix_links($feed, $uri) {
		// Fix relative links
		$feedhost = $feed->getLink ();
		$feedhost = parse_url ( $feedhost, PHP_URL_HOST );
		if (! $feedhost) {
			$feedhost = parse_url ( $uri, PHP_URL_HOST );
			$feedhost = explode ( ".", $feedhost );
			$feedhost [0] = "www";
			$feedhost = implode ( ".", $feedhost );
		}
		
		$feeddata = $feed->saveXml ();
		
		$doc = new \DOMDocument ( '1.0', 'UTF-8' );
		@$doc->loadXML ( $feeddata );
		
		$xpath = new \DOMXpath ( $doc );
		foreach ( $xpath->query ( '//link' ) as $node ) {
			$link = $node->nodeValue;
			$link = \Rss\Util\Helpers\Helpers::unquote ( trim ( $link ), '""\'\'' );
			$link = rawurldecode ( $link );
			$link = html_entity_decode ( $link, ENT_QUOTES, "UTF-8" );
			$link = htmlspecialchars_decode ( $link, ENT_QUOTES );
			$link = filter_var ( $link, FILTER_SANITIZE_URL );
			$linkhost = parse_url ( $link, PHP_URL_HOST );
			$node->nodeValue = htmlentities ( $link );
			if ($linkhost) {
				continue;
			}
			$link = "http://" . $feedhost . $link;
			$node->nodeValue = htmlentities ( $link );
		}
		
		return $doc->saveXML ();
	} // function fix_links
	
	/**
	 *
	 * @param unknown $response        	
	 * @return multitype:mixed
	 */
	private function strip_json($response) {
		if (! is_array ( $response )) {
			$response = explode ( "\n", $response );
		}
		
		$jsons = array ();
		foreach ( $response as $line ) {
			try {
				$json = json_decode ( $line, true );
				$jsons [] = $json;
				break;
				// Should be only one valid json line
			} catch ( \Exception $e ) {
				continue; // Not a valid json object
			}
		}
		return $jsons [0];
	} // function strip_json
	
	/**
	 * @param array $testarray
	 * @return boolean
	 */
	private function check_missing(array $testarray) {
		foreach ($testarray as $key => $value) {
			if (empty($value)) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * @param unknown $feed
	 * @return NULL
	 */
	private function get_feed_date($feed) {
		$DateModified = $feed->getDateModified();
		if (is_object($DateModified)) {
			return $DateModified->getTimestamp ();
		}
		$LastBuildDate = $feed->getLastBuildDate();
		if (is_object($LastBuildDate)) {
			return $LastBuildDate->getTimestamp ();
		}
		$DateModified = $feed->getDateModified();
		if (is_object($DateModified)) {
			return $DateModified->getTimestamp ();
		}
		return null;	
	}
} // class Read

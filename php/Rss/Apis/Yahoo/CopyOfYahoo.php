<?php

namespace Rss\Apis\Yahoo;

/**
 *
 * @author user
 *        
 */
class Yahoo {
	private $api_url = "http://query.yahooapis.com/v1/public/yql";
	private $timeout = 25;
	protected $logger;
	
	/**
	 */
	function __construct() {
		global $logger;
		$this->logger = $logger;
	}
	
	/**
	 * Analyze text via Yahoo
	 *
	 * @param string $content        	
	 * @return array
	 */
	public function tag($content) {
		if (mb_strlen ( $content ) < 50) {
			return false;
		}
		
		$query = "SELECT * FROM contentanalysis.analyze WHERE text=" . "'" . htmlentities ( $content, ENT_QUOTES ) . "'";
		
		$query = http_build_query ( array (
				"q" => $query,
				"format" => "json" 
		) );
		
		$ch = curl_init ();
		
		curl_setopt ( $ch, CURLOPT_URL, $this->api_url );
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $query );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, true );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, $this->timeout );
		
		$response = curl_exec ( $ch );
		$info = curl_getinfo ( $ch );
		curl_close ( $ch );
		
		if ((int) $info['http_code'] >= 400) {
			$this->logger->logWarn("Yahoo error analyzing $content response $response", $info);
			return false;
		}		
		
		try {
			$json = json_decode ( $response, true );
			if (! isset ( $json ['query'] ['results'] )) {
				$this->logger->logWarn("Yahoo error analyzing $content response $response", $info);
				return false;
			}
		} catch ( \Exception $e ) {
			$this->logger->logWarn("Yahoo error analyzing $content " . $e->getMessage());
			return false;
		}
		
		return array (
				'info' => $info,
				'response' => $json,
				'tags' => $this->get_tags ( $json ['query'] ['results'] ) 
		);
	} // function tag
	
	/**
	 *
	 * @param unknown $json        	
	 * @return multitype:unknown
	 */
	protected function get_tags($json) {
		if ((! is_array ( $json )) || (! isset ( $json ['yctCategories'] ))) {
			return array ();
		}
		$tags = array ();
		
		foreach ( $json ['yctCategories'] as $yctCategories ) {
			if (isset ( $yctCategories ['content'] )) {
				$tags [$yctCategories ['content']] = floatval ( $yctCategories ['score'] );
			} else {
				foreach ( $yctCategories as $yctCategory ) {
					if (isset ( $yctCategory ['content'] )) {
						$tags [$yctCategory ['content']] = floatval ( $yctCategory ['score'] );
					}
				}
			}
		}
		arsort ( $tags, 1 );
		$tags = array_slice ( $tags, 0, 3 );
		return array_keys ( $tags );
	} // function get_tags
} // class TextYahoo

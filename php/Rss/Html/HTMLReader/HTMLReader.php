<?php

namespace Rss\Html\HTMLReader;

/**
 *
 * @author user
 *        
 */
class HTMLReader {
	protected $logger;
	protected $xpaths;
	
	/**
	 */
	function __construct($postgresql) {
		global $logger;
		$this->logger = $logger;
		$this->xpaths = new \Rss\Util\Xpaths\Xpaths ( $postgresql );
	}
	
	/**
	 * Fetch an optionally process a page by providing a URI.
	 *
	 * @param string $uri
	 *        	The URI to read.
	 * @param bool $html
	 *        	If true output will be html else output will be text
	 * @return array The ['info'] section of response contains valuable information including
	 *         http_request info
	 *         safety info
	 *         Trully detected document encoding
	 */
	public function getUrl($uri, $html = true) {
		$zuri = \Zend\Uri\UriFactory::factory ( $uri );
		if (! $zuri->isValid ()) {
			$this->logger->logWarn ( "Invalid Uri ($uri)" );
			return array (
					'redirectURL' => null,
					'contentextra' => null 
			);
		}
		
		// First test if url is responding
		
		// $command = 'timeout --preserve-status -k 90 -s SIGTERM 90 casperjs ' . __DIR__ . '/html.js --cookies-file=cookies.txt --url=' . escapeshellarg ( $uri );
		$command = 'cd ' . __DIR__ . '; timeout -k 90 -s SIGTERM 90 casperjs html.js --cookies-file=cookies.txt --url=' . escapeshellarg ( $uri );

		$this->logger->logDebug ( "Testing if ($uri) is responding " . $command);
		exec ( $command, $response, $return_var );
		$response = $this->strip_json ( $response );
		
		if (($return_var !== 0) || (! isset ( $response ['redirectURL'] )) || empty ( $response ['redirectURL'] )) {
			if (isset ( $response ['content'] )) {
				unset ( $response ['content'] );
			}
			$this->logger->logWarn ( "Cannot fetch: ($uri) exit code $return_var", array (
					'command' => $command,
					'response' => $response 
			) );
			return array (
					'redirectURL' => null,
					'contentextra' => null 
			);
		}
		if (! isset($response["loadtime"])) {
			$response["loadtime"] = null;
		}		
		$this->logger->logDebug ( "($uri) is responded in " . $response ['loadtime'] );
		// Then check if we have xpaths for the final url
		$redirectURL = $response ['redirectURL'];
		if (empty ( $redirectURL )) {
			$this->logger->logWarn ( "Invalid redirect URL ($redirectURL) for ($uri)" );
			return array (
					'redirectURL' => null,
					'contentextra' => null 
			);
		}
		$zuri = \Zend\Uri\UriFactory::factory ( $redirectURL );
		if (! $zuri->isValid ()) {
			$this->logger->logWarn ( "Invalid redirect URL ($redirectURL) for ($uri)" );
			return array (
					'redirectURL' => null,
					'contentextra' => null 
			);
		}
		if ($uri !== $redirectURL) {
			$this->logger->logDebug ( "($uri) redirects to ($redirectURL)" );
		}
		
		$xpath = $this->xpaths->get_xpath ( $redirectURL );
		// Host not found
		if ($xpath === false) {
			$this->xpaths->add_host ( $redirectURL );
			$this->logger->logTrace ( "($redirectURL) added to hosts" );
		}
		$this->xpaths->update_posts ( $redirectURL );
		$this->logger->logTrace ( "Updated posts for ($redirectURL)" );
		
		if (! $xpath) {
			$this->logger->logDebug ( "($redirectURL) does not have xpaths" );
			return array (
					'redirectURL' => $redirectURL,
					'contentextra' => null 
			);
		}
		
		// We will use casperjs to retrieve dynamically generated content
		$response = null;
		$return_var = null;
		sleep ( 1 );
		// $command = 'timeout -k 90 -s SIGTERM 90 casperjs ' . __DIR__ . '/html.js --cookies-file=cookies.txt --url=' . escapeshellarg ( $redirectURL ) . ' --xpath=' . escapeshellarg ( $xpath );
		$command = 'cd ' . __DIR__ . '; timeout -k 90 -s SIGTERM 90 casperjs html.js --cookies-file=cookies.txt --url=' . escapeshellarg ($redirectURL) . ' --xpath=' . escapeshellarg ( $xpath );
		$this->logger->logDebug ( "Reloading ($redirectURL) to get ($xpath) " . $command );
		exec ( $command, $response, $return_var );
		$response = $this->strip_json ( $response );
		if (! isset ( $response ["loadtime"] )) {
			$response ["loadtime"] = null;
		}
		$this->logger->logDebug ( "Reloaded ($redirectURL) in " . $response ["loadtime"] );
		if ($return_var !== 0) {
			if (isset ( $response ['content'] )) {
				unset ( $response ['content'] );
			}
			$this->logger->logWarn ( "Cannot fetch text from ($redirectURL) exit code $return_var", array (
					'command' => $command,
					'response' => $response 
			) );
			return array (
					'redirectURL' => $redirectURL,
					'contentextra' => null 
			);
		}
		
		if (array_key_exists ( 'error', $response )) {
			$this->logger->logWarn ( "Error in fetching text ($redirectURL) ", $response );
			return array (
					'redirectURL' => $redirectURL,
					'contentextra' => null 
			);
		}
		
		if ((! isset ( $response ['content'] )) || (! is_array ( $response ['content'] )) || (count ( $response ['content'] ) == 0)) {
			$this->logger->logWarn ( "Cannot get xpaths ($xpath) for " . $redirectURL );
			$this->xpaths->xpfailed ( $redirectURL );
			return array (
					'redirectURL' => $redirectURL,
					'contentextra' => null 
			);
		}
		
		$results = $response ['content'];
		$results = $this->get_text ( $results );
		$this->logger->logDebug ( "Fetched ($xpath) from ($redirectURL)" );
		return array (
				'redirectURL' => $redirectURL,
				'contentextra' => $results 
		);
	} // function getUrl
	
	/**
	 * Get the text from an html string
	 *
	 * @param string $text        	
	 * @return string
	 *
	 */
	private function get_text($text) {
		if (! is_array ( $text )) {
			$this->logger->logWarn ( "Invalid text ", array (
					json_decode ( $text ) 
			) );
			return null;
		}
		for($i = 0; $i < count ( $text ); $i ++) {
			// Strip multiply spaces and remove line breaks
			$text [$i] = preg_replace ( '/\s+/m', ' ', $text [$i] );
			$text [$i] = trim ( $text [$i] );
		}
		
		$text = implode ( " ", $text );
		$text = htmlspecialchars_decode ( $text, ENT_QUOTES );
		$text = \Rss\Util\Helpers\Helpers::fix_smartquotes ( $text );
		return $text;
	} // function get_text
	
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
		if (isset ( $jsons [0] )) {
			return $jsons [0];
		}
		return $jsons;
	} // function strip_json
} // class HTMLReader

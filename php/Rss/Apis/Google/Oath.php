<?php

namespace Rss\Apis\Google;

/**
 *
 * @author user
 *        
 */
class Oath {
	
	protected $logger;
	protected $client;
	
	protected $client_id = "907322625228-266942j1ga1hv3k78fkt716dlqktu1it.apps.googleusercontent.com";
	protected $client_secret = "fmJHFoJgjJOtQAjlqHh5chTJ";
	protected $refresh_token = "1/_n1IiJZYkKGXWVIHLKBFHS8bCKtqXLbY_UeIebzyKjs";
	
	/**
	 */
	function __construct() {
		global $logger;
		$this->logger = $logger;
		
		try {
		$this->client = new \Google_Client ();
		$this->client->setAccessType ( 'offline' ); // default: offline
		$this->client->setApplicationName ( 'Blogger API' ); // name of the application
		$this->client->setClientId ( $this->client_id); // insert your client id
		$this->client->setClientSecret ( $this->client_secret ); // insert your client secret
		$this->client->setScopes ( array (
				'https://www.googleapis.com/auth/blogger'
		) ); // since we are going to use blogger services

		$this->client->refreshToken ( stripslashes ($this->refresh_token) );
		
		} catch ( \Exception $e ) {
			$m = "Cannot get an Access Token using this Refresh Token " . $this->refresh_token . " because " . $e->getMessage ();
			$this->logger->logError ( $m );
			die ( $m );
		}			
	} // function __construct
	
	/**
	 * @return \Google_Client
	 */
	public function get_client() {
		return $this->client;
	}
	
} // class Oath

?>
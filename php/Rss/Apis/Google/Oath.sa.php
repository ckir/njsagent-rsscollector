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
	protected $client_id = "907322625228-o6s448pqs2v80ro2f18goareckq5oe3o.apps.googleusercontent.com";
	protected $service_account_name = "907322625228-o6s448pqs2v80ro2f18goareckq5oe3o@developer.gserviceaccount.com";
	protected $key_file_location = "TaskQueueJSONRPCAPI-d6ab3c3923be.p12";
	
	/**
	 */
	function __construct() {
		global $logger;
		$this->logger = $logger;
		
		try {
			$this->client = new \Google_Client ();
			$this->client->setApplicationName ( 'Blogger API' ); // name of the application
			$key = file_get_contents ( __DIR__ . "/" . $this->key_file_location );
			$cred = new \Google_Auth_AssertionCredentials ( $this->service_account_name, array (
					'https://www.googleapis.com/auth/blogger' 
			), $key );
			$this->client->setAssertionCredentials ( $cred );
			if ($this->client->getAuth ()->isAccessTokenExpired ()) {
				$this->client->getAuth ()->refreshTokenWithAssertion ( $cred );
			}
		} catch ( \Exception $e ) {
			$m = "Cannot get an Access Token using this Refresh Token " . $this->refresh_token . " because " . $e->getMessage ();
			$this->logger->logError ( $m );
			die ( $m );
		}
	} // function __construct
	public function get_client() {
		return $this->client;
	}
} // class Oath

?>
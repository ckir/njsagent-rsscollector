<?php

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
		
		$content = html_entity_decode($content, ENT_QUOTES);
		$query = "SELECT * FROM contentanalysis.analyze WHERE text='";
		$characters = array(' ', '=', '"');
		$replacements = array('%20', '%3D', '%22');
		$query = str_replace($characters, $replacements, $query);
		$query = $query . rawurlencode($content) . "'";

		
		$ch = curl_init ();
		
		curl_setopt ( $ch, CURLOPT_URL, $this->api_url );
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS, "q=$query&format=json");
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, true );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, $this->timeout );
		
		for ($failures = 0; $failures < 3; $failures++) {
		    $response = curl_exec ( $ch );
		    $info = curl_getinfo ( $ch );
		    if ((int) $info['http_code'] < 500) {
		        break;
		    }
		    sleep(2);
		}

		curl_close ( $ch );
		
		if ((int) $info['http_code'] >= 400) {
			$this->logger->logWarn("Yahoo error analyzing [$content] response $response", $info);
			return false;
		}		
		
		try {
			$json = json_decode ( $response, true );
			if (! isset ( $json ['query'] ['results'] )) {
				$this->logger->logWarn("Yahoo error analyzing [$content] response $response", $info);
				return false;
			}
		} catch ( \Exception $e ) {
			$this->logger->logWarn("Yahoo error analyzing [$content] " . $e->getMessage());
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

/* 
$text = <<<EOT
&ldquo;&Tau;here is no reference of a &ldquo;haircut of deposits&rdquo; in an agreement signed between the Greek government and the institutions, regardless of the sum,&rdquo; the president of Hellenic Bank Association and National Bank governor Louka Katseli said. In an interview with &ldquo;Economic Review&rdquo; magazine, Katseli said that a recapitalization process &mdash; a responsibility of the &ldquo;&Tau;here is no reference of a &ldquo;haircut of deposits&rdquo; in an agreement signed between the Greek government and the institutions, regardless of the sum,&rdquo; the president of Hellenic Bank Association and National Bank governor Louka Katseli said. In an interview with &ldquo;Economic Review&rdquo; magazine, Katseli said that a recapitalization process &mdash; a responsibility of the European Central Bank &mdash; was progressing rapidly with completion of evaluating the quality of loan portfolios. &ldquo;With the completion of stress tests the capital needs of each bank will be determined. This process will be completed within 2015,&rdquo; the Greek banker said. &ldquo;Our assessment is that banks will not need the sum of 25 billion euros envisaged in the agreement. The exact sum to be needed, will arise based on the extreme forecasts to be made by ECB,&rdquo; Katseli said, adding that &ldquo;systemic banks are competing against each other over which will offer the most attractive loan rescheduling program. This is significant to lighten the burden of borrowers and improving the quality of banks&rsquo; loan portfolio. Of course, further initiatives are needed to deal with the non-performing loans problem. I personally believe that banks can satisfactorily handle the problem with consumer and mortgage loans, but a coordinated collective action was necessary to efficiently manage non performing syndicated business loans.&rdquo; Katseli said that political stability was a necessary condition to restoring confidence and credibility of the banking system and noted that banks should take initiatives towards building confidence, transparency and credibility. _(source: ana-mpa)_ &ldquo;&Tau;here is no reference of a &ldquo;haircut of deposits&rdquo; in an agreement signed between the Greek government and the institutions, regardless of the sum,&rdquo; the president of Hellenic Bank Association and National Bank governor Louka Katseli said. In an interview with &ldquo;Economic Review&rdquo; magazine, Katseli said that a recapitalization process &mdash; a responsibility of the European Central Bank &mdash; was progressing rapidly with completion of evaluating the quality of loan portfolios. &ldquo;With the completion of stress tests the capital needs of each bank will be determined. This process will be completed within 2015,&rdquo; the Greek banker said. &ldquo;Our assessment is that banks will not need the sum of 25 billion euros envisaged in the agreement. The exact sum to be needed, will arise based on the extreme forecasts to be made by ECB,&rdquo; Katseli said, adding that &ldquo;systemic banks are competing against each other over which will offer the most attractive loan rescheduling program. This is significant to lighten the burden of borrowers and improving the quality of banks&rsquo; loan portfolio. Of course, further initiatives are needed to deal with the non-performing loans problem. I personally believe that banks can satisfactorily handle the problem with consumer and mortgage loans, but a coordinated collective action was necessary to efficiently manage non performing syndicated business loans.&rdquo; Katseli said that political stability was a necessary condition to restoring confidence and credibility of the banking system and noted that banks should take initiatives towards building confidence, transparency and credibility. (source: ana-mpa)
EOT;
 */

/**
 * Function to use Yahoo to analyse some simple text
 * @param String $text
 * @param String $format
 * @return String $content
 */
function yahoo_content_analysis($text, $format = 'json')
{
    $url = "http://query.yahooapis.com/v1/public/yql";

    $query = 'SELECT * FROM contentanalysis.analyze WHERE text = "' . $text . '"';

    $characters = array(' ', '=', '"');
    $replacements = array('%20', '%3D', '%22');

    $query = str_replace($characters, $replacements, $query);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "q=$query&format=$format");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    $response = curl_exec($ch);
    $headers = curl_getinfo($ch);
    curl_close($ch);

    return $response;
}






$text = 'For the thousands of refugees and migrants landing on its beaches every day Greece Lesbos island is a step to safety and a brighter future in Europe';
//$text = 'Computer programming (often shortened to programming or coding) is the process of designing, writing, testing, debugging, and maintaining the source code of computer programs.';

$text = substr($text, 0, 120);

$query = "select * from contentanalysis.analyze where text = '".$text."'";
$url = 'http://query.yahooapis.com/v1/public/yql';
$yql_query_url = $url . "?q=" . urlencode($query);
$yql_query_url .= "&format=json";
$yql_query_url .= "&enable_categorizer=true";
$yql_query_url .= "&diagnostics=false";
$yql_query_url .= "&related_entities=true";
$yql_query_url .= "&show_metadata=true";
$ch = curl_init($yql_query_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
$result = curl_exec($ch);
curl_close($ch);

$response = yahoo_content_analysis($text);

$logger = null;
$yahoo = new Yahoo();
$tags = $yahoo->tag($text);

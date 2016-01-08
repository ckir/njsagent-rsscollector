<?php

namespace Rss\Apis\OpenCalais;

/**
 *
 * @author user
 *        
 */
class OpenCalais {
	
	protected $logger;
	
	/**
	 */
	function __construct() {
		global $logger;
		$this->logger = $logger;		
	}
	
	/**
	 * @param unknown $text
	 * @return boolean|multitype:NULL string Ambigous <\Rss\Apis\OpenCalais\multitype:unknown, multitype:unknown > 
	 */
	public function tag($text) {
		$url = "https://api.thomsonreuters.com/permid/calais?access-token=hbSGpstLBtiVTOlu0CvNucCxkIMdbdei";
		$headers = array ();
		$headers [] = 'Content-Type: text/raw';
		$headers [] = 'OutputFormat: application/json';
		$headers [] = 'X-AG-Access-Token: hbSGpstLBtiVTOlu0CvNucCxkIMdbdei';
		$headers [] = 'X-Calais-Language: English';
		
		$vars = array (
				'dataType' => "text",
				'inputData' => $text 
		);
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $vars );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		$response = curl_exec ( $ch );
		$info = curl_getinfo ( $ch );
		curl_close ( $ch );
		
		if ((int) $info['http_code'] >= 400) {
			$this->logger->logWarn("Calais error analyzing $text response $response", $info);
			return false;
		}
		
		try {
			$json = json_decode ( $response, true );
			if (! isset ( $json['doc'] )) {
				$this->logger->logWarn("Calais error analyzing $text response $response", $info);
				return false;
			}			
		} catch ( \Exception $e ) {
			$this->logger->logWarn("Calais error analyzing $text " . $e->getMessage());
			return false;
		}
		unset ($json['doc']['info']['document']);
		return array (
				'info' => $this->minify($info),
				'response' => $this->minify($json),
				'tags' => $this->get_tags ( $json ) 
		);
	} // function tag
	
	/**
	 *
	 * @param unknown $json        	
	 * @return multitype:unknown
	 */
	protected function get_tags($json) {
		$tags = array ();
		foreach ( $json as $topic ) {
			if (! isset ( $topic ['_typeGroup'] )) {
				continue;
			}
			if (! isset ( $topic ['name'] )) {
				continue;
			}
			switch ($topic ['_typeGroup']) {
				case 'topics' :
					$tags [] = $topic ['name'];
					break;
				case 'socialTag' :
					if (($topic ['forenduserdisplay'] == "true") && (strcasecmp ( $topic ['name'], "Greece" ) !== 0)) {
						if ($topic ['importance'] == "1") {
							$tags [] = $topic ['name'];
						}
					}
					break;
				default :
					;
					break;
			}
		}
		return $tags;
	} // function get_tags
	
	protected function minify($obj) {
		$ser = json_encode($obj, JSON_UNESCAPED_UNICODE);
		$comp = gzcompress($ser, 9);
		return base64_encode($comp);
	}
} // class OpenCalais
/* 
$str = <<<EOD
Every week, we bring you one overlooked aspect of the stories that made news in recent days. You noticed the media forgot all about another story's basic facts? Tweet @TheWorldPost or let us know on our Facebook page. In 2010 and 2012, Greece accepted bailout deals from European creditors totaling hundreds of billions of euros in order to prevent the collapse of the Greek banking system. The funds kept Greece from a potential default that would force it out of the eurozone, but most of the enormous sum of money involved in the bailouts ultimately didn't end up funding public services or directly going to the Greek people. Instead, as The New York Times reported in 2012, much of the bailout funds went back to the same creditors who gave Greece both the bailouts. This resulted in a situation where the so-called troika of the IMF, European Central Bank and European Commission were effectively lending Greece money so it could pay off the debt it already owed them. As a senior adviser to Germany's Deutsche Bank told the Times then, the troika "is paying themselves." At the time, the bailouts stopped a potential "Grexit," or Greek withdrawal from the eurozone, and quelled fears that Greece's collapse could have a disastrous domino effect throughout the eurozone. But some economists say it did very little to actually put the nation on a path to growth. “The rescue that took place in the banking sector was really more of a rescue of northern European financial institutions that had overexposed themselves to Greece,” says Vicky Pryce, chief economic adviser at the analyst firm Centre for Economics and Business Research and author of a book on the Greek economy. The first bailout largely went to paying off the private creditors that held Greek bonds and were owed debt payments, notes The Washington Post. This removed some of the danger that if Greece defaulted on its loans, it would lead these French and German banks to bankruptcy and in turn have a negative impact on Europe's financial system. However, as Pryce explains, the bailout may have set up the Greek economy for a fall. “The concern about this particular debt is that all it did is it transferred this large burden to the Greeks," Pryce says. "It was so large and unsustainable, and the only way you can make it sustainable is if you grow very substantially.” In exchange for lending the funds, the creditors required Greece to institute strict austerity measures that many economists blame for holding back the country's economic recovery. It also set up a stringent schedule of debt payments for Greece to meet with its creditors, but the country didn't experience the growth predicted by the IMF that would have potentially given it the funds to pay these debts. This year's debt crisis negotiations have focused around a June 30 deadline for Greece to pay the IMF 1.6 billion euros, with additional payments owed to the ECB in July and August. As Pryce explains, large amounts of the 7.2 billion euro tranche of bailout funds potentially released in these negotiations would quickly recirculate back to the creditors who lent it. “It will go straight into an escrow account, an account that’s held in the European Central Bank through the Bank of Greece, and then out again, straight back.” -- This feed and its contents are the property of The Huffington Post, and use is subject to our terms. It may be used for personal consumption, but may not be distributed on a website.
EOD;
require '../Yahoo/Yahoo.php';
$y = new \Rss\Apis\Yahoo\Yahoo();
var_dump ( $y->tag( $str ) );
//$o = new OpenCalais ();
//var_dump ( $o->tag ( $str ) );
 */
?>
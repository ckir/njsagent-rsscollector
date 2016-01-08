<?php
/**
 *
 * @author user
 *
 */
class FeedReadFilter {
	protected $logger;
	protected $feedcontent;
	protected $feedcache;
	protected $pagereader;
	protected $calais;
	protected $yahoo;
	
	/**
	 */
	function __construct($postgresql) {
		global $logger;
		$this->logger = $logger;
		$this->feedcache = new Rss\Util\Cache\FeedCache\FeedCache ( $postgresql );
		$this->pagereader = new \Rss\Html\HTMLReader\HTMLReader ( $postgresql );
		$this->calais = new \Rss\Apis\OpenCalais\OpenCalais();
		$this->yahoo = new \Rss\Apis\Yahoo\Yahoo();
	}
	
	/**
	 *
	 * @return array
	 */
	public function feed_filter($feedcontent) {

		$i = 0;
		if ((! isset ( $feedcontent ['data'] )) || (! is_array ( $feedcontent ['data'] ))) {
			return false;
		}
		foreach ( $feedcontent ['data'] as $article ) {
			
			if (isset ( $article ['dateCreated'] ) && (! empty ( $article ['dateCreated'] ))) {
				$article ['date'] = $article ['dateCreated'];
			}
			if (isset ( $article ['dateModified'] ) && (! empty ( $article ['dateModified'] ))) {
				$article ['date'] = $article ['dateModified'];
			}
			
			if (empty ( $article ['description'] )) {
				$article ['description'] = $article ['content'];
			}
			
			if (empty ( $article ['date'] )) {
				continue;
			}
			if (empty ( $article ['title'] )) {
				continue;
			}
			if (empty ( $article ['description'] )) {
				continue;
			}
			if ((! is_array ( $article ['titlestemmed'] )) || (count ( $article ['titlestemmed'] ) < 1)) {
				continue;
			}
			if ((! is_array ( $article ['titlemetaphone'] )) || (count ( $article ['titlemetaphone'] ) < 1)) {
				continue;
			}
			if (empty ( $article ['link'] )) {
				continue;
			}
			
			$article ['titlestemmed'] = implode ( " ", $article ['titlestemmed'] );
			$article ['titlestemmed'] = preg_replace ( '/\s{2,}/', ' ', $article ['titlestemmed'] );
			$article ['titlemetaphone'] = implode ( " ", $article ['titlemetaphone'] );
			$article ['titlemetaphone'] = trim ( preg_replace ( '/\s{2,}/', ' ', $article ['titlemetaphone'] ) );
			$article = $this->process_article ( $article );
			if ($article) {
				$t = microtime(true);
				$micro = sprintf("%06d",($t - floor($t)) * 1000000);
				$d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
				$d = $d->format("Y-m-d_H_i_s_u_");
				$d = $d . parse_url($article['link'], PHP_URL_HOST);
				file_put_contents(__DIR__ . '/articles/' . $d . '.txt', json_encode($article, JSON_PRETTY_PRINT));
				$i ++;
			}			
		}
		$this->logger->logInfo ( "Processed " . count ( $feedcontent ['data'] ) . " articles from (" . $feedcontent ['feed'] . ") Passed " . $i );

	} // function process_articles
	
	/**
	 *
	 * @param array $article        	
	 * @return NULL array
	 */
	private function process_article($article) {
		$newarticle = $article;
		
		$date = ( int ) $newarticle ['date'];
		$yesterday = strtotime ( '-1 days' );
		if (! $date) {
			$this->logger->logDebug ( "Bad date " . $article ['title'] );
			return null;
		}
		if ($date < $yesterday) {
			$this->logger->logTrace ( "Failed (Too old) " . $article ['title'] );
			return null;
		}
		
		// Not Greece or Greek
		$results = 0;
		$title = $newarticle ['title'];
		$description = $newarticle ['description'];
		$results = $results + preg_match ( "/greece/i", $title );
		$results = $results + preg_match ( "/greek/i", $title );
		$results = $results + preg_match ( "/greece/i", $description );
		$results = $results + preg_match ( "/greek/i", $description );
		if ($results == 0) {
			$this->logger->logTrace ( "Failed (filter terms) " . $newarticle ['title'] );
			return null;
		}
		
		// Video
		$results = 0;
		$link = $newarticle ['link'];
		$results = $results + preg_match ( "/video/i", $link );
		$results = $results + preg_match ( "/video/i", $title );
		if ($results != 0) {
			$this->logger->logTrace ( "Failed (video) " . $newarticle ['title'] );
			return null;
		}
		if (preg_match ( "/youtube/", $link )) {
			$this->logger->logTrace ( "Failed (youtube video) " . $newarticle ['title'] );
			return null;
		}
		
		$published = $this->feedcache->cache_search ( $article );
		
		if (! $published) {
			$this->logger->logDebug ( "Found unpublished article: " . $article ['title'] . " (" . $newarticle ['link'] . "). Fetching page" );
			$response = $this->pagereader->getUrl ( $newarticle ['link'], false, true );
			if ((! isset ( $response ['redirectURL'] )) || (empty ( $response ['redirectURL'] ))) {
				// Article link not responding
				return null;
			}
			$newarticle ['link'] = $response ['redirectURL'];
			$newarticle ['contentextra'] = null;
			if ((isset ( $response ['contentextra'] )) && (! empty ( $response ['contentextra'] ))) {
				$newarticle ['contentextra'] = $response ['contentextra'];
			}
			$tagtext = "";
			if ($newarticle ['description'] !== $newarticle ['content']) {
				$tagtext = $newarticle ['description'] . " " . $newarticle ['content'];
			} else {
				$tagtext = $newarticle ['description'];
			}
			if (!empty($newarticle ['contentextra'])) {
				$tagtext = $tagtext . " " . $newarticle ['contentextra'];
			}
			$tagtext = htmlentities($tagtext, ENT_QUOTES | ENT_IGNORE);
			$this->logger->logDebug("Analyzing by Calais");
			$newarticle['calais'] = $this->calais->tag($tagtext);
			$this->logger->logDebug("Analyzing by Yahoo");
			$newarticle['yahoo'] = $this->yahoo->tag($tagtext);
			return $newarticle;
		}
		
		$this->logger->logTrace ( "Failed (Already in cache) " . $article ['title'] );
		return null;
	} // function process_article
	
	/**
	 * @param unknown $text
	 * @return Ambigous <string, mixed>
	 */
	private function get_text($text) {
		// Strip links first
		$text = preg_replace ( "/<\\/?a(\\s+.*?>|>)/", "", $text );
		$html2Text = new \Html2Text\Html2Text ( $text );
		$text = $html2Text->get_text ();
		// Strip multiply spaces and remove line breaks
		$text = preg_replace ( '/\s+/m', ' ', $text );
		$text = \Rss\Util\Helpers\Helpers::fix_smartquotes ( $text );
		return $text;
	}
} // class FeedReadFilter
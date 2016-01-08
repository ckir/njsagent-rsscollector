<?php

namespace Rss\Feed\Reader;

/**
 *
 * @author user
 *        
 */
class Parse {
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
	 * Parses a feed and returns an array of elements.
	 *
	 * @param string $uri
	 *        	The URI to the feed
	 * @param bool $extinfo
	 *        	Add extended info for title
	 *        	
	 * @return array
	 */
	public function feedParse($uri, $extinfo = true) {
		$reader = new \Rss\Feed\Reader\Read ( $this->postgresql );
		$feed = $reader->feedRead ( $uri, "json" );
		if (! $feed) {
			return false;
		}
		$data = $feed ['data'];
		
		$articles = array ();
		$items = $data ['entries'];
		
		foreach ( $items as $item ) {
			$article = $this->process_item ( $item, $extinfo );
			if ($article) {
				$articles [] = $article;
			}
		}
		
		$feed ['data'] = $articles;
		
		return $feed;
	} // function getFeed
	
	/**
	 *
	 * @param array $item        	
	 * @param bool $extinfo        	
	 */
	private function process_item($item, $extinfo) {
		$item ['title'] = $this->get_title ( $item ['title'] );
		$title = $item ['title'];
		
		$item ['description'] = $this->get_text ( $item ['description'] );
		$description = $item ['description'];
		
		$item ['content'] = $this->get_text ( $item ['content'] );
		$content = $item ['content'];
		
		$item ['link'] = $this->get_link ( $item ['link'] );
		$link = $item ['link'];
		if (empty ( $link )) {
			$this->logger->logWarn ( "Empty item url ($link)" );
			return null;
		}
		$zuri = \Zend\Uri\UriFactory::factory ( $link );
		if (! $zuri->isValid ()) {
			$this->logger->logWarn ( "Invalid item url ($link)" );
			return null;
		}
		$linkparts = parse_url ( $link );
		if ((! isset ( $linkparts ['host'] )) || (empty ( $linkparts ['host'] ))) {
			$this->logger->logWarn ( "Invalid item host ($link)" );
			return null;
		}
		
		if ($extinfo) {
			// Ext Title
			$titlenocommon = \Rss\Text\CommonWords\CommonWords::removeCommonWords ( $title );
			$stemmer = new \Rss\Text\Stemmer\PorterStemmer ();
			$titlestemmed = $stemmer->getStemmed ( $titlenocommon );
			// Strip multiply spaces and remove line breaks
			$titlestemmed = preg_replace ( '/\s+/m', ' ', $titlestemmed );
			$misc = new \Rss\Text\Misc\Misc ();
			$titlemetaphone = $misc->getMetaphone ( $titlestemmed );
			$item ['titlenocommon'] = $titlenocommon;
			$item ['titlestemmed'] = $titlestemmed;
			$item ['titlemetaphone'] = $titlemetaphone;
		}
		ksort ( $item );
		
		return $item;
	} // function process_item
	
	
	/**
	 * @param unknown $title
	 * @return mixed
	 */
	private function get_title($title) {
		$title = $this->get_text ( $title );
		$title = preg_replace ( '/ - .*$/s', '', $title );
		$title = preg_replace ( '/ \| .*$/s', '', $title );
		$title = str_replace ( '—', '-', $title );
		return $title;
	}
	
	/**
	 * Get the text from an html string
	 *
	 * @param string $text        	
	 * @return string
	 *
	 */
	private function get_text($text) {
		// Strip links first because Html2Text puts them in the output
		$text = preg_replace ( "/<\\/?a(\\s+.*?>|>)/", "", $text );
		$html2Text = new \Html2Text\Html2Text ( $text );
		$text = $html2Text->get_text ();
		$text = html_entity_decode ( $text, ENT_QUOTES );
		// Strip multiply spaces and remove line breaks
		$text = preg_replace ( '/\s+/m', ' ', $text );
		$text = \Rss\Util\Helpers\Helpers::fix_smartquotes ( $text );
		$text = trim ( $text );
		return $text;
	} // function get_text
	
	/**
	 *
	 * @param string $link        	
	 * @return string
	 */
	private function get_link($link) {
		// Google & Bing news are collections from multiply sources.
		// We keep the description but set the link to final url.
		$linkparts = parse_url ( $link );
		
		// Yahoo adds some tracking at the end. Lets remove it
		if (preg_match ( '/yahoo/', $linkparts ['host'] )) {
			$link = explode ( ';', $link );
			$link = $link [0];
			if (isset ( $linkparts ['path'] )) {
				$path = preg_match ( "/RU=.*\/RK/", $linkparts ['path'], $matches );
				if (isset ( $matches [0] )) {
					$matches = $matches [0];
					$matches = substr ( $matches, 3 );
					$matches = substr ( $matches, 0, - 3 );
					$validator = new \Zend\Validator\Uri ();
					if ($validator->isValid ( $matches )) {
						$link = $matches;
					}
				}
			}
		}
		
		// Google news are collections from multiply sources.
		// We keep the description but set the link to final url.
		if (preg_match ( '/google/', $linkparts ['host'] )) {
			if (isset ( $linkparts ['query'] )) {
				parse_str ( $linkparts ['query'], $query );
				$link = null;
				if (isset ( $query ['q'] )) {
					$link = $query ['q'];
				}
				if (isset ( $query ['url'] )) {
					$link = $query ['url'];
				}
			}
		}
		
		// Bing news are collections from multiply sources.
		// We keep the description but set the link to final url.
		if (preg_match ( '/bing/', $linkparts ['host'] )) {
			if (isset ( $linkparts ['query'] )) {
				parse_str ( $linkparts ['query'], $query );
				if (isset ( $query ['url'] )) {
					$link = $query ['url'];
				} else {
					$link = null;
				}
			}
		}
		
		$validator = new \Zend\Validator\Uri ();
		if (! $validator->isValid ( $link )) {
			return null;
		}
		
		return $link;
	} // function get_link
	
	/**
	 *
	 * @param array $item        	
	 * @return number NULL
	 */
	private function get_date($item) {
		if (isset ( $item ['pubDate'] )) {
			return strtotime ( $item ['pubDate'] );
		}
		
		if (isset ( $item ['updated'] )) {
			return strtotime ( $item ['updated'] );
		}
		
		return time();
	} // function get_date
} // class Parse

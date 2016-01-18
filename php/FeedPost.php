<?php

class BloggerPost
{

    protected $logger;

    protected $client;

    protected $service;

    protected $blogid = "6513984178921553264";

    function __construct($client)
    {
        global $logger;
        $this->logger = $logger;
        $this->client = $client;
        $this->service = new \Google_Service_Blogger($client);
    }
 // function __construct
    public function handle($post)
    {
        $link = $this->getLink($post['link']);
        $title = $this->getTitle($post['title']);
        $content = $this->getContent($post['description'], $post['content'], $link);
        $labels = $this->getLabels($post['yahoo']);
        if ((! empty($link)) && (! empty($title)) && (! empty($content)) && (count($labels) > 0)) {
            $posted = $this->post($title, $content, $labels, $link);
            return $posted;
        } else {
            return null;
        }
    }
 // function handle
    
    /**
     *
     * @param string $link            
     * @return string
     */
    private function getLink($link)
    {
        // Google & Bing news are collections from multiply sources.
        // We keep the description but set the link to final url.
        $linkparts = parse_url($link);
        
        if (! isset($linkparts['host'])) {
            $this->logger->logWarn("Invalid host in link ($link)");
            return null;
        }
        
        // Yahoo adds some tracking at the end. Lets remove it
        if (preg_match('/yahoo/', $linkparts['host'])) {
            $link = explode(';', $link);
            $link = $link[0];
            if (isset($linkparts['path'])) {
                $path = preg_match("/RU=.*\/RK/", $linkparts['path'], $matches);
                if (isset($matches[0])) {
                    $matches = $matches[0];
                    $matches = substr($matches, 3);
                    $matches = substr($matches, 0, - 3);
                    $validator = new \Zend\Validator\Uri();
                    if ($validator->isValid($matches)) {
                        $link = $matches;
                    }
                }
            }
        }
        
        // Google news are collections from multiply sources.
        // We keep the description but set the link to final url.
        if (preg_match('/google/', $linkparts['host'])) {
            if (isset($linkparts['query'])) {
                parse_str($linkparts['query'], $query);
                $link = null;
                if (isset($query['q'])) {
                    $link = $query['q'];
                }
                if (isset($query['url'])) {
                    $link = $query['url'];
                }
            }
        }
        
        // Bing news are collections from multiply sources.
        // We keep the description but set the link to final url.
        if (preg_match('/bing/', $linkparts['host'])) {
            if (isset($linkparts['query'])) {
                parse_str($linkparts['query'], $query);
                if (isset($query['url'])) {
                    $link = $query['url'];
                } else {
                    $link = null;
                }
            }
        }
        
        $validator = new \Zend\Validator\Uri();
        if (! $validator->isValid($link)) {
            return null;
        }
        
        return $link;
    }
 // function getLink
    
    /**
     *
     * @param string $title            
     * @return string
     */
    private function getTitle($title)
    {
        $newtitle = html_entity_decode($title, ENT_QUOTES);
        $newtitle = htmlspecialchars_decode($newtitle, ENT_QUOTES);
        return $newtitle;
    }
 // function getTitle
    
    /**
     *
     * @param string $description            
     * @param string $content            
     * @param string $link            
     * @return string
     */
    private function getContent($description, $content, $link)
    {
        if (! empty($description)) {
            $newcontent = $description;
        } else {
            $newcontent = $content;
        }
        
        $newcontent = html_entity_decode($newcontent, ENT_QUOTES);
        $newcontent = htmlspecialchars_decode($newcontent, ENT_QUOTES);
        if (empty($newcontent)) {
            return null;
        }
        $newcontent = "<p>" . $newcontent . "</br></p>";
        $newcontent = $newcontent . $this->articletail($link);
        return $newcontent;
    }
 // function getContent
    
    /**
     *
     * @param array $yahoo            
     * @return string
     */
    private function getLabels($yahoo)
    {
        $labels = array();
        if (isset($yahoo["response"]["query"]["results"]['yctCategories']['yctCategory']) && is_array($yahoo["response"]["query"]["results"]['yctCategories']['yctCategory'])) {
            foreach ($yahoo["response"]["query"]["results"]['yctCategories']['yctCategory'] as $category) {
                if (isset($category['content'])) {
                    $labels[] = preg_replace("/&/", "and", $category['content']);
                }
            }
            $labels = array_filter($labels);
        }
        
        if (count($labels) === 0) {
            $labels[] = "Uncategorized";
        }
        return $labels;
    }
 // function getLabels
    
    /**
     * Add a clickable icon link to the original article
     *
     * @param string $link            
     * @return string
     */
    private function articletail($link)
    {
        $tail = '<div style="text-align: center;"><br /><table border="0" style="margin-left: auto; margin-right: auto; text-align: left;"><tbody><tr><td align="center"><a href="LINK" target="_blank"><img height="64" src="http://tests-for-my-class.googlecode.com/git/Rss/GoRead64x64.png" width="64" /></a></td></tr><tr><td align="center"><b>READ THE ORIGINAL POST AT NEWSSITE</b></td></tr></tbody></table></div>';
        $l = parse_url($link);
        $tail = preg_replace('/LINK/', $link, $tail);
        $tail = preg_replace('/NEWSSITE/', $l['host'], $tail);
        return $tail;
    }
 // function articletail
    private function post($title, $content, $labels, $link)
    {
        try {
            $mypost = new \Google_Service_Blogger_Post();
            $mypost->setTitle($title);
            $mypost->setContent($content);
            $mypost->setLabels($labels);
            
            for ($i = 0; $i < 2; $i ++) {
                try {
                    $article = $this->service->posts->insert($this->blogid, $mypost);
                    break;
                } catch (\Exception $e) {
                    if ($e->getCode() == 500) {
                        sleep(1);
                        continue;
                    }
                    throw $e;
                }
            }
            
            $postdata = array();
            if (is_object($article)) {
                $postdata['id'] = $article->getId();
                $postdata['title'] = $article->getTitle();
                $postdata['selfLink'] = $link;
                $postdata['url'] = $article->getUrl();
                $postdata['labels'] = $article->getLabels();
                $postdata['published'] = $article->getPublished();
            }
            
            return $postdata;
        } catch (\Exception $e) {
            $m = 'Cannot post to blogger because ' . $e->getCode() . ' ' . $e->getMessage();
            $this->logger->logError($m);
        }
    } // function post
} // class BloggerPost

require_once 'RssArchive.php';
require_once 'RssPostArchive.php';
$archive = new RssArchive($postgresql);
$postarchive = new PostArchive($postgresql);

$oath = new \Rss\Apis\Google\Oath();
$client = $oath->get_client();
$BloggerPost = new BloggerPost($client);

$articles = glob(__DIR__ . "/articles/*.txt");
foreach ($articles as $filename) {
    $post = @file_get_contents($filename);
    if (! $post) {
        continue;
    }
    if (@unlink($filename)) {
        $logger->logDebug("Deleted ($filename)");
    } else {
        $logger->logError("Cannot delete ($filename)");
    }
    $post = json_decode($post, true);
    if (! $post) {
        $logger->logWarn("Error decoding article ($filename)");
        continue;
    }
    
    $posted = $BloggerPost->handle($post);
    if (is_array($posted) && (count($posted) > 0)) {
        $logger->logDebug("Deleted ($filename)");
        if ($archive->handle($post)) {
            $h = parse_url($post["link"], PHP_URL_HOST);
            $t = $post["title"];
            $logger->logDebug("Archived article from ($h) [$t]");
        }
        if ($postarchive->insert($posted)) {
            $id = $posted["id"];
            $logger->logDebug("Archived post id ($id) from ($h) [$t]");
        }
    } else {
        $logger->logWarn("Error posting article ($filename)", $post);
    }
}

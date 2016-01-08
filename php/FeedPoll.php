<?php
require_once ("Startup.php");

$logger->logInfo ( __FILE__ . " started at " . date(DATE_ATOM));
$logger->logInfo ("RSSCOLLECTOR_PGSQL=" . getenv("RSSCOLLECTOR_PGSQL"));
$logger->logInfo ("RSSCOLLECTOR_MYSQL=" . getenv("RSSCOLLECTOR_MySQL"));
sleep(5);
die();

/**
 *
 * @author user
 *
 */
class FeedsPoll {
    protected $logger;
    protected $db;
    protected $feedsselect = 'SELECT feedurl from feeds WHERE (feedenabled = 1) AND (feedgroup = 1) ORDER BY (CASE WHEN feedlastchecked IS NULL THEN 1 ELSE 0 END) DESC, feedlastchecked ASC LIMIT 10';
    protected $feedsupdate = 'UPDATE feeds SET feedlastchecked=? WHERE feedurl=?';

    /**
     */
    function __construct($postgresql) {
        global $logger;
        $this->logger = $logger;
        $this->db = \Rss\Util\Db\PostgreSQL::getPDO ( $postgresql );
        $this->feedsselect = $this->db->prepare ( $this->feedsselect );
        $this->feedsupdate = $this->db->prepare ( $this->feedsupdate );
    }
    private function fail($message) {
        $this->logger->logError ( $message );
        exit ( 1 );
    }

    /**
     *
     * @return array
     */
    public function poll() {
        try {
            $feeds = array ();
            $this->feedsselect->execute ();
            $results = $this->feedsselect->fetchAll ( \PDO::FETCH_ASSOC );
        } catch ( Exception $e ) {
            $m = "Can't select feeds because " . $e->getCode () . ' ' . $e->getMessage ();
            $this->fail ( $m );
        }

        foreach ( $results as $feed ) {
            $feedurl = $feed ['feedurl'];
            try {
                $now = date ( "Y-m-d H:i:s", time () );
                $this->feedsupdate->bindParam ( 1, $now );
                $this->feedsupdate->bindParam ( 2, $feedurl, PDO::PARAM_STR );
                $this->feedsupdate->execute ();
                $feeds [] = $feedurl;
            } catch ( Exception $e ) {
                $m = "Can't update checked date for ($feedurl) because " . $e->getCode () . ' ' . $e->getMessage ();
                $this->logger->logError ( $m );
            }
        }

        // Add standard feeds
        $feeds [] = "http://www.google.com/alerts/feeds/07177147464394752526/1472770238304804822";
        $feeds [] = "http://www.google.com/alerts/feeds/07177147464394752526/1063701502445133856";
        $feeds [] = "https://uk.news.yahoo.com/rss/europe";
        $feeds [] = "http://www.bing.com/news/search?q=greece&qs=n&form=NWBQBN&pq=greece&sc=8-6&sp=-1&sk=&format=RSS";
        $feeds [] = "http://www.bing.com/news/search?q=greek&qs=n&form=NWBQBN&pq=greece&sc=8-6&sp=-1&sk=&format=RSS";

        // Eliminate duplicates
        $feeds = array_unique ( $feeds );

        return $feeds;
    } // function fetch_feeds
} // class RssCron

$postgresql = "pgsql://user:a@localhost:5432/rsszf210poll";
$mysql = "mysql://root:1508Ckir_@127.0.0.1:3306/kirgoussios";

// $postgresql = getenv("RSSCOLLECTOR_PGSQL");
// if (empty($postgresql)) {
//     $logger->logError("Missing environment variable RSSCOLLECTOR_PGSQL");
//     exit (1);
// }
// $mysql = getenv("RSSCOLLECTOR_MYSQL");
// if (empty($mysql)) {
//     $logger->logError("Missing environment variable RSSCOLLECTOR_MYSQL");
//     exit (1);
// }

$logger->logDebug ( "Using Postgress at $postgresql and MySql at $mysql" );

$reader = new FeedsPoll($postgresql);
$feeds = $reader->poll();

require_once 'FeedReadFilter.php';
$reader = new \Rss\Feed\Reader\Parse ( $postgresql );
$feedfilter = new FeedReadFilter ( $postgresql );

foreach ($feeds as $feedurl) {
    //$feedurl = "http://world.einnews.com/rss/y4bqah8fsEQ2NjIG";
    $logger->logInfo ( "Starting ($feedurl)" );
    $content = $reader->feedParse ( $feedurl );
    if (! $content) {
        $logger->logError ( "Feed unreadable ($feedurl)" );
        continue;
    }
    $filtered = $feedfilter->feed_filter ( $content );
}

require_once 'FeedPost.php';
exit ( 0 );

?>
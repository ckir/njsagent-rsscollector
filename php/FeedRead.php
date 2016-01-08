<?php
require_once ("Startup.php");

$shortopts = "";
$postgresql = getopt ( $shortopts, array (
"pg:"
) );
if (count ( $postgresql ) == 0) {
$logger->logError ( "Missing Postgres connection url" );
exit ( 1 );
}
$postgresql = $postgresql ['pg'];
$feedurl = getopt ( $shortopts, array (
"url:"
) );
if (count ( $feedurl ) == 0) {
$logger->logError ( "Missing feed url" );
exit ( 1 );
}
$feedurl = $feedurl ['url'];

$postgresql = "pgsql://user:a@localhost:5432/rsszf210poll";
// $feedurl = "http://www.google.com/alerts/feeds/07177147464394752526/1472770238304804822";
// $feedurl = "http://www.google.com/alerts/feeds/07177147464394752526/1063701502445133856";
// $feedurl = "https://uk.news.yahoo.com/rss/europe";
// $feedurl = "http://www.bing.com/news/search?q=greece&qs=n&form=NWBQBN&pq=greece&sc=8-6&sp=-1&sk=&format=RSS";
// $feedurl = "http://www.bing.com/news/search?q=greek&qs=n&form=NWBQBN&pq=greece&sc=8-6&sp=-1&sk=&format=RSS";
//$feedurl = "http://www.ecns.cn/rss/rss.xml";
//$feedurl = 'http://backup.globaltimes.cn/DesktopModules/DnnForge%20-%20NewsArticles/Rss.aspx?TabID=99&ModuleID=405&CategoryID=51&MaxCount=100&sortBy=StartDate&sortDirection=DESC';
// $feedurl = 'http://www.mfa.gr/en/rss/rss20.xml';
// $feedurl = 'http://www.chinadaily.com.cn/rss/world_rss.xml';

// $feedurl = 'http://feeds.feedburner.com/TheBalticTimesNews?format=xml';
// $feedurl = 'http://theconversation.com/uk/politics/articles.atom';
// $feedurl = 'http://www.economist.com/rss/the_world_this_week_rss.xml';
// $feedurl = 'http://praguemonitor.com/rss/1+11+12+13+14+19+143/feed';
// $feedurl = 'http://www.sfgate.com/rss/feeds/news_world.xml';
// $feedurl = 'http://en.gmw.cn/rss_en.xml';

$reader = new \Rss\Feed\Reader\Parse ( $postgresql );
$logger->logInfo("Starting: $feedurl");
$content = $reader->feedParse ( $feedurl );

if (! $content) {
	$logger->logError ( "Feed unreadable ($feedurl)" );
	exit ( 1 );
}
unset($reader);

$logger->logInfo ( "Fetched ($feedurl)" );
require_once 'FeedReadFilter.php';
$feedfilter = new FeedReadFilter ( $postgresql, $feedurl );
$filtered = $feedfilter->feed_filter ( $content );
unset ($content);
unset ($feedfilter);
$filtered = json_encode ( $filtered, JSON_UNESCAPED_UNICODE );

file_put_contents("articles.txt", $filtered);
echo $filtered . PHP_EOL;
// //unset($filtered);
// $tmp = file_get_contents("articles.txt");
// $tmp1 = json_decode($tmp, true);
// $tmp1 = json_encode ( $tmp1, JSON_UNESCAPED_UNICODE );

// $countrecovered = fwrite ( STDOUT, $tmp1 );
// fwrite ( STDOUT, PHP_EOL );
// $countoriginal = fwrite ( STDOUT, $filtered );
// fwrite ( STDOUT, PHP_EOL );
// echo PHP_EOL;
// echo "Original size: " . strlen($filtered) . PHP_EOL;
// echo "Recovered original size: " . strlen($tmp) . PHP_EOL;
// echo "Recovered recoded size: " . strlen($tmp1) . PHP_EOL;
// echo "Original output size: " . $countoriginal . PHP_EOL;
// echo "Recovered output size: " . $countrecovered . PHP_EOL;
// if ($filtered == $tmp1) {
// 	echo "Equals". PHP_EOL;
// }
// require_once 'class.Diff.php';
// $diff = Diff::compare($filtered, $tmp1, true);
// echo Diff::toString($diff);
// echo PHP_EOL;
//fwrite ( STDOUT, json_encode ( $filtered, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL );
exit ( 0 );

?>

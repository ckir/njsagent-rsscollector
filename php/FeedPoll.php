<?php
require_once ("Startup.php");

$logger->logInfo ( __FILE__ . " started at " . date(DATE_ATOM));
$logger->logInfo ("RSSCOLLECTOR_PGSQL=" . getenv("RSSCOLLECTOR_PGSQL"));
$logger->logInfo ("RSSCOLLECTOR_MYSQL=" . getenv("RSSCOLLECTOR_MySQL"));
sleep(5);
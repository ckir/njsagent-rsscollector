<?php
require_once ("Startup.php");

$logger->logInfo ( __FILE__ . " started at " . date(DATE_ATOM));
$logger->logInfo ("RSSCOLLECTOR_PGSQL=" . getenv("RSSCOLLECTOR_PGSQL"));
sleep(5);
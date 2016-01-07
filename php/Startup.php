<?php
stream_set_blocking(STDIN, 0);
error_reporting ( E_ALL );
date_default_timezone_set ( "UTC" );
session_start();

$loader = require_once __DIR__ . '/vendor/autoload.php';
$loader->add ( 'Rss', realpath ( __DIR__ ) );

use phpMultiLog\phpMultiLog;

$basename = basename(realpath ( __DIR__  . "/.."));
$logsfolder = realpath ( getenv("NJSAGENT_APPROOT") . "/logs/") . "/";

$logger = new phpMultiLog ( $basename );

// Errors and unhandled exceptions will go to these transports
// $logger->errTransportAdd ( "errStderrJson", $logger::DEBUG );
$logger->errTransportAdd ( "errFile", array (
		"filename" => $logsfolder . $basename . "Err.log" 
) );

// System messages will go to these transports
//$logger->logTransportAdd ( "sysStderrJson", $logger::DEBUG );
$logger->logTransportAdd ( "sysFile", $logger::DEBUG , array (
		"filename" => $logsfolder . $basename . "Sys.log"
) );

?>
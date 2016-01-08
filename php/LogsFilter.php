<?php
$handle = fopen("/var/www/heroku/agent0010/logs/RssworkerSys.log", "r");
if ($handle) {
	while (($line = fgets($handle)) !== false) {
		// process the line read.
		preg_match("/level: [0-9]*/", $line, $matches);
		$matches = explode(":", $matches[0]);
		$level = filter_var($matches[1], FILTER_SANITIZE_NUMBER_INT);
		if ($level < 6) {
			echo $line . PHP_EOL;
		}
	}

	fclose($handle);
} else {
	// error opening the file.
}

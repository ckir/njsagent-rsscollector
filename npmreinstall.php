<?php
error_reporting(E_ALL);
date_default_timezone_set("UTC");
stream_set_blocking(STDIN, 0);

$deps = json_decode(file_get_contents('package.json'), true);
$dependencies = $deps['dependencies'];

foreach ($dependencies as $dependency => $value) {
    $command = "npm remove $dependency --save";
    exec($command, $output, $return_var);
    if ($return_var === 0) {
        echo "Succesfully removed " . $dependency . PHP_EOL;
        $command = "npm install $dependency --save";
        exec($command, $output, $return_var);
        if ($return_var === 0) {
            echo "Succesfully reinstalled " . $dependency . PHP_EOL;
        } else {
            echo "Failed to reinstall " . $dependency . PHP_EOL . implode(PHP_EOL, $output) . PHP_EOL;
        }
    } else {
        echo "Failed to remove " . $dependency . PHP_EOL . implode(PHP_EOL, $output) . PHP_EOL;
    }
}
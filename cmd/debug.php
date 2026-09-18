<?php
var_dump($argv);
foreach ($argv as $i => $arg) {
    echo "[$i] len=" . strlen($arg) . " hex=" . bin2hex($arg) . "\n";
}
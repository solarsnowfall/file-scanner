<?php

include 'vendor/autoload.php';

use Solar\Scanner;

$scanner = new Scanner(
    '.',
    'Scanner',
    Scanner::MODE_CONTENTS
);

$scanner->scan();
$scanner->dump();
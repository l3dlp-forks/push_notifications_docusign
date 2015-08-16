<?php

# test

define("APP", "Push Notifications for DocuSign");
include (realpath(dirname(__FILE__) . '/public/bootstrap.php'));

$g = new PND_google_log;
$g->test();

#!/usr/bin/env php
<pre>
<?php

require_once("libEAPS.php");

$ec = new EAPS_Client("https://ssl.xionic.co.uk/debug/EAPS/", "f1495127-68d2-4402-9103-5663798b25c8");

$resp = $ec->get_values("drugs");

var_dump($resp->keys);

	

?>
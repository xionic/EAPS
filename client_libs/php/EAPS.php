#!/usr/bin/env php
<pre>
<?php

require_once("libEAPS.php");

$ec = new EAPS_Client("https://ssl.xionic.co.uk/debug/EAPS/", "76245766-cfca-416f-a141-47fc2a14b59f");

$resp = $ec->get_values("newtag");

var_dump($resp);

$ec->add_value("newtag", "testkey", "testvalue1");

	

?>
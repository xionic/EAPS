<?php

$req_start_time = microtime(true);

require_once("config.php");
require_once("lib/Argh/src/Argh.php");
require_once("functions.php");

use \xionic\Argh\Argh;

if(isset($_GET["action"]))
	$action = $_GET["action"];
else 
	send_error("missing argument action");

//ensure we have a client_key; tag is validated per-action where needed
$args = $_GET;
Argh::validate($args, [
    "client_key" => ["notblank"],
]);

print_debug("Request received for action: " . $action . " (" . get_req_type() .")", INFO);
print_debug("REQUEST: " . var_export($_REQUEST, true), DEBUG);

switch ($action){
	case "tags":
		handle_tags_req();
		break;
	case "keys":
		handle_keys_req();
		break;
	case "value":
		handle_value_req();
		break;	
	case "values":
		handle_values_req();
		break;
	case "delete":
		handle_delete_req();
		break;
	default:
		send_error("Invalid action");
		break;
}
//print_debug("start:" . $req_start_time . " end:" . microtime(true), DEBUG);
print_debug("Request for action: " . $action . " completed in " . number_format((microtime(true)-$req_start_time),4) . "s", INFO);


?>
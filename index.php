<?php

$req_start_time = microtime(true);

require_once("config.php");
require_once("lib/PHPArgValidator/PHPArgValidator.class.php");
require_once("functions.php");

if(isset($_GET["action"]))
	$action = $_GET["action"];
else 
	send_error("missing argument action");

//ensure we have a tag and client_key
$av = get_arg_validator();
$args = $av->validateArgs($_GET, array(
	"client_key" => array("notblank"),
	"tag" => array("notblank"),
));

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
	default:
		send_error("Invalid action");
		break;
}
//print_debug("start:" . $req_start_time . " end:" . microtime(true), DEBUG);
print_debug("Request for action: " . $action . " completed in " . number_format((microtime(true)-$req_start_time),4) . "s", INFO);


?>
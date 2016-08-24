<?php

require_once("config.php");
require_once("lib/PHPArgValidator/PHPArgValidator.class.php");
require_once("functions.php");

$action = $_GET["action"];

//ensure we have a tag and client_key
$av = get_arg_validator();
$args = $av->validateArgs($_GET, array(
	"client_key" => array("notblank"),
	"tag" => array("notblank"),
));

switch ($action){
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

?>
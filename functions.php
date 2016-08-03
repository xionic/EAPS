<?php

function handle_keys_req(){
	switch (get_req_type()){
		case "GET":		
			$keys = get_keys(get_current_tag());
			send_response(null, $keys);
		case "POST": //Keys are inserted as required when values are inserted
			send_error("Cannot directly add a key. New keys are created as required when values are inserted", 400);
	}	
}

function get_keys($tag_name){	
	$db = get_db_connection();
	$stmt = $db->prepare(
		"SELECT key_id, key_name FROM tKey INNER JOIN tTag ON (tKey.tag_id = tTag.tag_id) where tag_name = :tag_name"
	);
	$stmt->bindValue(":tag_name",$tag_name,PDO::PARAM_STR);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $rows;
}

function handle_value_req (){
	switch (get_req_type()){
		case "GET": //get the latest value for the given key
			$av = get_arg_validator();
			$args = $av->validateArgs($_GET, array(
				"tag" => array("notblank"),
				"key" => array("notblank"),
			));
			$tag = $args["tag"];
			$key = $args["key"];
			$value = get_latest_value($tag, $key);

			send_response(array($value),array($key));			
			break;
			
		case "POST": //add a value		
			$av = get_arg_validator();
			$args = $av->validateArgs($_GET, array(
				"tag" => array("notblank"),
				"key" => array("notblank"),
				"value" => array("string"),
			));
			
			$client_id = get_current_client_id();
			if($client_id == null) {
				send_error("Invalid client_id");
			}
			$tag = get_current_tag();
			$key = $args["key"];
			$value = $args["value"];
			add_value($client_id, $tag, $key, $value);
	}	
}

function get_latest_value($tag_name, $key_name){
	$client_id = get_current_client_id();
	
	$db = get_db_connection();
	$stmt = $db->prepare("SELECT 
							key_name as key, value_id, value_data as value, strftime('%s',created) as created
						FROM 
							tTag 
							INNER JOIN tKey ON (tTag.tag_id = tKey.tag_id)
							INNER JOIN tValue ON (tKey.key_id = tValue.key_id)
						WHERE 
							client_id = :client_id
							AND tag_name = :tag_name
							AND key_name = :key_name
							AND tKey.newest_value_id = tValue.value_id
							
						");
	$stmt->bindValue(":client_id",$client_id,PDO::PARAM_INT);
	$stmt->bindValue(":tag_name",$tag_name,PDO::PARAM_STR);
	$stmt->bindValue(":key_name",$key_name,PDO::PARAM_STR);
	$stmt->execute();
	
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row;

}

function add_value($client_id, $tag_name, $key_name, $value_data){
	$db = get_db_connection();
	$db->beginTransaction();
	
	//try to insert the tag, if it fails we'll assume it's already in.
	print_debug("trying to insert a new tag", DEBUG);
	print_debug($client_id . " " . $tag_name, DEBUG);
	try {
		$stmt = $db->prepare("INSERT INTO tTag(tag_name, client_id) VALUES(:tag_name, :client_id)");
		$stmt->bindValue(":tag_name",$tag_name,PDO::PARAM_STR);
		$stmt->bindValue(":client_id",$client_id,PDO::PARAM_INT);
		$stmt->execute();
	} catch (PDOException $pe){		
		if($pe->getCode() != "23000"){ // 23000 = integrity constract violation - presumably the tag already exists so ignore
			throw $pe;
		}
	}	
	//retrieve the tag_id
	$stmt = $db->prepare("SELECT tag_id FROM tTag WHERE tag_name = :tag_name AND client_id = :client_id");
	$stmt->bindValue(":tag_name",$tag_name,PDO::PARAM_STR);
	$stmt->bindValue(":client_id",$client_id,PDO::PARAM_STR);
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$tag_id = $row["tag_id"];
	
	//try to insert the key, if it fails we'll assume it's already in.
	print_debug("trying to insert a new key", DEBUG);
	print_debug($tag_id . " " . $key_name, DEBUG);
	try {
		$stmt = $db->prepare("INSERT INTO tKey(tag_id, key_name, newest_value_id) VALUES(:tag_id, :key_name, :newest_value_id)");
		$stmt->bindValue(":tag_id",$tag_id,PDO::PARAM_INT);
		$stmt->bindValue(":key_name",$key_name,PDO::PARAM_STR);	
		$stmt->bindValue(":newest_value_id",-1,PDO::PARAM_INT);	//New rows will not have a value yet, this will be updated below
		$stmt->execute();
	} catch (PDOException $pe){		
		if($pe->getCode() != "23000"){ // 23000 = integrity constract violation - presumably the tag already exists so ignore
			throw $pe;
		}
	}
	
	//retrieve the key_id
	$stmt = $db->prepare("SELECT key_id FROM tKey WHERE key_name = :key_name AND tag_id = :tag_id");
	$stmt->bindValue(":key_name",$key_name,PDO::PARAM_STR);
	$stmt->bindValue(":tag_id",$tag_id,PDO::PARAM_STR);
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$key_id = $row["key_id"];
	
	//insert the value - we always insert a new value.
	print_debug("Inserting value - tag_id: $tag_id key_id: $key_id value_data :$value_data", DEBUG);
	$stmt = $db->prepare("INSERT INTO tValue(key_id, value_data, created) VALUES(:key_id, :value_data, :created)");
	$stmt->bindValue(":key_id",$key_id,PDO::PARAM_INT);
	$stmt->bindValue(":value_data",$value_data,PDO::PARAM_STR);
	$stmt->bindValue(":created",time(),PDO::PARAM_INT);
	$stmt->execute();
	
	//update tKey with the latest value id
	$last_id = $db->lastInsertId();
	print_debug("Updating tKey with last value id - last_id: $last_id key_id: $key_id tag_id: $tag_id", DEBUG);
	$stmt = $db->prepare("UPDATE tKey set newest_value_id = :last_id WHERE key_id = :key_id AND tag_id = :tag_id");
	$stmt->bindValue(":last_id",$last_id,PDO::PARAM_INT);
	$stmt->bindValue(":key_id",$key_id,PDO::PARAM_INT);
	$stmt->bindValue(":tag_id",$tag_id,PDO::PARAM_INT);
	$stmt->execute();
	
	$db->commit();
	$db = null;
	
	rest_response("", 201);	
}

function handle_values_req(){
	switch (get_req_type()){
		case "GET": //get the latest value for the given key
			$av = get_arg_validator();
			$args = $av->validateArgs($_GET, array(
				"tag" => array("notblank"),
				"key" => array("optional", "notblank"),
				"since" => array("optional", "int"),
			));
			$tag = $args["tag"];
			$since = null;
			$key = null;
			if(isset($args["since"]))
				$since = $args["since"];
			if(isset($args["key"]))
				$key = $args["key"];
			
			
			$values = get_values($tag, $key, $since);
			
			$keys = array();
			foreach($values as $v){
				$keys[$v["key"]] = null;
			}
			$keys = array_keys($keys);
			
			//build a unique list of the keys
			//TODO NEXT - make it so that values reqs can be made with just a tag

			send_response($values,$keys);			
			break;
			
		case "POST": //Has no meaning for values req
			send_error("Cannot add multiple values", 400);
	}
}

/* Retrieve value(s) from the store
	if key_name is omitted or is null, then all key/value datasets for the tag are returned.
*/
function get_values($tag_name, $key_name = null, $since = null){
	
	print_debug("retriving values for tag: '$tag_name' key: '$key_name' since: '$since'", DEBUG);
	
	$client_id = get_current_client_id();
	
	$db = get_db_connection();
	
	$key_sql= "";
	$since_sql= "";
	if($key_name != null)		
		$key_sql = "AND key_name = :key_name";
	if($since != null)
		$since_sql = "AND created > :since";
	
	$sql = "SELECT
				key_name as key, value_id, value_data as value, strftime('%s',created) as created
			FROM 
				tTag 
				INNER JOIN tKey ON (tTag.tag_id = tKey.tag_id)
				INNER JOIN tValue ON (tKey.key_id = tValue.key_id)
			WHERE 
				client_id = :client_id
				AND tag_name = :tag_name
				$key_sql
				$since_sql
			ORDER BY 
				tValue.created DESC
							
	";
						
	$stmt = $db->prepare($sql);
	$stmt->bindValue(":client_id",$client_id,PDO::PARAM_INT);
	$stmt->bindValue(":tag_name",$tag_name,PDO::PARAM_STR);
	if($key_name != null)
		$stmt->bindValue(":key_name",$key_name,PDO::PARAM_STR);
	if($since != null)
		$stmt->bindValue(":since",$since,PDO::PARAM_INT);
	$stmt->execute();
	
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $rows;
}

function get_client_id($client_key){
	global $current_request_client_id;
	print_debug("client_key=$client_key", DEBUG);
	
	if($current_request_client_id == null){
		$db = get_db_connection();
		$stmt = $db->prepare(
			"SELECT client_id FROM tClient WHERE client_key = :client_key"
		);
		$stmt->bindValue(":client_key",$client_key,PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$client_id = $row["client_id"];
		print_debug("client_id=$client_id", DEBUG);
			
		$db = null;	
		$current_request_client_id = $client_id;
	}
	return $current_request_client_id;
}


// --- HELPERS --- //

function print_debug($text, $level = 1){
	if(1){
		$debugInfo = debug_backtrace(1);
		if(count($debugInfo) > 1)
			$callingfn = $debugInfo[1]["function"];
		else
			$callingfn = "[origin unknown]";
		echo $callingfn . ": " . $text . PHP_EOL;
	}
}


//GET, POST, PUT, DELETE etc
function get_req_type(){
	return $_SERVER["REQUEST_METHOD"];
}

function get_current_tag(){
	$av = get_arg_validator();
	$args = $av->validateArgs($_GET, array(
		"tag" => array("notblank"),
	));
	return $args["tag"];
}

function get_current_client_id(){	
	return get_client_id($_GET["client_key"]);
}

function get_db_connection()
{
	global $db;
	if($db == null){
		$db = new PDO(PDO_DSN);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	

		//enable foreign key enforcement - not enabled by default in sqlite and must be done per connection
		//TODO make this only happen for sqlite
		$db->exec("PRAGMA foreign_keys = ON");
	}
	return $db;
}

function rest_response($body, $status = 200)
{
	http_response_code($status);
	header('Content-Type: text/json');
	echo $body;
	exit;
}

//send a data response
function send_response($dataset, $keys = null, $status = 200){
	$data = ["keys" => $keys, "data" => $dataset];
	rest_response(json_encode($data));

}

function send_error($message, $code = 400){
	rest_response(json_encode(array("error_message" => $message)), $code);
}

function get_arg_validator(){
	global $arg_validator;
	if ($arg_validator == null){
		$arg_validator = new ArgValidator(function($msg, $argName="", $argValue=""){
			send_error("invalid args: $msg --- argname: $argName argvalue: $argValue");
			die;
		});
	}
	return $arg_validator;
}


?>
<?php

	require_once("config.php");
	require_once("lib/PHPArgValidator/PHPArgValidator.class.php");
	require_once("functions.php");	
	
	
	if (!isset($_GET["csv"])){
?>
<!DOCTYPE html>
<html>
	<head>
		<style type="text/css">
			table {
				border-collapse: collapse
			}
			td {
				border: solid black 1px;
				padding: 5px;
			}
		</style>
	</head>
	<body>
		<form method="GET">
		<div>
			<label for = "client_key">Client name:</label>
			<select name="client_key" id="client_key">		
			<?php
				if(isset($_GET["client_key"])){
					$client_key = $_GET["client_key"];
				}
				foreach(get_clients() as $client){
					echo "<option value='" . $client["client_key"] . "' " . ((isset($client_key)&&$client_key==$client["client_key"])?"selected":"") . ">" . htmlentities($client["client_name"]) . "</option>";
				}
			?>
			</select>
		</div>
		<div>
			<label for = "tag">Tag:</label>
			<select name="tag" id="tag">		
			<?php
				if( isset($client_key) && isset($_GET["tag"])){
					$tag_name = $_GET["tag"];
				}
				if(isset($client_key)){
					foreach(get_tags($client_key) as $tag){
						echo "<option value='" . $tag["tag_name"] . "' " . ((isset($tag_name)&&$tag_name==$tag["tag_name"])?"selected":"") . ">" . htmlentities($tag["tag_name"]) . "</option>";
					}
				}
			?>
			</select>
		</div>
		<div>
			<label for = "key">Key:</label>
			<select name="key" id="key" >
			<?php
				if( isset($tag_name) && isset($_GET["key"])){
					$key = $_GET["key"];		
				}
				if(isset($tag_name)){	
				
					foreach(get_keys($tag_name) as $k){
						//var_dump($key);
						echo "<option value='" . $k["key_name"] . "' " . ((isset($key)&&$key==$k["key_name"])?"selected":"") . ">" . htmlentities($k["key_name"]) . "</option>";
					}
				}				
			?>
			</select>
		</div>
		<div>
			<label for = "since">Since:</label>
			<input type="datetime-local" name="since" id="since"
			<?php
				if(isset($_GET["since"])){
					$since = $_GET["since"];
					echo " value='$since'";
				}
			
			?>
			>
		</div>
			
			<input name="menu" type="submit" value="Update Menu">
			<input name="submit" type="submit" value="Search">
			<a href='<?php echo $_SERVER["SCRIPT_URL"] . "?" . $_SERVER["QUERY_STRING"] . "&csv=true"; ?>'>As CSV </a>
		</form>
		<pre>
<?php
	}
	
	if(!isset($_GET["submit"])) exit;
	
	$av =  new ArgValidator(function($msg, $argName="", $argValue=""){
		die("invalid args: $msg --- argname: $argName argvalue: $argValue");
	});
	
	$args = $av->validateArgs($_GET, array(
		"client_key" => array("optional", "string"),
		"tag" => array("optional", "string"),
		"key" => array("optional", "string"),
		"since" => array("optional")
	));
	
	
	
	$client_sql = "";
	$tag_sql = "";
	$key_sql = "";
	$since_sql = "";
	
	//build the query with the supplied filters
	if($args["client_key"] != ""){
		$client_sql = "AND client_key = :client_key";
	}
	if(isset($tag_name)){
		$tag_sql = "AND tag_name = :tag_name";
	}
	if(isset($key)){
		$key_sql = "AND key_name = :key_name";
	}
	
	if($args["since"] != ""){
		$since_sql = "AND created > :created";
		$since = strtotime($args["since"]);
	}
	
	
	$sql = "SELECT
		client_name, client_key, tag_name as tag, key_name as `key`, value_id, value_data as `value`, `created`
	FROM 
		tClient
		INNER JOIN tTag ON (tClient.client_id = tTag.client_id)
		INNER JOIN tKey ON (tTag.tag_id = tKey.tag_id)
		INNER JOIN tValue ON (tKey.key_id = tValue.key_id)
	WHERE 
		tClient.client_id = tClient.client_id
		$client_sql
		$tag_sql
		$key_sql
		$since_sql
	ORDER BY 
		tValue.created DESC
					
	";
	
	 try{    
		$db = get_db_connection();
		$stmt = $db->prepare($sql);
		
		if($args["client_key"] != ""){
			$stmt->bindValue(":client_key",$args["client_key"],PDO::PARAM_STR);
		}
		if(isset($tag_name)){
			$stmt->bindValue(":tag_name",$tag_name,PDO::PARAM_STR);
		}
		if(isset($key)){
			$stmt->bindValue(":key_name",$key,PDO::PARAM_STR);
		}
		if($args["since"] != ""){
			$stmt->bindValue(":created",$since,PDO::PARAM_INT);
		}
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	 } catch (PDOException $e){
        return var_dump($e);
    }
	
	
	if (isset($_GET["csv"])){
		
		header('Content-type: text/csv');
		header('Content-disposition: attachment;filename=' . $args["client_key"] . ".csv");
		
		//headers
		if(count($rows)){
			$row_str = "";
			foreach ($rows[0] as $col_name => $value){
				$row_str .= "$col_name,";
			}
			echo substr($row_str, 0, count($row_str)-2);
		}
		echo "\n";
		//data
			foreach ($rows as $row){
				$row_str = "";			
				foreach ($row as $k => $v){	
				//hackhack ...
					if($k == "created" && ! isset($_GET["timestamp"]))
						$row_str .= date("Y/m/d H:i:s", $v) . ",";
					else
						$row_str .= $v . ",";
				}
				echo substr($row_str, 0, count($row_str)-2) . "\n";
			}
	} else {
		echo "<div>" . count($rows) . " records<br></div>";
		echo "<table>\n";
			//headers
			if(count($rows)){
				echo "<tr>\n";
				foreach ($rows[0] as $col_name => $value){
					echo "<th>$col_name</th>\n";
				}		
				echo "</tr>\n";
			}
			//data
			foreach ($rows as $row){
				echo "<tr>\n";		
				foreach ($row as $k => $v){
					echo "<td>\n";
					//hackhack ...
					if($k == "created" && ! isset($_GET["timestamp"]))
						echo date("Y/m/d H:i:s", $v);
					else
						echo $v;
					echo "</td>\n";
				}
				echo "</tr>\n";
			}
			
			echo "</table>\n";	
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
?>
	</body>
<html>
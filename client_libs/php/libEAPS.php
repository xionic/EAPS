<?php

require_once("EAPSValueResponse.class.php");

class EAPS_client{
	private $url = null;
	private $client_key = null;

	function __construct($u, $c){
		$this->url = $u;
		$this->client_key = $c;		
	}
	
	public function get_values($tag, $key = false, $since = false){
		
		$get = array(
			"tag" => $tag
		);
		if($key)
			$get["key"] = $key;
		if($since)
			$get["since"] = $since;
		
		$json = $this->EAPS_req("values", $get);		
		$values = json_decode($json, true);		
		$response = new EAPSValueResponse($values["keys"], $values["data"]);
		
		return $response;
	}
	
	public function add_value($tag, $key, $value){
		$get = array("tag" => $tag);
		$post = array("key" => $key, "value" => $value);
		$this->EAPS_req("value", $get, $post);
	}
	
	//make http req usign curl to the EAPS service - using the action $action, get vars in $query_string, post vars in $post
	private function EAPS_req($action, $query_string = null, $post = null){
		$ch = curl_init(); 

		$get_str = "";
		if($query_string){				
			foreach($query_string as $k => $v){
				$get_str .= "&" . urlencode($k) . "=" . urlencode($v);
			}		 
		}
		curl_setopt($ch, CURLOPT_URL, $this->url . "?action=" . urlencode($action). "&client_key=" . urlencode($this->client_key). "&" . $get_str);



		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if($post){
			curl_setopt($ch, CURLOPT_POST, TRUE);
			$post_str = "";
			foreach($post as $k => $v){
				$post_str .= urlencode($k) . "=" . urlencode($v) . "&";
			}
			rtrim($post_str, "&");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_str);
		}

		$output = curl_exec($ch);
		curl_close($ch);
		
		return $output;
	}
	
	function handle_error($msg){
		die("error: " . $msg);
	}
	
}


?>
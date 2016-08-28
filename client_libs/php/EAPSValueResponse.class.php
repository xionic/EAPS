 <?php

class EAPSValueResponse{	
		public $keys = null;
		public $data = null;
		
		function __construct($k, $d){			
			$this->keys = $k;
			$this->data = $d;
		}

}
	

?>
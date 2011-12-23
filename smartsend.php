<?php
include('app/Mage.php');  
umask(0);
Mage::app();

Mage::getSingleton('core/session', array('name'=>'adminhtml'));
$session = Mage::getSingleton('admin/session');

$db = Mage::getSingleton('core/resource')->getConnection('core_write');

function array_pluck($key, $array){
	 if (is_array($key) || !is_array($array)) return array();
	 $funct = create_function('$e', 'return is_array($e) && array_key_exists("'.$key.'",$e) ? $e["'. $key .'"] : null;');
	 return array_map($funct, $array);
}
	
function getConfig($cfg){
	global $db;
	$corcondat = $db->getTableName('core_config_data');
	
	$config = $db->query("SELECT value FROM $corcondat WHERE path='carriers/smartsend/$cfg'");
	$config = $config->fetch(PDO::FETCH_ASSOC);
	
	return $config["value"];
}

if ($session->getUser()){
	$catproden = $db->getTableName('catalog_product_entity');
	$catprodenvar = $db->getTableName('catalog_product_entity_varchar');
	$salflaoradd = $db->getTableName('sales_flat_order_address');
	$salflaor = $db->getTableName('sales_flat_order');
	$salflaorit = $db->getTableName('sales_flat_order_item');
	
	if($_POST["action"]=="getorder"){
		$stateMap = array(array("australian capital territory"=>"ACT", "new south wales"=>"NSW", "northern territory"=>"NT", "queensland"=>"QLD", "south australia"=>"SA", "tasmania"=>"TAS", "victoria"=>"VIC", "western australia"=>"WA"));
		
		$items = explode("|", $_POST["items"]);
		
		// init calls
		$post_param_values["METHOD"] = "SETBOOKING";
		$post_param_values["USERTYPE"] = getConfig("user_type");
		$post_param_values["USERCODE"] = getConfig("user_code");
		
		$post_param_values["RETURNURL"] = $_POST["url"]."?returnurl=1";
		$post_param_values["CANCELURL"] = $_POST["url"]."?cancelurl=1";
		$post_param_values["NOTIFYURL"] = $_POST["url"]."?notifyurl=1";
		
		$post_url = "http://api.dev.smartsend.com.au/";
		
		$bookingCount=0;
			foreach($items as $item){
				$itemCount=0;
				$tl_none=0;
				$tl_atpickup=0;
				$tl_atdestination=0;
				$tl_both=0;
				
				$customerInfos = $db->query("SELECT company, city, firstname, lastname, street, telephone, postcode, region FROM $salflaoradd WHERE parent_id={$item} LIMIT 1");
				$customerInfos = $customerInfos->fetch(PDO::FETCH_ASSOC);
				$state=array_pluck(strtolower($customerInfos["region"]), $stateMap);
				$post_param_values["BOOKING({$bookingCount})_CONTACTCOMPANY"] = getConfig("contact_company");
				$post_param_values["BOOKING({$bookingCount})_CONTACTNAME"] = getConfig("contact_name");
				$post_param_values["BOOKING({$bookingCount})_CONTACTPHONE"] = 4434554567; //update it dondon
				$post_param_values["BOOKING({$bookingCount})_CONTACTEMAIL"] = getConfig("contact_email");
				
				$post_param_values["BOOKING({$bookingCount})_PICKUPCOMPANY"] = getConfig("pickup_company");
				$post_param_values["BOOKING({$bookingCount})_PICKUPCONTACT"] = getConfig("pickup_contact");
				$post_param_values["BOOKING({$bookingCount})_PICKUPADDRESS1"] = getConfig("pickup_address1");
				$post_param_values["BOOKING({$bookingCount})_PICKUPADDRESS2"] = getConfig("pickup_address2");
				$post_param_values["BOOKING({$bookingCount})_PICKUPPHONE"] = getConfig("pickup_phone");
				$post_param_values["BOOKING({$bookingCount})_PICKUPSUBURB"] = getConfig("pickup_suburb");
				$post_param_values["BOOKING({$bookingCount})_PICKUPPOSTCODE"] = getConfig("pickup_postcode");
				$post_param_values["BOOKING({$bookingCount})_PICKUPSTATE"] = getConfig("pickup_state");
				$post_param_values["BOOKING({$bookingCount})_RECEIPTEDDELIVERY"] = "true";

				
				$post_param_values["BOOKING({$bookingCount})_DESTCOMPANY"] = $customerInfos["company"];
				$post_param_values["BOOKING({$bookingCount})_DESTCONTACT"] = $customerInfos["firstname"]." ".$customerInfos["lastname"];
				$post_param_values["BOOKING({$bookingCount})_DESTADDRESS1"] = $customerInfos["street"];
				$post_param_values["BOOKING({$bookingCount})_DESTPHONE"] = $customerInfos["telephone"];
				$post_param_values["BOOKING({$bookingCount})_DESTSUBURB"] = $customerInfos["city"];
				$post_param_values["BOOKING({$bookingCount})_DESTPOSTCODE"] = $customerInfos["postcode"];
				$post_param_values["BOOKING({$bookingCount})_DESTSTATE"] = $state[0];
				
				$grandTotal = $db->query("SELECT grand_total FROM $salflaor WHERE entity_id={$item}");
				$grandTotal = $grandTotal->fetch(PDO::FETCH_ASSOC);
				
				$post_param_values["BOOKING({$bookingCount})_TRANSPORTASSURANCE"] = $grandTotal["grand_total"];
				
				
				//item init
				$result = $db->query("SELECT qty_ordered, product_id, weight FROM $salflaorit WHERE order_id={$item}");
				while($row = $result->fetch(PDO::FETCH_ASSOC)){
					
					$result2 = $db->query("SELECT description, depth, height, length, taillift FROM smartsend_products WHERE id={$row['product_id']}");
					//item loop
					
					while($row2 = $result2->fetch(PDO::FETCH_ASSOC)){
						for($j=0;$j<floor($row["qty_ordered"]);$j++){
							$post_value_items["BOOKING({$bookingCount})_ITEM($itemCount)_DESCRIPTION"] = $row2["description"];
							$post_value_items["BOOKING({$bookingCount})_ITEM($itemCount)_DEPTH"] = $row2["depth"];
							$post_value_items["BOOKING({$bookingCount})_ITEM($itemCount)_HEIGHT"] = $row2["height"];
							$post_value_items["BOOKING({$bookingCount})_ITEM($itemCount)_LENGTH"] = $row2["length"];
							$post_value_items["BOOKING({$bookingCount})_ITEM($itemCount)_WEIGHT"] = floor($row["weight"]);
							
							if($row2["taillift"]=="none")
								$tl_none=1;
								
							if($row2["taillift"]=="atpickup")
								$tl_atpickup=1;
							
							if($row2["taillift"]=="atdestination")
								$tl_atdestination=1;
								
							if($row2["taillift"]=="both")
								$tl_both=1;
							
							$itemCount++;
						}
					}
					
				if($tl_none==1)
					$dTaillift="none";
				if($tl_atpickup==1 && $tl_atdestination==0)
					$dTaillift="atpickup";
				if($tl_atpickup==0 && $tl_atdestination==1)
					$dTaillift="atdestination";
				if($tl_atpickup==1 && $tl_atdestination==0)
					$dTaillift="atpickup";
				if($tl_atpickup==1 && $tl_atdestination==1)
					$dTaillift="both";
				if($tl_both==1)
					$dTaillift="both";
				
				$post_param_values["BOOKING({$bookingCount})_TAILLIFT"] = $dTaillift;
				}
				
				$bookingCount++;
			}
			/*foreach( $post_value_items as $key => $value ){
				echo "$key - $value\n";
			}
			
			foreach( $post_param_values as $key => $value ){
				echo "$key - $value\n";
			}*/
					
		
			$post_final_values = array_merge($post_param_values,$post_value_items);
			
			# POST PARAMETER AND ITEMS VALUE URLENCODE
			$post_string = "";
			foreach( $post_final_values as $key => $value ){
				if( $value!="" )
				$post_string .= "$key=" . urlencode( $value ) . "&";
			}
			//$post_string .= "BOOKING(0)_PICKUPDATE=25/12/2011&BOOKING(0)_PICKUPTIME=2& ";
			$post_string = rtrim( $post_string, "& " );
			
			//echo $post_string;
			# START CURL PROCESS
			
			/*$request = curl_init($post_url); 
			curl_setopt($request, CURLOPT_HEADER, 0); 
			curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($request, CURLOPT_POSTFIELDS, $post_string);
			curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE);
			$post_response = curl_exec($request); 
			curl_close ($request); // close curl object   **/
			//var_dump($post_response);
			
			echo $post_url."?".$post_string;
			//echo $post_response;
			//echo "ACK=FAILED&TOKEN=ejfklj453589&BOOKINGURL=http://www.google.com&ERROR(0)=Error%20Message%201&ERROR(1)=Error%20Message%202";
	}
	
	if($_POST["action"]=="add"){
		$result = $db->query("SHOW TABLE STATUS LIKE '$catproden'");
		$rows = $result->fetch(PDO::FETCH_ASSOC);
	
		$next_id = $rows['Auto_increment']-1;
		
		$depth=$_POST["depth"];
		$length=$_POST["length"];
		$height=$_POST["height"];
		$desc=$_POST["description"];
		$taillift=$_POST["taillift"];
		$db->query("INSERT INTO smartsend_products (description, id, depth, length, height, taillift) VALUES('$desc', '$next_id', '$depth', '$length', '$height','$taillift')");
	}
	
	if($_GET["action"]=="attr"){
		header("Content-type: text/javascript");
		$pID=$_GET["pID"];
		$result = $db->query("SELECT * FROM smartsend_products WHERE id='$pID'");
		
		$row = $result->fetch(PDO::FETCH_ASSOC);
		$height = $row["height"];
		$length = $row["length"];
		$depth = $row["depth"];
		$desc = $row["description"];
		$taillift = $row["taillift"];
		
		echo '$j("input[name=\'products_height\']").val("'.$height.'");';
		echo '$j("input[name=\'products_length\']").val("'.$length.'");';
		echo '$j("input[name=\'products_depth\']").val("'.$depth.'");';
		echo '
		desc="'.$desc.'";
		var ItemTypeMap = {
				"envelope" : 0,
				"carton" : 2, 
				"satchel" : 3,
				"bag" : 3,
				"tube" : 4,
				"skid" : 5, 
				"pallet" : 6, 
				"crate" : 7, 
				"flatpack" : 8, 
				"roll" : 9, 
				"length" : 10, 
				"tyre" : 12,
				"wheel" : 12, 
				"furniture" : 13, 
				"bedding" : 13
			}[desc];
			$j("select[name=\'description\'] option[value=\'"+ItemTypeMap+"\']").attr("selected", true);
		var tl="'.$taillift.'";
		var TailLiftTypeID = { 
		"none" : 0, 
		"atpickup" : 1, 
		"atdestination" : 2, 
		"both" : 3}[tl.toLowerCase()];
			$j("select[name=\'TailLift\'] option[value=\'"+TailLiftTypeID+"\']").attr("selected", true);
		';
		
	}
	
	if($_POST["action"]=="edit"){
		$depth=$_POST["depth"];
		$length=$_POST["length"];
		$height=$_POST["height"];
		$desc=$_POST["description"];
		$taillift=$_POST["taillift"];
		$pID=$_POST["pID"];
		
		$count = $db->query("SELECT depth from smartsend_products WHERE id='$pID'");
		$row = $count->fetch(PDO::FETCH_ASSOC);
		if($row["depth"]){
			$update = $db->query("UPDATE smartsend_products SET depth = '$depth', length = '$length', height='$height', description='$desc', taillift='$taillift' WHERE id='$pID'");
		}
		
		else{
			$db->query("INSERT INTO smartsend_products (description, id, depth, length, height, taillift) VALUES('$desc', '$pID', '$depth', '$length', '$height', '$taillift') ");	
		}
	}
	
	if($_GET["action"]=="alertscr"){

		$i=0;
		$result = $db->query("SELECT $catproden.entity_id AS did FROM $catproden WHERE $catproden.entity_id NOT IN (SELECT smartsend_products.id FROM smartsend_products) LIMIT 10");

		while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$id = $row["did"];
			$daname = $db->query("SELECT value from {$catprodenvar} WHERE entity_id = {$id} LIMIT 1");
			$named = $daname->fetch(PDO::FETCH_ASSOC);
			$name = addslashes($named["value"]);
			
			echo "sItems[$i]=[$id,'$name'];\n";
			
			$i++;
		}

		if($i!=0){
			echo "msgTitle='Please update the depth, length, height and best packing method for the following products';";	
		}

		header("Content-type: text/javascript");
	}
}

?>
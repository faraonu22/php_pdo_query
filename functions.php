<?php
require_once 'connect.php';
class Functions{
	private $conn_obj;
	private $conn;

	public function __construct(){
		$this->conn_obj = new Connect();
		$this->conn = $this->conn_obj->DBConnection();
	}

	public function sql_exec($sql_cmd, $export = "", $write_log = 2) {		
		ini_set('max_execution_time', 28800);

		$sql_cmd = trim(str_replace(PHP_EOL, ' ', $sql_cmd));
		if (substr($sql_cmd,-1) != ';') {$sql_cmd .= ';';}

		while (is_numeric($pos = strpos($sql_cmd, ';'))) {
			$sql = substr($sql_cmd, 0, $pos);
			$sql_cmd = substr($sql_cmd, $pos+1);
			if (((is_numeric(strpos($sql, ':'))) && (strpos($sql, ':=')===false)) || (strtoupper(substr($sql, 0,6)) == "SELECT")) {
				return self::sql_execB($sql, $export, $write_log);
			}
			else {
				return self::sql_execNB($sql, true);
			}
		}		
	}

	// executare cu bind
	public function sql_execB($sql_cmd, $export, $write_log) {
		// global $conn;
		if (!$this->conn->inTransaction()){
			$this->conn->beginTransaction();
		}
		try {
			$sql_con = $this->conn->prepare($sql_cmd);
			self::sql_bind($sql_con,$sql_cmd);
			
			if (strtoupper(substr($sql_cmd, 0,6)) !== "SELECT" && (strtoupper(substr($sql_cmd, 0,4) !== "SHOW"))){
				$sql_data = $sql_con->execute();
				// self::write_logs("",json_encode($sql_data),$write_log);
				if ($sql_data === true) {
					// self::write_logs("","-1-",$write_log);
					$this->conn->commit();
					ini_set('max_execution_time', 1000);
					return "OK";
				}
				else {
					throw new Exception("-B1-".json_encode($sql_con->errorInfo()));
					// self::write_logs($sql_cmd,"-B1-".json_encode($sql_con->errorInfo()));
				}
			}
			else {
				// self::write_logs("","-2-",$write_log);
				if ($sql_con->execute()) {
					if ($sql_con->rowCount() > 0){
						$sql_data = array();
						// throw(new Exception("-Bnnnn-"));
						while ($row = $sql_con->fetch(PDO::FETCH_ASSOC)) {
							$sql_data[] = $row;
						}
						// self::write_logs("",json_encode($sql_data),$write_log);
						$this->conn->commit();

						ini_set('max_execution_time', 1000);
						switch ($export) {
							case 'return': return $sql_data; break;
							case '0': echo json_encode($sql_data[0]); break;
							case '1': return implode('', array_values($sql_data[0])); break;
							default: return "OK"; break;
						}
					}
					else {
						// throw(new Exception("Eroare row count"));
						// self::write_logs($sql_cmd, "Eroare row count");
					}
				}
				else {
					$this->conn->rollBack();

					throw new Exception("B2--".json_encode($sql_con->errorInfo()));
					// self::write_logs($sql_cmd, "B2--".json_encode($sql_con->errorInfo()));
					// echo json_encode("EROARE SQL_EXEC !"."@@".$sql_cmd."-B2-".json_encode($sql_con->errorInfo()[0])."@@");
					// return ""; //$sql_con->errorInfo()[0];
				}
			}
		}
		catch (PDOException $e) {
			$this->conn->rollBack();
			file_put_contents("err_LOGS.txt", "BAAAU1->".$e->getMessage().";\n SQL_CMD--> ".$sql_cmd."\n", FILE_APPEND);
			echo json_encode("EROARE SQL_EXEC B 1!"."@@".$sql_cmd)."@@".$e->getMessage()."-B2-".json_encode($sql_con->errorInfo()[0]);
			// self::write_logs("", $e->getMessage().">>".json_encode($sql_con->errorInfo()), $write_log);
		}
		catch (Exception $e) {
			// print_r($sql_con->errorInfo()[0]);
			file_put_contents("err_LOGS.txt", "BAAAU2->".$e->getMessage().";\n SQL_CMD--> ".$sql_cmd."\n", FILE_APPEND);
			echo json_encode("EROARE SQL_EXEC B 2!"."@@".$e->getMessage()."-B2-".json_encode($sql_con->errorInfo()[0])."@@".$sql_cmd);
			// self::write_logs("", $e->getMessage().">>".json_encode($sql_con->errorInfo()), $write_log);
		}

		// self::write_logs("","(IESIRE)",$write_log);
		return ""; //$sql_con->errorInfo()[0];
	}

	// executare fara bind
	public function sql_execNB($sql_cmd, $write_log) {
		if (!$this->conn->inTransaction()){
			$this->conn->beginTransaction();
		}
		try {
			$sql_data = $this->conn->exec($sql_cmd);
        	$err = $this->conn->errorInfo();
			if ($sql_data === false) {
        		if ($err[0] === '00000' || $err[0] === '01000') {
	            	$sql_data = true;
	        	}
	        	else {
	        		$this->conn->rollBack();
					// throw new Exception("-NB-".json_encode($err));
				}
    		}
    		else {
    			$sql_data = true;
    		}
			if ($sql_data===true) {
				$this->conn->commit();
				// ini_set('max_execution_time', 1000);
				return "OK";//$sql_data;
			}
		}
		catch (PDOException $e) {
			// $conn->rollBack();
			// self::write_logs("", $e->getMessage(), $write_log);
			file_put_contents("err_LOGS.txt", "BAAAU->".$e->getMessage().";\n SQL_CMD--> ".$sql_cmd."\n", FILE_APPEND);
			// print_r($e->getMessage());
			if (stripos($e->getMessage(), "1062") !== -1){ //DUPLICATE ENTRY
				// print_r($_POST);
				return "DUPLICATE_ENTRY";
			}
			else{
				echo json_encode("EROARE SQL_EXEC NB!"."@@".$e->getMessage()."@@".$sql_cmd);
			}
		}
		// catch (Exception $e) {
		// 	// self::write_logs("", $e->getMessage(), $write_log);
		// 	echo json_encode("EROARE SQL_EXEC !"."@@".$e->getMessage()."@@");
		// }
		return "ERR";//0;
	}

	private function sql_bind($con, $sql_cmd){
		$pos_end = 0;
		$pos_arr = array();
		$len = strlen($sql_cmd);
		while (is_int($pos_start = strpos($sql_cmd, ':', $pos_end))) {
		 	if ($pos_end == 0) { $pos_end = $len; }
			if (!is_int($x1 = strpos($sql_cmd,')',$pos_start))) { $x1 = $len; }
			if (!is_int($x2 = strpos($sql_cmd,'"',$pos_start))) { $x2 = $len; }
			if (!is_int($x3 = strpos($sql_cmd,"'",$pos_start))) { $x3 = $len; }
			if (!is_int($x4 = strpos($sql_cmd,';',$pos_start))) { $x4 = $len; }
			if (!is_int($x5 = strpos($sql_cmd,',',$pos_start))) { $x5 = $len; }
		    if (!is_int($x6 = strpos($sql_cmd,' ',$pos_start))) { $x6 = $len; }
			$pos_end = min($x1,$x2,$x3,$x4,$x5,$x6);				
			// if (!is_int($pos_end = strpos($sql_cmd,' ',$pos_start))) {
			// 	$pos_end = strlen($sql_cmd);
			// }
			$pos_cmd = trim(substr($sql_cmd, $pos_start+1, $pos_end-$pos_start-1));
			if (!in_array($pos_cmd, $pos_arr)) {
				array_push($pos_arr,$pos_cmd);
			}
		}
		foreach ($pos_arr as $value) {
			if (stripos($value, "=") === false){
				if (!isset($_POST[$pos_cmd])) {
			 		throw new Exception("-BIND-".json_encode($sql_cmd));
					// die('EROARE ! Variabila necunoscuta : '.$value.' ! ('.$sql_cmd.')');
				}
				// print_r(':'.$value.'---'. $_POST[$value]);
				$con->bindParam(':'.$value, $_POST[$value]);
			}
		}
	}
}
?>
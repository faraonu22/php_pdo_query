<?php
class Connect {
	private $ini = array();

	public function __construct(){
		$this->ini = parse_ini_file('conf.ini');
	}

    public function DBConnection(){
        try {
            $options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES latin1',);
			$conn = new PDO("mysql:host=".$this->ini['host']."; dbname=".$this->ini['db_name'].";port=".$this->ini['port'], 
							$this->ini['db_user'], $this->ini['db_password'], $options);
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			return $conn;
        }
        catch(PDOException $e) {
            echo 'ERROR: ' . $e->getMessage();
        }
    }
}
?>
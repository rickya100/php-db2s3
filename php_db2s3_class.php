<?php
/**
 * DB Backup 
 *
 * Dumps mysql database then pushes it to S3
 *
 *@package DB2S3
 */ 

class php_db2s3{
		
		//Instantiate class properties	
		protected $db_host = NULL;
		protected $db_username;
		protected $db_password;
		protected $db_name = NULL;
		
		protected $aws_access_key = NULL;
		protected $aws_secret_key = NULL;
		protected $aws_bucket_name = NULL;
		
		protected $mysqldump_path = 'mysqldump';
		protected $mysqldump_filename = NULL;
				
		protected $arg_num_required = 5;
		
		protected $force_backup_all_databases_flag = FALSE;
		
		protected $email_to_notify = NULL;
		
		const DEFAULT_MYSQLDUMP_FILENAME = 'php_db2s3_backup.sql';
		
		
		
		 /**
		 * Constructor method
		 *
		 * Performs basic verification of data at object instantiation
		 *
		 * @param string $host
		 * @param string $username
		 * @param string $pass
		 * @param string $aws_access_key
		 * @param string $aws_secret_key
		 * @param string $aws_bucket_name
		 * @throws Exception If all params aren't set and if any param is empty or NULL
		 */ 
		  function __construct($username = NULL, $password = NULL, $aws_access_key = NULL, $aws_secret_key = NULL, $aws_bucket_name = NULL ) {				
				
				date_default_timezone_set('UTC');
				
				$arg_num = func_num_args();
				$arg_list = func_get_args();
								
				try{
					$this->_check_num_construct_args($arg_num);
					$this->_check_construct_arg_validaty($arg_list);
				}
				catch(Exception $e){
					echo 'Error instantiating object. ' . $e->getMessage();
					exit;
				}	
								
				//if checks don't throw an error then store the details
				$this->db_username = $username;
				$this->db_password = $password;
				$this->aws_access_key = $aws_access_key;
				$this->aws_secret_key = $aws_secret_key;
				$this->aws_bucket_name = $aws_bucket_name;
						
				//include the S3 class by Don @ http://undesigned.org.za/2007/10/22/amazon-s3-php-class
				require_once('S3.php');	
								
		  }
		  
		  
		  
		 
		 /**
		 * Check that there is the correct number or arguments being passed to the constructor
		 *
		 * @param integer $arg_num
		 * @throws Exception If the number of arguments passed isn't equal to the number expected
		 */ 
		 private function _check_num_construct_args($value){
					if($value != $this->arg_num_required){
						throw new Exception('You haven\'t supplied all required object constructor arguments. You must suppy ' . $this->arg_num_required . ' arguments.');
					}
		 }
		 
		 
		 
		 
		 
		 
		 /**
		 * Checks for basic validaty of arguments passed
		 *
		 * @param integer $arg_list
		 * @throws Exception If element in array is not an integer
		 */ 
		 private function _check_construct_arg_validaty($arg_list){
				foreach( $arg_list as $key => $val ){
						$val = trim($val);
						$arg_val = ( $val == NULL || empty($val) || $val == '' ) ? TRUE : FALSE;
						
						if($arg_val === TRUE ){
							throw new Exception('You MUST supply all your DB credentials + your amazon web services s3 access key, secret key and bucket name');
						}					
				}
		 }
		 
		 
		 
		 
		 /**
		 * Before running db backup check if filename has been set and is writable
		 *
		 */ 
		 private function pre_backup_db_checks(){
		 		try{
					if( $this->mysqldump_filename === NULL ){
						throw new Exception('Filename not supplied. Using default one');
					}
				}
				catch(Exception $e){
						$this->mysqldump_filename = $this::DEFAULT_MYSQLDUMP_FILENAME;
				}
				
				try{
					if ( !is_writable('.') ) {
						throw new Exception('Filename is not writable');
					}
				}
				catch(Exception $e){
						echo 'Error: ' . $e->getMessage();
						exit;
				}	
		 }
		 
		 
		 /**
		 * Runs the backup to S3 against all databases on the server.
		 *
		 * Runs mysqldump with the --all-databases flag to backup all databases on the server. Note that this can produce large files 
		 * and may cause errors. If you are pretty sure that the resultant dump won't be too large (> 2GB) then feel free to use.
		 * Calling this method will backup all databases even if you have supplied a specific database name via set_db_name public method or
		 * if you have set force_backup_all_databases_flag to FALSE.
		 *
		 *
		 * @return boolean depending on if backup was successful. Returns true if everything is a-ok
		 * @throws Exception If backup failed.
		 */		 
		 public function backup_all_databases(){
				
				$this->pre_backup_db_checks();	 		
				
				$this->force_backup_all_databases_flag = TRUE;
				
				try{
					$this->run_mysqldump_backup();
				}
				catch(Exception $e){
						echo 'Error: ' . $e->getMessage();
				}
		 }
		 
		 
		  /**
		 * Runs the backup to S3 against all databases on the server but creates individual files of each DB rather than one large one.
		 *
		 * Runs mysqldump in a loop through all DBs on the server. 
		 * Calling this method will backup all databases even if you have supplied a specific database name via set_db_name public method or
		 * if you have set force_backup_all_databases_flag to FALSE.
		 *
		 *
		 * @return boolean depending on if backup was successful. Returns true if everything is a-ok
		 * @throws Exception If backup failed.
		 */		 
		 public function backup_all_databases_individually(){
				
				$this->pre_backup_db_checks();
				
				try{
					if( $this->db_host === NULL  ){
							throw new Exception('db host must be set to be able to backup DBs individually ');
					}
				}
				catch(Exception $e) {
					echo "To use this method you must supply your database host";
					exit;
				}
				
				
				try {
				  $DBH = new PDO("mysql:host=$this->db_host", $this->db_username, $this->db_password);
				  $DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
				}
				catch(Exception $e) {
					echo $e->getMessage();
					exit;
				}
				
				$this->force_backup_all_databases_flag = FALSE;
				
				$STH = $DBH->query('SHOW DATABASES');
				$STH->setFetchMode(PDO::FETCH_OBJ);
				while($row = $STH->fetch()) {
						$db_name = array($row->Database);
						$this->set_db_name($db_name);
						$this->set_mysqldump_filename($row->Database.'_'.date('Ymd-H:i:s').'.sql');
						try{
							$this->backup_specific_databases();
						}
						catch(Exception $e){
								echo 'Error: ' . $e->getMessage();
								echo '<br />';
						}
				}

												
					 		
				
				
				
				
		 }//close method
		 
		 
		 
		 /**
		 * Runs the backup to S3 against a specific database on the server.
		 *
		 * Runs mysqldump specifying DB name to backup all databases on the server. Calling this method will backup only the specified DB even
		 * if you have set force_backup_all_databases_flag to TRUE.
		 *
		 *
		 * @return boolean depending on if backup was successful. Returns true if everything is a-ok
		 * @throws Exception If backup failed.
		 */		 
		 public function backup_specific_databases(){
				
				$this->pre_backup_db_checks();	 		
				$this->force_backup_all_databases_flag = FALSE;
								
				try{
					$this->run_mysqldump_backup();
				}
				catch(Exception $e){
						echo 'Error: ' . $e->getMessage();
						exit;
				}
		 }
		 		 

		 
		 /**
		 * Deals with running mysqldump and calling S3 method to push to S3.
		 *
		 *
		 * @return boolean
		 * @throws Exception If mysqldump or pushing to S3 fails
		 */ 
		 private function run_mysqldump_backup(){
				if( $this->force_backup_all_databases_flag === TRUE ){
					$result = system("$this->mysqldump_path -u$this->db_username -p$this->db_password --all-databases > $this->mysqldump_filename");
				}
				else{
					if( $this->db_name == NULL || empty($this->db_name) ){
						throw new Exception('Database name cannot be left NULL or be an empty array as you haven\'t set force_backup_all_databases_flag to TRUE.');
					}
					$result = system("$this->mysqldump_path -u$this->db_username -p$this->db_password --databases ". implode(' ', $this->db_name) ." > $this->mysqldump_filename");
				}
							
				if( $result != 0 ){
					throw new Exception('mysqldump of databases failed');
				}			
				else{
					$this->push_to_s3();
				}
		 }
		 
		 
		 
		 /**
		 * Transfers mysql dump to S3 bucket
		 *
		 * @throws Exception If transfer to S3 doesn't report success
		 */
		 private function push_to_s3(){
				$s3 = new S3($this->aws_access_key, $this->aws_secret_key);
		 	
				if (!$s3->putObjectFile($this->mysqldump_filename, $this->aws_bucket_name, $this->mysqldump_filename, S3::ACL_PRIVATE)) {
					throw new Exception('There was a problem pushing your file to S3. Please check your credentials.');
				}
				else{
					@unlink($this->mysqldump_filename);
					@mail($this->email_to_notify,'Successful DB backup to S3', $this->mysqldump_filename .' was transferred to your Amazon S3 bucket ('.$this->aws_bucket_name.') successfully.');
				}
		 }
		 
		 



		/**
		 * Sets mysql dump to backup only specific databases. 
		 *
		 * @param boolean $value
		 * @throws Exception If param is not boolean
		 */ 
		public function set_db_name($array){
			try{
				if( gettype($array) != 'array' ){
					throw new Exception('Data type passed as argument must be an array. Even if you are only supplying one DB name it must be an array. Data type of value passed was "' . gettype($array) . '".' );
				}
			}
			catch(Exception $e){
					echo $e->getMessage();
					exit;
			}
			$this->db_name = $array;
		}
		
		
		
		
		
		
		
		/**
		 * Sets the protected class property to user defined val
		 *
		 */ 
		public function set_db_host($value){
			$this->db_host = $value;
		}	
		
		
		/**
		 * Sets the protected class property to user defined val
		 *
		 */ 
		public function set_mysqldump_filename($value){
			$this->mysqldump_filename = $value;
		}
		
		/**
		 * Sets the protected class property to user defined val
		 *
		 */ 
		public function set_mysqldump_path($value){
			$this->mysqldump_path = $value;
		}
		
		/**
		 * Sets the protected class property to user defined val
		 *
		 */ 
		public function set_email($value){
			$this->email_to_notify = $value;
		}
		
		
		/**
		 * Sets mysql dump to backup all availabe databases as one large SQL file. If you want to backup only specific databases then supply an array of their names to set_db_names while making sure set_force_backup_all_databases_flag is set to FALSE
		 *
		 * @param boolean $value
		 * @throws Exception If param is not boolean
		 */ 
		public function set_force_backup_all_databases_flag($value){
			try{
				if( gettype($value) != 'boolean' ){
					throw new Exception('Data type passed as argument must be boolean. Data type of value passed was "' . gettype($value) . '".' );
				}
			}
			catch(Exception $e){
					echo $e->getMessage();
					return false;
			}
			$this->force_backup_all_databases_flag = $value;
		}
		



}//close class

?>

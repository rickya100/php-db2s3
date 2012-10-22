<?php 
//Require the php_db2s3_class file
require('php_db2s3_class.php'); 
 
/*
Set up object instatiation of class php_db2s3

Constructor must be supplied with the following: 
DB USERNAME,
DB PASSWORD,
S3 ACCESS KEY
S3 SECRET KEY
S3 BUCKET NAME (THE BUCKET YOU WANT THE SQL FILE TO GO TO)
*/

$dbObj = new php_db2s3('DB USER', 'DB PASS', 'S3 ACCESS KEY', 'S3 SECRET KEY', 'BUCKET NAME');

 
//If you need to you can pass in a custom path to mysqldump. For example on a local dev env of Mac running MAMP you may need to pass in the following path. If not supplied this defaults to just 'mysqldump'
//$dbObj->set_mysqldump_path('/Applications/MAMP/Library/bin/mysqldump');


//Supply the filename you wish the resulting SQL file to have
$dbObj->set_mysqldump_filename('Server_ip_address_or_client_ref_'.date('Ymd-H:i:s').'.sql');


//If you want to backup all DBs on the server then run the method 'backup_all_databases' as shown below
//$dbObj->backup_all_databases();


//If you want to loop thorugh all databases and produce a SQL dump file for each one individually. Will create more .sql files in your S3 bucket.
$dbObj->set_db_host('localhost');
$dbObj->backup_all_databases_individually();


//Only backup a selection of DBs. Use this method to backup one or more predefined DBs
/*
$db_names = array('thirst_testdb','candc');
$dbObj->set_db_name($db_names);
$dbObj->backup_specific_databases();
*/
 
?> 
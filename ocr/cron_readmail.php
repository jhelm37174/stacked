<?php
/**
 * This page reads email from a specified users account, extracts the email attachmens and creates an entry in the tbl_ocr_txn table to start the extraction process.
 * 
 * 
 */
//Include files
//
//
//
require "./includes/cron_config.php";
require "./includes/class_database.php";
require "./includes/class_mailtools.php";

//Use statements
use dataapi\database as database; 
use mailtools\mailengine as mailengine;

//create objects as required
$dbconn = new database($mssql_servername, $mssql_username, $mssql_password, $mssql_dbname);
$mailengine = new mailengine();


include "./includes/html_header.php";
include "./includes/html_navbar.php";

//update table before running
$status = $dbconn->getSystemStatus(1); //look for status code for system is 0 -- e,g, no txn underway.
$status = $status[0]['counter'];

if($status > 0)
{
    //read mail not runing...
    echo("\n<br>--Starting mail process");
    $status = $dbconn->setSystemStatus(1,100);
    echo("\n<br><br><div class='alert alert-success'>");
    $documents = $mailengine->getAllEmailAttachments($dbconn);
    echo("</div>");
    
    $status = $dbconn->setSystemStatus(100,1);
    echo("\n<br>--Mail process complete.\n");
}
else
{
    echo("\nMail process already underway. Skipping this round.\n");
}


?>

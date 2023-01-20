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
    echo("\n<br>--Starting archive process");
    $status = $dbconn->setSystemStatus(6,600);
    echo("\n<br><br><div class='alert alert-success'>");

    $doclist = $dbconn->getInvoicesForArchive();

    //now loop and save
    foreach($doclist AS $key => $value)
    {
        $txnid = $value['txnid'];
        $docname = $value['docname'];
        $docstring = $value['docstring'];
        $imgstring = $value['imgstring'];
        $imgsize = $value['imgsize'];
        $extract = $value['extract'];
        $json = $value['json'];
        $status = $value['status'];
        $invoiceextractstatus = $value['invoiceextractstatus'];
        $docdate = $value['docdate'];

        $archve = $dbconn->setInvoiceArchive($txnid, $docname, $docstring, $imgstring, $imgsize, $extract, $json, $status, $invoiceextractstatus, $docdate);

        //now delete this record
        $delete = $dbconn->setDeleteInvoice($txnid);

        $recordarray = json_decode($json,true);


        echo("\n<br>-- Invoice Number " . $recordarray['invoice_number'] . " has been archived");


    }
    //setInvoiceArchive($txnid, $docname, $docstring, $imgstring, $imgsize, $extract, $json, $status, $invoiceextractstatus, $docdate)

    echo("</div>");
    
    $status = $dbconn->setSystemStatus(600,6);
    echo("\n<br>--Archive process complete.\n");
}
else
{
    echo("\nArchive process already underway. Skipping this round.\n");
}


?>

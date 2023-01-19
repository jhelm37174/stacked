<?php


/**
 *  This page extracts data from the images at the invoice level and line item level.
 *
 *   The first stage is to extract at the invoice level. Once we have known invoice level scoring, we can attempt the line item level and see if the results match.
 *
 * 
 */



/**
 * This page extracts the text from the prepared images and processes it
 */
//Include files
//
//

require_once  './vendor/autoload.php';

require "./includes/cron_config.php";
require "./includes/class_database.php";
require "./includes/class_extract.php";

//Use statements
use dataapi\database as database; 
use extract\extracttools as ExtractEngine;
use thiagoalessio\TesseractOCR\TesseractOCR AS TesseractOCR;


//create objects as required
$dbconn = new database($mssql_servername, $mssql_username, $mssql_password, $mssql_dbname);
$extractengine = new ExtractEngine();


include "./includes/html_header.php";
include "./includes/html_navbar.php";


echo("\n<br>--Starting OCR extract process");
echo("\n<br><br><div class='alert alert-success'>");



//This cron simply extracts all 3 rounds of extract.

//system status management
$status = $dbconn->getSystemStatus(3); //look for status code for system is 1 -- e,g, no txn underway.
$status = $status[0]['counter'];

//Retreive list of documents with status 1 - e.g. image already processed. Note that this will also set status to 99 so it does not get double processed while this is running.

$inprogressscheck = $dbconn->getTxnCountByStatus(99);
$inprogresscount = $inprogressscheck['totalcount'];

if($status > 0) //means system state is 1 and can process
{
    //Change system status to 99. Means CRON job has started.
    $status = $dbconn->setSystemStatus(3,300);

    if($inprogresscount == 0)
    {
        echo("\n<br>No transactions in progress - can start extraction");
        $doclist = $dbconn->setPendingTxn(1,99,1 );

        $doclisttable = "\n<br>List of pending documents from database:\n<br>";

        foreach($doclist AS $txn => $values)
        {
            $doclisttable .="\n<br>--" . $values['docname'] . "";

        }
        $doclisttable .="\n<br>===================================================";  
        $doclisttable .="\n<br>";        
    }    
    else
    {
        //no need to update system status - will remain in 99 from initial setSystemStatus
        echo("\n<br>" . $inprogresscount . " Transactions underway. Skipping this round");
    }


             

        if(empty($doclist) && $inprogresscount == 0)
        {
            echo("\n<br>There are no documents pending processing");
            //completion successful - update system status to 2 from 99.
            $status = $dbconn->setSystemStatus(300,3);
        }
        elseif($inprogresscount == 0)
        {
            echo($doclisttable);            

            echo("\n<br>Starting  Extraction Process:");


            foreach($doclist AS $file => $parameter)
            {
                
                //Vars required for processing
                $txnid       = $parameter['txnid'];
                //$userid      = $parameter['userid'];
                $imagestring = $parameter['imgstring'];
                $imagesize   = $parameter['imgsize'];

            
                //Create a new record in the database. Later need something in this to update or create, instead of create every time.
                //$newrecord = $dbconn->setNewExtract($txnid,$userid);
                
                
                echo("\n<br>--Processing Txn #" . $txnid);

                $start_time = microtime(true);

                //-----------------------------------------------------EXTRACT --------------------------------------------------            
                //Extract the text using OCR engine
                echo("\n<br>----Starting extract on PSM 4");
                $textpsm4 = $extractengine->getExtract($imagestring,$imagesize,4,false);
                // echo("\n<br>----Starting extract on PSM 6");
                // $textpsm6 = $extractengine->getExtract($imagestring,$imagesize,6,$live);
                // echo("\n<br>----Starting extract on PSM 3");
                // $textpsm3 = $extractengine->getExtract($imagestring,$imagesize,3,$live);

                echo("\n<br>------Saving results to database");
                $update = $dbconn->setSaveExtract($txnid,$textpsm4,1);
                // $update = $dbconn->setSaveExtract($txnid,$userid,$textpsm6,2);
                // $update = $dbconn->setSaveExtract($txnid,$userid,$textpsm3,3);

                $update = $dbconn->setTxnStatus($txnid,2);

                $end_time = microtime(true);
                $seconds = ($end_time - $start_time);

                //$update = $dbconn->setSaveTextExtractTime($txnid,$seconds);
                echo("\n<br>------Extract complete in " . $seconds);

                echo("\n<br>===================================================");

                //$updateuser = $dbconn->setSaveLastRun($userid,"lastextract");

            }
            //process complete, update status. If it fails here, system state will remain at 99
            $status = $dbconn->setSystemStatus(300,3);


        }

    //change system status back to 2 - means cron job has finised. If it crashed out during extract, it means that the system status will remain 99 and a transaction will be stuck in 99. This can be picked up by autofix.
    
}
else
{
    echo("\n<br>Extract process already underway. Skipping this round.\n<br>");
    //no need to update status - it will remain 99 from start of previous loop.
}






echo("</div>");

echo("\n<br>--OCR extract process complete.\n");
?>


 
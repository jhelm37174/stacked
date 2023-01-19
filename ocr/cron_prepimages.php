<?php

/**
 * This page prepares the extracted email attachments for OCR
 * 
 * 
 */
//Include files

require "./includes/cron_config.php";
require "./includes/class_database.php";
require "./includes/class_imagetools.php";


//Use statements
use dataapi\database as database; 
use image\imagetools as ImageEngine;

//PATH=C:/wamp64/www/connector/src/ocr/documents/:C:/wamp64/www/connector/src/ocr/cron/;

//create objects as required
$dbconn = new database($mssql_servername, $mssql_username, $mssql_password, $mssql_dbname);
$ImageEngine = new ImageEngine();



include "./includes/html_header.php";
include "./includes/html_navbar.php";



echo("\n<br>--Starting Image extract process");
echo("\n<br><br><div class='alert alert-success'>");


//Vars we need for this process:::
$status = $dbconn->getSystemStatus(2); //look for status code for system is 2 -- e,g, no image process underway.
$status = $status[0]['counter'];

//check nothing has hung on previous loop
$inprogressscheck = $dbconn->getTxnCountByStatus(98);
$inprogresscount = $inprogressscheck['totalcount'];

if($status > 0) //means system state is 1 and can process
{
    //set system status that extract is underway. All this means is that the CRON job has kicked off - it does not mean that there is not an error.
    $status = $dbconn->setSystemStatus(2,200);

    if($inprogresscount == 0)
    {
        echo("\n<br>No transactions in progress - starting...");

        $doclist = $dbconn->setPendingTxn(0,200,1); //look for status 0, change to 200, $imagesperbatch is defined in cron_config - number of records to do each time.
        //$doclist = $dbconn->getAllOCRFileList(0,0);

        $counter = 0;

        foreach($doclist AS $file => $parameter)
        {
            $start_time = microtime(true);

            $filename = $parameter['docname'];
            $filetext = $parameter['docstring'];
            $txnid    = $parameter['txnid'];


            echo("\n<br>Processing... Starting for txnid " . $txnid . " with file name " . $filename);   
            
            $result = $ImageEngine->getImage($filetext);
            //$result2 = $ImageEngine->newprocess($filetext);

            $status = $result['status'];

            echo("\n<br>--Status return code: " . $status);

            //$status == true ? $update = $dbconn->setOCRTxnStatus($txnid,1): $update = $dbconn->setOCRTxnStatus($txnid,60);
            if($status == 1)
            {
                $update = $dbconn->setTxnStatus($txnid,1);
                $update = $dbconn->setSaveImage($txnid,$result['blob'],$result['size']);
                echo("\n<br>--Images extracted for " . $txnid . "\n<br>" );
            }
            elseif($status == 52)
            {
                $update = $dbconn->setTxnStatus($txnid,52);
                echo("\n<br>--Not able to process BLOB in IMagick\n<br>" );
            }
            elseif($status == 60)
            {
                //assume too large
                $dbconn->setTxnStatus($txnid,60);
                echo("\n<br>--Not able to open BLOB for processing\n<br>" );
            }
            elseif($status == 58)
            {
                //assume too large
                $dbconn->setTxnStatus($txnid,58);
                echo("\n<br>--Image to large for OCR (Exceeds 32767 pixels)\n<br>" );
            }        
            //$update = $dbconn->setOCRTxnStatus($txnid,1);
            else
            {
                //assume too large
                //$dbconn->setOCRTxnStatus($txnid,58);
                echo("\n<br>--Unknown error\n<br>" );
            }

            //$dbconn->setOCRTxnStatus($txnid,0);
            
            //$end_time = microtime(true);
            //$seconds = ($end_time - $start_time);

            //$update = $dbconn->setSaveImageExtractTime($txnid,$seconds);

            //$updateuser = $dbconn->setSaveLastRun($userid,"lastimage");
            
            $counter ++;
        }

        if($counter == 0)
        {
            echo("\n<br>No images to process");
            //update system state
            $status = $dbconn->setSystemStatus(200,2);
        }
        else
        {
            //update system state
            $status = $dbconn->setSystemStatus(200,2);
        }
    }
    else
    {
        //no need to update system state...something already running and may time out for trackfix
        echo("\n<br><br>" . $inprogresscount . " Transactions underway. Skipping this round");
    }

    
}
else
{
    //no need to update system state
    echo("\n<br>Image process already underway. Skipping this round.\n<br>");
}



echo("</div>");

echo("\n<br>--Image extract process complete.\n");




      

// if ($counter == 0)
// {
//     echo("<p>There were no images to process</p>");
// }
// else
// {
//     echo($counter . " images processed");
// }



?>


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


//Use statements
use dataapi\database as database; 


//create objects as required
$dbconn = new database($mssql_servername, $mssql_username, $mssql_password, $mssql_dbname);



include "./includes/html_header.php";
include "./includes/html_navbar.php";

//update table before running
$status = $dbconn->getSystemStatus(4); //look for status code for system is 0 -- e,g, no txn underway.
$status = $status[0]['counter'];

if($status > 0)
{
    //read mail not runing...
    echo("\n<br>--Starting JSON invoice extractor process");
    $status = $dbconn->setSystemStatus(4,400);
    echo("\n<br><br><div class='alert alert-success'>");
    
    $text = $dbconn->getPendingExtract();
    //var_dump($text);    
    
    if(empty($text))
    {
        echo("\n<br>--no transaction to process");
    }
    else
    {
        $json = getjson($text,$dbconn);

        echo("Invoice number " . $json['invoice_number'] . " has been processed");

        //var_dump($json);
        $json = json_encode($json);

        //save json
        $savedjson = $dbconn->setSaveJson($text[0]['txnid'],$json);

        //update txn status to show json done
        $update = $dbconn->setTxnStatus($text[0]['txnid'],3);
    }



    echo("</div>");
    
    $status = $dbconn->setSystemStatus(400,4);
    echo("\n<br>--JSON process complete.\n");
}
else
{
    echo("JSON extract process already underway. Skipping this round.\n");
}





function getjson($text,$dbconnobj)
{
    //What we need:
    //
    //1. Customer account number
    //2. Invoice Date
    //3. Invoice Number
    //4. Invoice Net
    //4. Line items
    //      Total Value
    //      Description
    //      Line Tax
    //All loaded in to an array for easy validation and export

    $array = array();
    $linearray = explode("\n",$text[0]['extract']);
    //var_dump($linearray);

    //Invoice Date
            $invoicedatepattern = "/Invoice Date\h*\d{2}\/\d{2}\/\d{2}/i";
            $invoicedatestring = preg_match($invoicedatepattern, $text[0]['extract'],$invoicedatematches);
            if(isset($invoicedatematches[0]))
            {
                $invoicedatestring = $invoicedatematches[0];
                $invoicedatestring = str_replace('Invoice Date','',$invoicedatestring);
                $invoicedatestring = preg_replace("/\h*/",'',$invoicedatestring);
                $array['invoice_date'] = $invoicedatestring;                
            }
            else
            {
                $array['invoice_date'] = null;  
            }


    //Invoice Date
            $invoicenumberpattern = "/Invoice No\h*\d{0,8}/i";
            $invoicenumberstring = preg_match($invoicenumberpattern, $text[0]['extract'],$invoicenumbermatches);
            if(isset($invoicenumbermatches[0]))
            {            
                $invoicenumberstring = $invoicenumbermatches[0];
                $invoicenumberstring = str_replace('Invoice No','',$invoicenumberstring);
                $invoicenumberstring = preg_replace("/\h*/",'',$invoicenumberstring);
                $array['invoice_number'] = $invoicenumberstring;
            }
            else
            {
                $array['invoice_number'] = null;  
            }


    //Invoice Net
            //remove all junk from text 
            $strippedtext = preg_replace( '/[^A-z0-9\s.\r\n]/', '', $text[0]['extract']);
            $invoicenetpattern = "/Goods\h*\d{1,5}.\d{2}/i";
            $invoicenetstring = preg_match($invoicenetpattern, $strippedtext ,$invoicenetmatches);
            $invoicenetstring = $invoicenetmatches[0];
            $invoicenetstring = str_replace('Goods','',$invoicenetstring);
            $invoicenetstring = preg_replace("/\h*/",'',$invoicenetstring);
            $array['invoice_net'] = $invoicenetstring;
            //echo($strippedtext);

    
    //Customer Account number and Invoice Number (same line)
            //find entry which starts 'Customer Reference'
            $array['account'] = null;
            $array['customerreference'] = null;
            $titlearray = preg_grep('/^Customer Reference\h*Account.*/', $linearray);
            foreach($titlearray AS $key => $value)
            {
                $titlekey = $key;
            }
            $contentarray = $linearray[$titlekey + 1];
            //split by space, should have 4 entries
            $contentarray = preg_replace("/\h+/",' ',$contentarray);
            $contentarray = explode(" ",$contentarray);
            //now extract...should have 5 numbers, if 4, assume custer ref is missing.
            if (count($contentarray) == 4)
            {
                $array['account'] = $contentarray[0];
            }
            elseif(count($contentarray) == 5)
            {
                $array['customerreference'] = $contentarray[0];
                $array['account'] = $contentarray[1];
            }


    //now for line items...
            $linecounter = 0;
            $linenettotal = 0;
            $strippedtext = preg_replace( '/[^A-z0-9\s.\r\n]/', '', $text[0]['extract']);
            $lineitemsarray = explode("\n",$strippedtext);
            //var_dump($lineitemsarray);
            foreach($lineitemsarray AS $key => $value)
            {
                if (substr($value,0,4) == "CPC1")
                {
                    //echo("is line item");
                    //var_dump($value);
                    $thisline = $value;
                    $thisline = preg_replace("/\h+/",' ',$thisline);
                    //we only want description and line net
                    //line net first
                    $linenet = preg_match_all('/\d{1,5}.\d{1,3}/', $thisline, $thislinenumbers);
                    //var_dump($thislinenumbers);
                    $linenet = $lastnum = end($thislinenumbers[0]);
                    //description
                    $thisline = $value;
                    $thisline = preg_split('/\h{2,}/', $value);
                    $linedesc = $thisline[1];
                    //update aggregate line item total
                    $linenettotal += $linenet;
                    //update array
                    $array['line_net_total'] = $linenettotal;
                    $array['line_items'][$linecounter]['line_net'] = $linenet;
                    $array['line_items'][$linecounter]['line_desc'] = $linedesc;
                    //add line VAT
                    $linevat = round(($linenet * 0.23),2);
                    $array['line_items'][$linecounter]['line_vat'] = $linevat;
                    //update line counter
                    $linecounter ++;
                }
            }


    //validation checks
            //need to check that everyhting extracted properly and give appropriate status code.
            $invoicevalid = true;

            //check date
            if($array['invoice_date'] == null)
            {
                $invoicevalid = false;
                $txnstatuscode = 10;
                $insert = $dbconnobj->setTxnInvoiceExtractStatus($text[0]['txnid'],$txnstatuscode);
            }

            if($array['invoice_number'] == null)
            {
                $invoicevalid = false;
                $txnstatuscode = 20;
                $insert = $dbconnobj->setTxnInvoiceExtractStatus($text[0]['txnid'],$txnstatuscode);
            }

            if($array['customerreference'] == null)
            {
                $invoicevalid = false;
                $txnstatuscode = 30;
                $insert = $dbconnobj->setTxnInvoiceExtractStatus($text[0]['txnid'],$txnstatuscode);
            }
            
            if($array['account'] == null)
            {
                $invoicevalid = false;
                $txnstatuscode = 40;
                $insert = $dbconnobj->setTxnInvoiceExtractStatus($text[0]['txnid'],$txnstatuscode);
            }

            $linenettotal = number_format($linenettotal, 2, '.', '');
            $invoicenet = number_format($invoicenetstring, 2, '.', '');

            if((float)$invoicenet !== (float)$linenettotal)
            {
                $invoicevalid = false;
                $txnstatuscode = 50;
                $insert = $dbconnobj->setTxnInvoiceExtractStatus($text[0]['txnid'],$txnstatuscode);                
            }

            //check to see if has been processed before
            $duplicatecount = $dbconnobj->getDuplicateCount($invoicenumberstring);
            //var_dump($duplicatecount);
            //echo("Duplicate count is " . $duplicatecount['totalcount']);
            if($duplicatecount['totalcount'] > 0)
            {
                $invoicevalid = false;
                $txnstatuscode = 60;
                $insert = $dbconnobj->setTxnInvoiceExtractStatus($text[0]['txnid'],$txnstatuscode);                
            } 

            //if not null, add this invoice id to the records
            if($invoicenumberstring !== null && $duplicatecount['totalcount'] == 0)
            {
                $insert = $dbconnobj->setNewInvoiceNumber($invoicenumberstring); 
            }
                



            //now if true, change status
            if($invoicevalid == true)
            {
                $array['extract_status'] = 100;
                $insert = $dbconnobj->setTxnInvoiceExtractStatus($text[0]['txnid'],100); 
            }
            else
            {
                $array['extract_status'] = $txnstatuscode;
            }


    return $array;


}

?>

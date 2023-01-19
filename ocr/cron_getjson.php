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
    $json = getjson($text);

    var_dump($json);


    echo("</div>");
    
    $status = $dbconn->setSystemStatus(400,4);
    echo("\n<br>--JSON process complete.\n");
}
else
{
    echo("\JSON extract process already underway. Skipping this round.\n");
}





function getjson($text)
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
            $invoicedatestring = $invoicedatematches[0];
            $invoicedatestring = str_replace('Invoice Date','',$invoicedatestring);
            $invoicedatestring = preg_replace("/\h*/",'',$invoicedatestring);
            $array['invoice_date'] = $invoicedatestring;

    //Invoice Date
            $invoicenumberpattern = "/Invoice No\h*\d{0,8}/i";
            $invoicenumberstring = preg_match($invoicenumberpattern, $text[0]['extract'],$invoicenumbermatches);
            $invoicenumberstring = $invoicenumbermatches[0];
            $invoicenumberstring = str_replace('Invoice No','',$invoicenumberstring);
            $invoicenumberstring = preg_replace("/\h*/",'',$invoicenumberstring);
            $array['invoice_number'] = $invoicenumberstring;

    //Invoice Net
            //remove all junk from text 
            $strippedtext = preg_replace( '/[^A-z0-9\s.\r\n]/', '', $text[0]['extract']);
            $invoicenetpattern = "/Goods\h*\d{1,5}.\d{2}/i";
            $invoicenetstring = preg_match($invoicenetpattern, $strippedtext ,$invoicenetmatches);
            $invoicenetstring = $invoicenetmatches[0];
            $invoicenetstring = str_replace('Goods','',$invoicenetstring);
            $invoicenetstring = preg_replace("/\h*/",'',$invoicenetstring);
            $array['invoice_net'] = $invoicenetstring;
            echo($strippedtext);

    
    //Customer Account number and Invoice Number (same line)
            //find entry which starts 'Customer Reference'
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
            foreach($lineitemsarray AS $key => $value)
            {
                if (substr($value,0,4) == "CPC1")
                {
                    echo("is line item");
                    $thisline = $value;
                    $thisline = preg_replace("/\h+/",' ',$thisline);
                    //we only want description and line net
                    //line net first
                    $linenet = preg_match_all('/\d{1,5}.\d{2}/', $thisline, $thislinenumbers);
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
                    //update line counter
                    $linecounter ++;
                }
            }


    //validation checks
            
   

    return $array;


}

?>
        $anarray[$key][0] = 'PINV';
        $anarray[$key][1] = $customeraccountno;
        $anarray[$key][2] = '677000';
        $anarray[$key][3] = $invoicedate;
        $anarray[$key][4] = $invoicenumber;
        $anarray[$key][7] = 'S';
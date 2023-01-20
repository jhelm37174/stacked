<?php
/**
 * This page reads email from a specified users account, extracts the email attachmens and creates an entry in the tbl_ocr_txn table to start the extraction process.
 * 
 * 
 */
//Include files
//
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600);
ini_set("xdebug.var_display_max_children", '-1');
ini_set("xdebug.var_display_max_data", '-1');
ini_set("xdebug.var_display_max_depth", '-1');
//

require_once __DIR__ . '/vendor/autoload.php';
require "./includes/cron_config.php";
require "./includes/class_database.php";
require "./includes/class_mailtools.php";




//Use statements
use dataapi\database as database; 
use mailtools\mailengine as mailengine;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


//create objects as required
$dbconn     = new database($mssql_servername, $mssql_username, $mssql_password, $mssql_dbname);
$mailengine = new mailengine();
$mail       = new PHPMailer();

include "./includes/html_header.php";
include "./includes/html_navbar.php";

//update table before running
$status = $dbconn->getSystemStatus(1); //look for status code for system is 0 -- e,g, no txn underway.
$status = $status[0]['counter'];

if($status > 0)
{
    //start process
    echo("\n<br>--Starting CSV Export process");
    $status = $dbconn->setSystemStatus(5,500);
    echo("\n<br><br><div class='alert alert-success'>");
    
    //start with getting records marked with JSON complete.
    echo("\n<br><b>Starting processing of valid invoices</b><br>");
    $validinvoices = $dbconn->getValidInvoices();
    $linecounter = 0;
    $invoicecounter = 0;
    $custrefarray = array();

            //generate CSV
            foreach($validinvoices AS $key => $value)
            {
                $json = $value['json'];
                $invoicearray = json_decode($json,true);

                $invoicearraylineitems = $invoicearray['line_items'];
                $txnid = $value['txnid'];

                foreach($invoicearraylineitems AS $ikey => $ivalue)
                {

                    //customer reference one
                    $custrefarray[$linecounter][0] = 'PINV';
                    $custrefarray[$linecounter][1] = $invoicearray['customerreference'];
                    $custrefarray[$linecounter][2] = '677000';
                    $custrefarray[$linecounter][3] = $invoicearray['invoice_date'];
                    $custrefarray[$linecounter][4] = $invoicearray['invoice_number'];
                    $custrefarray[$linecounter][5] = $ivalue['line_desc'];
                    $custrefarray[$linecounter][6] = $ivalue['line_net'];
                    $custrefarray[$linecounter][7] = 'S';
                    $custrefarray[$linecounter][8] = $ivalue['line_vat'];

                    

                    $linecounter ++;

                }

                echo("\n<br>-Invoice Number " . $invoicearray['invoice_number'] . " has been processed");

                $update = $dbconn->setTxnStatus($txnid,4);

                $invoicecounter ++;

            }

            //generate csv file
            if(count($custrefarray) > 0)
            {


                    ob_start();
                    $fp = fopen('php://output', 'w');
                    foreach ($custrefarray as $key => $value)
                        {
                            fputcsv($fp, $value);
                        }
                    fclose($fp);
                    $approved_string = ob_get_contents();
                    ob_end_clean();

                    $mail->IsSMTP();
                    $mail->SMTPKeepAlive = true;
                    $mail->CharSet       = 'UTF-8';         
                    $mail->Timeout       = 600;
                    $mail->Mailer        = "smtp";
                    $mail->SMTPSecure    = "tls"; 
                    $mail->SMTPAuth      = true;  
                    $mail->Host          = "mail.smtp2go.com";   
                    $mail->Port          = 587;                 
                    $mail->Username      = $mailUsername;
                    $mail->Password      = $mailPassword;
                    $mail->isHTML(true);  
                    $mail->setFrom($mailfrom);
                    //$mail->addBcc($csv2pdf_emailbcc, 'CSV2PDF BCC');
                    $mail->AltBody = 'Please use a mail client which supports HTML';
                    $mail->addStringAttachment($approved_string, 'cust_ref_valid_invoices.csv');

                    echo("\n<br><br>--A total of " . $invoicecounter . " invoices have been approved</br>");

                    $bodymessage = "<p>Please see attached the EDI CSV for invoices received from Dygitized</p>";
                        $mail->addAddress($destinationmail, 'Dygitized Alerts'); 
                        $mail->AddCC($bccmail, 'Backup BCC');
                        $mail->Subject = $invoicecounter  . " Approved Invoices received from Dygitized";
                        $mail->Body    = $bodymessage;
                    if($sendmail == true)
                    {
                        $mail->send(); 
                    }            
                    $mail->clearAllRecipients();
                    $mail->clearAttachments();
                    $mail->SmtpClose();
            }

            //now update those records as exported.
     //start process

    
    //start with getting records marked with JSON complete.
    echo("\n<br><b>Starting processing of invalid invoices</b><br>");
    $invalidinvoices = $dbconn->getInvalidInvoices();
    $linecounter = 0;
    $invoicecounter = 0;
    $custrefarray = array();

            //generate CSV
            foreach($invalidinvoices AS $key => $value)
            {
                $json = $value['json'];
                $invoicearray = json_decode($json,true);

                $invoicearraylineitems = $invoicearray['line_items'];
                $txnid = $value['txnid'];

                foreach($invoicearraylineitems AS $ikey => $ivalue)
                {

                    //customer reference one
                    $custrefarray[$linecounter][0] = 'PINV';
                    $custrefarray[$linecounter][1] = $invoicearray['customerreference'];
                    $custrefarray[$linecounter][2] = '677000';
                    $custrefarray[$linecounter][3] = $invoicearray['invoice_date'];
                    $custrefarray[$linecounter][4] = $invoicearray['invoice_number'];
                    $custrefarray[$linecounter][5] = $ivalue['line_desc'];
                    $custrefarray[$linecounter][6] = $ivalue['line_net'];
                    $custrefarray[$linecounter][7] = 'S';
                    $custrefarray[$linecounter][8] = $ivalue['line_vat'];                    

                    $linecounter ++;

                }

                echo("\n<br>-Invoice Number " . $invoicearray['invoice_number'] . " has been processed");

                $update = $dbconn->setTxnStatus($txnid,4);

                $invoicecounter ++;

            }

            //generate csv file
            if(count($custrefarray) > 0)
            {


                    ob_start();
                    $fp = fopen('php://output', 'w');
                    foreach ($custrefarray as $key => $value)
                        {
                            fputcsv($fp, $value);
                        }
                    fclose($fp);
                    $approved_string = ob_get_contents();
                    ob_end_clean();

                    $mail->IsSMTP();
                    $mail->SMTPKeepAlive = true;
                    $mail->CharSet       = 'UTF-8';         
                    $mail->Timeout       = 600;
                    $mail->Mailer        = "smtp";
                    $mail->SMTPSecure    = "tls"; 
                    $mail->SMTPAuth      = true;  
                    $mail->Host          = "mail.smtp2go.com";   
                    $mail->Port          = 587;                 
                    $mail->Username      = $mailUsername;
                    $mail->Password      = $mailPassword;
                    $mail->isHTML(true);  
                    $mail->setFrom($mailfrom);
                    //$mail->addBcc($csv2pdf_emailbcc, 'CSV2PDF BCC');
                    $mail->AltBody = 'Please use a mail client which supports HTML';
                    $mail->addStringAttachment($approved_string, 'cust_ref_invalid_invoices.csv');

                    echo("\n<br><br>--A total of " . $invoicecounter . " invoices have been REJECTED</br>");

                    $bodymessage = "<p>Please see attached the EDI CSV for REJECTED invoices received from Dygitized</p>";
                        $mail->addAddress($destinationmail, 'Dygitized Alerts'); 
                        $mail->AddCC($bccmail, 'Backup BCC');
                        $mail->Subject = $invoicecounter  . " rejected Invoices received from Dygitized";
                        $mail->Body    = $bodymessage;
                    if($sendmail == true)
                    {
                        $mail->send(); 
                    }            
                    $mail->clearAllRecipients();
                    $mail->clearAttachments();
                    $mail->SmtpClose();           
            }




    echo("</div>");
    
    $status = $dbconn->setSystemStatus(500,5);
    echo("\n<br>--CSV Export process complete.\n");
}
else
{
    echo("\nCSV Export process already underway. Skipping this round.\n");
}


?>


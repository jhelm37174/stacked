<?php

/**
 * 
 */


//detect page script
$metatime         = 10000;
$script =  basename($_SERVER['PHP_SELF']);
if($script == "cron_readmail.php")
{
    $metatime = 10;
}
elseif($script == "cron_prepimages.php")
{
    $metatime = 10;
}
elseif($script == "cron_extract.php")
{
    $metatime = 10;
}
elseif($script == "cron_getjson.php")
{
    $metatime = 10;
}
elseif($script == "cron_sendcsv.php")
{
    $metatime = 600;
}
elseif($script == "cron_archive.php")
{
    $metatime = 600;
}
else
{
    $metatime = 10;
}

            //VPS Server
                $mssql_servername = "88.202.190.13";
                $mssql_username   = "dygityze_dbuser";
                $mssql_password   = "x95;@$]{CUi2";
                $mssql_dbname     = "dygityze_docuscan"; 

                                                   

            //Dev
                $mssql_servername = "localhost";
                $mssql_username   = "root";
                $mssql_password   = "";
                $mssql_dbname     = "stacked";
                $basepath         = 'http://localhost/stacked/ocr/';
                $metarefresh      = true;
                $sendmail         = true;



                $mailUsername      = "Stacked" ; //cbautomation.co.uk
                $mailPassword      = "OXhxNnV3MDVpbGQw";  //qmmOEIR3TX0B

                //for live
                $destinationmail  = "vanessa.stynes@stacked.ie";
                $bccmail          = "gavin.byrne@stacked.ie";
                $failmail         = "gavin.byrne@stacked.ie";
                $mailfrom         = "autoinvoice@stacked.com";


                //testing
                $destinationmail  = "Gavinp.byrne1@gmail.com";
                $destinationmail  = "jamiehelm@hotmail.com";
                $bccmail          = "jamiehelm@hotmail.com";
                $failmail         = "jamiehelm@hotmail.com";
                $mailfrom         = "autoinvoice@stacked.com";
?>
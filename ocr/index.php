<?php
/**
 * This page reads email from a specified users account, extracts the email attachmens and creates an entry in the tbl_ocr_txn table to start the extraction process.
 * 
 * 
 */
//Include files
//

//

require_once __DIR__ . '/vendor/autoload.php';
require "./includes/cron_config.php";
require "./includes/class_database.php";





//Use statements
use dataapi\database as database; 


//create objects as required
$dbconn     = new database($mssql_servername, $mssql_username, $mssql_password, $mssql_dbname);


include "./includes/html_header.php";
include "./includes/html_navbar.php";


//get list of files in process from tbl_txn
if(isset($_GET["pageNumber"]))
{
  $pagenumber = clean_input($_GET["pageNumber"]);//add cleaner santizier
}
else
{
  $pagenumber = 1;
}
$resultsperpage = 20;

$doclist = $dbconn->getAllTxn();
$archivelistcount = $dbconn->getAllArchiveCount();
$archivelist = $dbconn->getAllArchive($pagenumber,$resultsperpage);



$totalcount = $archivelistcount[0][0];

//
//
//
//get list of files already processed:
//
//



?>

<div class='container'>
<div class='row'>


<div class='col-sm-2'>
</div>


<div class='col-sm-8'>
    <h2>Documents pending processing</h2>

    <table class='table table-sm table-striped'>
        <tr>
            <td>Txnid</td>
            <td>Doc Name</td>
            <td>Txn status</td>
            <td>Doc Status</td>
        </tr>

    <?php  
        foreach($doclist AS $key => $value)
        {
            $txnid   = $value['txnid'];
            $docname = $value['docname'];
            $status  = $value['invoiceextractstatus'];
            $txnstatus = $value['status'];

             if($status == 10)
                {
                    $statusstring = " 'No Invoice Date found'";
                }
                elseif($status == 20)
                {
                    $statusstring = " 'No Invoice Number found'";
                }
                elseif($status == 30)
                {
                    $statusstring = " 'No customer reference found'";
                }   
                elseif($status == 40)
                {
                    $statusstring = " 'No account number found'";
                }   
                elseif($status == 50)
                {
                    $statusstring = " 'Invoice Net and Line Net does not equate'";
                }   
                elseif($status == 60)
                {
                    $statusstring = " 'Duplicate Invoice Number'";
                }
                elseif($status == 100)
                {
                    $statusstring = " 'Succesful export'";
                } 
                else
                {
                    $statusstring = " 'Not Started'";
                }                      


            echo("<tr>");
            echo("<td>" . $txnid . "</td>");
            echo("<td>" . $docname . "</td>");
            echo("<td>" . $txnstatus . "</td>");
            echo("<td>" . $statusstring . "</td>");
            echo("</tr>"); 
        }
        //var_dump($doclist);

    ?>
    </table>

    <h2>Documents Exported</h2>


    <table class='table table-sm table-striped'>
        <tr>
            <td>Txnid</td>
            <td>Doc Name</td>
            <td>Doc Status</td>
        </tr>
    <?php 



        //var_dump($archivelist);
        foreach($archivelist AS $key => $value)
        {
            $txnid   = $value['txnid'];
            $docname = $value['docname'];
            $status  = $value['invoiceextractstatus'];

             if($status == 10)
                {
                    $statusstring = " 'No Invoice Date found'";
                }
                elseif($status == 20)
                {
                    $statusstring = " 'No Invoice Number found'";
                }
                elseif($status == 30)
                {
                    $statusstring = " 'No customer reference found'";
                }   
                elseif($status == 40)
                {
                    $statusstring = " 'No account number found'";
                }   
                elseif($status == 50)
                {
                    $statusstring = " 'Invoice Net and Line Net does not equate'";
                }   
                elseif($status == 60)
                {
                    $statusstring = " 'Duplicate Invoice Number'";
                }
                elseif($status == 100)
                {
                    $statusstring = " 'Succesful export'";
                }                       


            echo("<tr>");
            echo("<td>" . $txnid . "</td>");
            echo("<td>" . $docname . "</td>");
            echo("<td>" . $statusstring . "</td>");
            echo("</tr>");
        }

    ?>
    </table>
</div>

<div class='col-sm-2'>
</div>

</div>



<div class='row'>
    <div class='col-sm-6 text-left'></div>
    <div class='col-sm-5 text-right float-right'>
       <?php
      //page numbering time
      $href='index.php';
      $pagelink = pagination($totalcount, $href,$resultsperpage);
      echo($pagelink);
      ?>         
    </div>  
    <div class='col-sm-1 text-left'></div> 
</div> 


</div>




<?php


function pagination($count, $href, $resultsperpage) {
$output = '<table class="table table-responsive m-0 pagenumbers"><tr>';
//if pagenumber is not in querystring, set page number = 1
if(!isset($_REQUEST["pageNumber"])) $_REQUEST["pageNumber"] = 1;

//get max page number
$pages  = ceil($count/$resultsperpage);

//if pages exists after loop's lower limit
//case when more than 3 pages - e.g min 4 pages
if($pages>1) 
{
    if(($_REQUEST["pageNumber"]-3)>0) //E.g if page 10 will start with a link to page 1 with green class coloring
    {
    $output = $output . '<td class="btn-success"><a href="' . $href . '?pageNumber=1" class="btn-success">1</a></td>';
    }
    if(($_REQUEST["pageNumber"]-3)>1) //E.g. 
    {
    $output = $output . '<td>...</td>';
    }

    //Loop for provides links for 2 pages before and after current page
    for($i=($_REQUEST["pageNumber"]-2); $i<=($_REQUEST["pageNumber"]+2); $i++)  
    {
      if($i<1) continue;
      if($i>$pages) break;
      if($_REQUEST["pageNumber"] == $i)
      {
        $output = $output . '<td class="">'.$i.'</span></td>';
      }
      else   
      {
       $output = $output . '<td class="btn-primary"><a href="' . $href . "?pageNumber=".$i . '" class="btn-primary"> '.$i.' </a></td>'; 
     }    
    }

//if pages exists after loop's upper limit
    if(($pages-($_REQUEST["pageNumber"]+2))>1) 
    {
    $output = $output . '<td>...</td>';
    }
    if(($pages-($_REQUEST["pageNumber"]+2))>0) 
    {
      if($_REQUEST["pageNumber"] == $pages)
      {
        $output = $output . '<td>' . ($pages) .'</span></td>';
      }
      else  
      {
        $output = $output . '<td class="btn-success"><a href="' . $href .  "?pageNumber=" .($pages) .'" class="btn-success">' . ($pages) .' </a></td> ';
      }      
      
    }
}
$output.='</table>';
return $output;
}


    function clean_input($data) 
      {
          $data = trim($data);
          $data = stripslashes($data);
          $data = htmlspecialchars($data);
          return $data;
       }


?>

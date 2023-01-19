<?php 



//echo("\n----Opening file<br>");
            $filename = 'email.html';
            $fp   = fopen($filename,"r");
            $contents = fread($fp, filesize($filename));

            //find the table that contains the keyword "SKU"
            //


            $contents = str_replace("\r\n", "", $contents);

            //work back to find the table start point
            $SKUtablestarts = strpos($contents,'<table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="700" style="width:525.0pt"><thead><tr><td width="15" style="width:11.25pt;border:solid windowtext 1.0pt;padding:3.0pt 3.0pt 3.0pt 3.0pt"><p class="MsoNormal" align="center" style="text-align:center"><b><span style="font-size:9.0pt">SKU<o:p></o:p></span></b></p>');

            

            $contents = substr($contents, $SKUtablestarts);
            // echo("Table:<br>");
            // echo($contents);
            // echo("<hr>");

            //now find the end...
            $SKUtablends = strpos($contents, "</table>");

            //length calculation
            $tablelength = $SKUtablends - $SKUtablestarts;
            //echo("<br>SKU Table Located at " . $SKUtablestarts);
            //echo("<br>table length is " . $tablelength);

           	//now get that table out....
           	$contents = substr($contents, 0, $SKUtablends);

            echo($contents);

            //now split by <tr>, then <td>
            $trarray = explode("<tr",$contents);

            $varsarray = array();

            //var_dump($array);
            //
            //for teting output
            //echo("<table>");

            foreach($trarray AS $trkey => $trvalue)
            {
            	
            	

            	//echo("<tr>");

            	$tdarray = explode("<td",$trvalue);        	

            	foreach($tdarray AS $tdkey => $tdvalue)
            	{
            		
            		$skipline = false;
            		$fulltd = "<td" . $tdvalue;

            		//var_dump($fulltd);
            		
            		$cellvalue = preg_replace("/<[^>]*>/",	"", $fulltd);
            		$cellvalue == "SKU" ? $skipline = true: null;
            		$cellvalue == "Totals" ? $skipline = true: null;
            		$cellvalue == "Delivery" ? $skipline = true: null;
            		$cellvalue == "Grand Total" ? $skipline = true: null;

            		if($skipline == true)
            		{
            			break;
            		}
            		else     		

            		{
	            		$varsarray[$trkey][$tdkey] = $cellvalue;
	            		// echo("<td>");
	            		// echo($cellvalue);
	            		// echo("</td>");            			
            		}


            	}

            	//echo("</tr>");
            	

            	// var_dump($value);
            	// //now remove anything in tags
            	// $cellvalue = preg_replace("/<[^>]*>/",	"|", $value);
            	// var_dump($cellvalue);
            	
            }
         	
         	//echo("</table>");

         	//var_dump($varsarray);

         	//output required fields
         	foreach ($varsarray as $key => $value) 
         	{
         		// code...
         		if(count($value) > 3)
         		{
	         		$sku = $value[1];
	         		$qty = $value[4];
	         		$unit = $value[5];  
	         		echo("\n<br>" . $sku . " - " . $qty . " - " . $unit . "<br>");       			
         		}

         		
         	}


?>


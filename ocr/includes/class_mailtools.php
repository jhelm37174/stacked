<?php
/**
 * 
 */



namespace mailtools;



/**
 * This class handles all of the interfaces for OCR image extraction and processing.
 */
class mailengine
{
    /**
	 * Used by CRON job to read all user profiles, extract mail box information, parse any emails and download attachements and move mail to processed inbox.
	 * @return int The record id.
	 */
	public function getAllEmailAttachments($dbconn)
	{ //class open

        //use the database connection to get the email address
        //$userconfig = $dbconn->getAllUsers();

        $mailaddress = "xerox@dygitized.com";
        $mailpassword = "QUT!PH#LQfb3gzF";
        $mailport = 993;
        $mailserver = "mail.uk2.net";
        $emailssl = true;

        //setup variables used for output.
        $attachmentcounter = 0;
        $returnarray = array();

        //loop through each users inbox.
        

            //connect to server via IMAP
            $mailbox    = imap_open("{".$mailserver.":".$mailport."/imap/ssl/novalidate-cert}INBOX", $mailaddress, $mailpassword);
            if($mailbox == false)
            {
                echo("\n<br>----<br>----Error connecting to mailbox");
                return false;
            }

            $emails     = imap_search($mailbox,'ALL'); 

            $inboxcount = imap_num_msg($mailbox);

            echo("\n<br>----Processing mail\n<br>");
            echo("\n<br>-----Connecting to " . $mailserver . " port " . $mailport . " for ".$mailaddress."\n<br>");

            if(!$emails)//if inbox empty return nothing
            {
                $returnarray = ['No messages'];
                echo("\n<br>------ No messages\n<br>");

            }
            else
            {
                echo("\n<br>------ There are messages \n<br>");
                $mailcounter = 0;
                //verify processed folder exists
                $processedfolder = imap_utf7_encode('Processed');
                $failfolder =  imap_utf7_encode('Failed');
                $connstring = "{".$mailserver.":".$mailport."/imap/ssl/novalidate-cert}INBOX.";
                $folderlist = imap_list($mailbox, $connstring, "*");



                //if does not exist in array, crate processed folder (assumes folderlist is an array - some errors on this.
                if(is_array($folderlist))
                {
                    if (!in_array($connstring . $processedfolder, $folderlist))
                    {
                       
                        $result = imap_createmailbox($mailbox, $connstring.$processedfolder);

                        echo("\n<br>------ Created 'Processed' mailbox with result ".$result."\n<br>");
                    }
                    else
                    {
                        echo("\n<br>------ 'Processed' mailbox exists \n<br>----");
                    }    

                    if (!in_array($connstring . $failfolder, $folderlist))
                    {
                        imap_createmailbox($mailbox, $connstring.$failfolder);
                        echo("\n<br>------ Created 'Failed' mailbox \n<br>");
                    }
                    else
                    {
                        echo("\n<br>------ 'Failed' mailbox exists \n<br>");
                    }                    
                } 
                else //assume nothing returned and force creation
                {
                    $result = imap_createmailbox($mailbox, $connstring.$processedfolder);
                    echo("\n<br>------ Created 'Processed' mailbox  with result ".$result."\n<br>");
                    $result = imap_createmailbox($mailbox, $connstring.$failfolder);
                    echo("\n<br>------ Created 'Failed' mailbox\n<br>");
                }           


            

                $totalmailcount = count($emails);
                //loop out for emails
                foreach($emails as $email)
                {
                    
                    //retreive header so that we can see flag status - U,F etc. Returned as an object.
                    $header = imap_header($mailbox,$email);
                    $structure = imap_fetchstructure($mailbox, $email, 0);
                    //var_dump($structure->parts);

                    //extract required header information.
                    $readstatus = $header->Unseen; //not strictly needed.
                    $maildate   = $header->MailDate; //there are actually 3 dates in the header.
                    $mailfrom   = $header->fromaddress;
                    $mailsubject = $header->subject;

                    //itterate and extract the body -> parts -> structure
                    echo("\n<br>-------- Processing mail # " . $email . " of ".$totalmailcount." \n<br>");
                    //save attachment and record in database for processing
                    //
                    ///* get information specific to this email */
                            $overview = imap_fetch_overview($mailbox,$email,0);
                            $message = imap_fetchbody($mailbox,$email,2);


                            /* get mail structure */
                            $structure = imap_fetchstructure($mailbox, $email);
                            $attachments = array();
                            


                            /* if any attachments found...as parts in structure */
                            //if(isset($structure->parts) && count($structure->parts)) 
                            if(isset($structure->parts) && count($structure->parts)) 
                            {
                                echo("------ Attachment found on mail # " . $email . "\n<br>");
                                $downloadable = false;
                                for($i = 0; $i < count($structure->parts); $i++) 
                                {
                                    $attachments[$i] = array(
                                        'is_attachment' => false,
                                        'filename' => '',
                                        'name' => '',
                                        'attachment' => ''
                                    );

                                    if($structure->parts[$i]->ifdparameters) 
                                    {
                                        foreach($structure->parts[$i]->dparameters as $object) 
                                        {
                                            if(strtolower($object->attribute) == 'filename') 
                                            {
                                                $attachments[$i]['is_attachment'] = true;
                                                //$attachments[$i]['filename'] = $object->value;

                                                $tempfilename = $object->value;

                                                if(substr($tempfilename,0,8) == "=?utf-8?")//e.g. =?utf-8?B?aW1nMDUwODIwMjJfMDAwOC5wZGY=?=
                                                {
                                                    $filenamearray = explode('?', $tempfilename);
                                                    $tempfilename = base64_decode($filenamearray[3]);

                                                }

                                                //echo("\n<br>----<br>------Filename extracted as " . $tempfilename);
                                                $attachments[$i]['filename'] = $tempfilename;
                                            }
                                        }
                                    }

                                    if($structure->parts[$i]->ifparameters) 
                                    {
                                        foreach($structure->parts[$i]->parameters as $object) 
                                        {
                                            if(strtolower($object->attribute) == 'name') 
                                            {
                                                $attachments[$i]['is_attachment'] = true;
                                                $attachments[$i]['name'] = $object->value;
                                            }
                                        }
                                    }


                                    if($attachments[$i]['is_attachment']) 
                                    {                                       
                                        $attachments[$i]['attachment'] = imap_fetchbody($mailbox, $email, $i+1);

                                        /* 3 = BASE64 encoding */
                                        if($structure->parts[$i]->encoding == 3) 
                                        { 
                                            $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                                        }
                                        /* 4 = QUOTED-PRINTABLE encoding */
                                        elseif($structure->parts[$i]->encoding == 4) 
                                        { 
                                            $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                                        }
                                    }
                                }
                            }

                            if(isset($structure->dparameters) && ($structure->disposition == "attachment"))
                            {
                                echo("\n<br>---------- Attachment found on mail # " . $email . " but cannot download.\n<br>");
                                $downloadable = false;
                            } 
                                                        


                            /* iterate through each attachment and save it */
                            foreach($attachments as $attachment)
                            {
                                if($attachment['is_attachment'] == 1)
                                {
                                    //get the new filename from the database -- 
                                    //
                                    $filename = $attachment['name'];
                                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                                    echo("\n<br>---------- Attached file name is " . $filename);                             
                                    

                                    //echo("Ext is " . $ext);
                                    //only save PDF format 
                                    if($ext == "PDF" || $ext == "Pdf" || $ext == "pdf")
                                    {
                                            
                                            //var_dump($attachment);
                                            //$savefilename = $userid . "_" . $newid . "_" . $session."." . $ext;

                                            if(empty($filename)) $filename = $attachment['filename'];

                                            if(empty($filename)) $filename = time() . ".pdf";

                                            $filesize = strlen($attachment['attachment']);

                                            $newid = $dbconn->setSaveNewDocument($filename,$attachment['attachment']);
                                            if($filesize < 10485760 && $filesize > 0)
                                            {
                                                
                                                echo("\n<br>---------- PDF Attachment saved as BLOB\n<br>");
                                                $dbconn->setTxnStatus($newid,0); //0 is default on table anyway, but in case someone changes it.
                                            }
                                            elseif($filesize == 0)
                                            {
                                                $dbconn->setTxnStatus($newid,58); //0 is default on table anyway, but in case someone changes it.
                                                echo("\n<br>---------- PDF Attachment '" . $filename . "' reported as 0 bytes\n<br>");
                                            }
                                            else
                                            {
                                                //file is too large - update database to prevent processing.
                                                $dbconn->setTxnStatus($newid,60);
                                                echo("\n<br>---------- PDF Attachment saved as BLOB, but file too large and marked too large\n<br>");
                                            }

                                            //echo("\n<br>----<br>------ PDF Attachment saved as BLOB\n<br>----");
                                            // $newid = $dbconn->setOCRFileName($newid,$savefilename,$attachment['name']); 
                                            $attachmentcounter ++;
                                            $returnarray[$attachmentcounter] = $filename;
                                            $downloadable = true;  
                                    }
                                    
                                    else
                                    {
                                        $downloadable == false;
                                        echo("\n<br>---------- File format not supported. Mail will be sent to failed folder");
                                    }


                                    //only save PDF format 
                                    // if($ext == "jpg" || $ext == "JPG" || $ext == "Jpg")
                                    // {
                                            
                                    //         //var_dump($attachment);
                                    //         //$savefilename = $userid . "_" . $newid . "_" . $session."." . $ext;

                                    //         if(empty($filename)) $filename = $attachment['filename'];

                                    //         if(empty($filename)) $filename = time() . ".jpg";

                                    //         $filesize = strlen($attachment['attachment']);

                                    //         $newid = $dbconn->setSaveNewDocument($userid,$filename,$attachment['attachment']);
                                    //         if($filesize < 2097152 && $filesize > 0)
                                    //         {
                                                
                                    //             echo("\n<br>----<br>------ JPG Attachment saved as BLOB\n<br>----");
                                    //         }
                                    //         elseif($filesize == 0)
                                    //         {
                                    //             $dbconn->setOCRTxnStatus($newid,58); //0 is default on table anyway, but in case someone changes it.
                                    //             echo("\n<br>----<br>------ JPG Attachment '" . $filename . "' reported as 0 bytes\n<br>----");
                                    //         }
                                    //         else
                                    //         {
                                    //             //file is too large - update database to prevent processing.
                                    //             $dbconn->setOCRTxnStatus($newid,60);
                                    //             echo("\n<br>----<br>------ JPG Attachment saved as BLOB, but file too large and marked too large\n<br>----");
                                    //         }

                                    //         //echo("\n<br>----<br>------ PDF Attachment saved as BLOB\n<br>----");
                                    //         // $newid = $dbconn->setOCRFileName($newid,$savefilename,$attachment['name']); 
                                    //         $attachmentcounter ++;
                                    //         $returnarray[$userid][$attachmentcounter] = $filename;
                                    //         $downloadable = true;  
                                    // }
                                    // else
                                    // {
                                    //     $downloadable == false;
                                    // }

                                    // if($ext == "jpg" || $ext == "JPG" || $ext == "Jpg")
                                    // {
                                    //         $newid = $dbconn->setNewOCRTxn($userid);
                                    //         $savefilename = $userid . "_" . $newid . "_" . $session ."." . $ext;

                                    //         if(empty($filename)) $filename = $attachment['filename'];

                                    //         if(empty($filename)) $filename = time() . ".dat";
                                    //         $folder = "documents";
                                    //         if(!is_dir($folder))
                                    //         {
                                    //              mkdir($folder);
                                    //         }
                                    //         $fp = fopen("./". $folder ."/" . $savefilename, "w+");
                                    //         fwrite($fp, $attachment['attachment']);
                                    //         fclose($fp);
                                    //         $newid = $dbconn->setOCRFileName($newid,$savefilename,$attachment['name']); 
                                    //         $attachmentcounter ++;
                                    //         $returnarray[$userid][$attachmentcounter] = $filename;  
                                    // }
                                    //filename is userid_newid_sessionrandstring.extension                              
                                }                          
                            }
                            
                    if($downloadable == true)
                    {
                        echo("\n<br>---------- Attempting to move mail number ".$email." to ".$processedfolder."\n<br>");
                        $imapresult=imap_mail_move($mailbox,$email,"INBOX." . $processedfolder);
                    }
                    else
                    {
                        echo("\n<br>---------- Attempting to move mail number ".$email." to ".$failfolder."\n<br>");
                        $imapresult=imap_mail_move($mailbox,$email,"INBOX." . $failfolder);
                        //var_dump($imapresult);
                        //$record = $dbconn->setFailMessageLog($userid,$mailfrom,$mailsubject,$maildate);
                        //var_dump($record);
                    }
                                
                }//close foreach($emails as $email)


            }//close if(!$emails) else loop

            //all mails processed for this user and marked for move.
            imap_close($mailbox,CL_EXPUNGE); 
            unset($mailbox);


       return $returnarray; //list of documents processed array =? userid => array(counter,filename)

	} //class close

    
}//close class
?>
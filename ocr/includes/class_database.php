<?php
/**
 * Database Class for CBA Connector
 * 
 * This class contains all methods for interacting with the database for every product. All SQL statements are here.
 * 
 * @category    Database
 * @author      Jamie Helm
 * 
 */
namespace dataapi;

use mysqli;

Class database
{

    /**
     * Database connection used to pass queries with methods.
     * @var obj $connection
     */
    protected $connection;


 
//Initiate and destroy method

        /**
         * Database constructor used to intiate database connection
         * 
         * Constructor class used within pages to initiate the persistant database connection to MySQL.
         * 
         * @author Jamie Helm
         * @category database
         * 
         * @param string $servername IP address of the server, e.g. 192.168.1.1
         * @param string $username The DB user name.
         * @param string $password The assocaiated users password.
         * @param string $dbname The name of the connector database.
         * 
         * @return object  private DB object
         */
        public function __construct($servername, $username, $password, $dbname)
        {         
            $this->connection = new mysqli($servername, $username, $password, $dbname);

            if ($this->connection->connect_error) 
            {
                $this->error('Failed to connect to MySQL - ' . $this->connection->connect_error);
            }
        }
        
        /**
         * Method to destroy connection oject.
         * 
         * Class method used to destroy the database connection.
         * 
         * @author Jamie Helm
         * @category database
         * @api closedb
         * 
         * @param $db the database connection object
         * 
         * @return bool true or false
         */
        public function closedb($database)
        {
            return $database->connection->close();
        }





        /**
         * Used to set system state when a cron job starts. Cron job submits current state, e.g. 0 and new state, e.g. 96. The codes are defined in the getSystemStatus method.
         * @param int $currentstatus The current status code to change
         * @param int $newsatus      The new status code to be applied
         */
        public function setSystemStatus($currentstatus,$newsatus)
        {
            $timenow = time();
            $stmt = $this->connection->prepare(" UPDATE tbl_status SET lastrun = ? , statuscode = ? WHERE statuscode = ? ");
            $stmt->bind_param("sss", $timenow, $newsatus,$currentstatus);
            $stmt->execute();           
            return true;                 
        }


        /**
         * Creates a new record in the txn table, and then uses the returned new txnid to create a new record in the documents table and store the associated attachment received via email.
         * @param int $userid    User ID from the database
         * @param string $filename  The original file name from the email
         * @param string $docstring The PDF document as a string, stored as a BLOB in the database
         */
        public function setSaveNewDocument($docname,$docstring)
        {
            //Create transaction record first
            $stmt = $this->connection->prepare( " INSERT INTO tbl_txn (docname,docstring) VALUES (?,?)");           
            $stmt->bind_param("ss", $docname,$docstring);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            return $rowid;  
        }

        /**
         * Set the txn status for a specific txnid. For example, on creating a record, we set status 0. On deleting a record we set status 4. This is also called within this class by other methods, for example, setPendingDocuments.
         * @param int $txnid      The txn id
         * @param int $statuscode The updated status code to apply
         */
        public function setTxnStatus($txnid,$statuscode)
        {
            $stmt = $this->connection->prepare("UPDATE tbl_txn SET status = ? WHERE txnid = ?");           
            $stmt->bind_param("ss", $statuscode,$txnid);
            $stmt->execute();
            return true;
        }

        /**
         * Set the invoice extract status code, used when bulk exporting for email.
         */
        public function setTxnInvoiceExtractStatus($txnid,$statuscode)
        {
            $stmt = $this->connection->prepare("UPDATE tbl_txn SET invoiceextractstatus = ? WHERE txnid = ?");           
            $stmt->bind_param("ss", $statuscode,$txnid);
            $stmt->execute();
            return true;
        }

        public function getTxnCountByStatus($status)
        {
            $stmt = $this->connection->prepare(" SELECT count(txnid) AS totalcount FROM tbl_txn WHERE status = ?  ");
            $stmt->bind_param("s", $status);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
        }


        /**
         * Used to retreive a list of documents for processing - applies queue method to prioritize documents.
         * @param [type] $searchstatus [description]
         * @param [type] $newstatus    [description]
         * @param [type] $limit        [description]
         */
        public function setPendingTxn($searchstatus,$newstatus,$limit)
        {
 

            $stmt = $this->connection->prepare(" SELECT A.txnid, A.docname, A.docstring, A.imgstring, A.imgsize FROM tbl_txn AS A  WHERE A.status = ?   ORDER BY A.txnid ASC LIMIT 0,?");          
            $stmt->bind_param("ss", $searchstatus,$limit);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            foreach($result AS $key => $value)
            {
                $txnid = $value['txnid'];
                $this->setTxnStatus($txnid,$newstatus);
            }            
            return $result;   
        }

        public function setSaveImage($txnid,$imagestring,$imagesize)
        {
            $stmt = $this->connection->prepare(" UPDATE tbl_txn SET imgstring = ?, imgsize = ? WHERE txnid = ? ");
            $stmt->bind_param("sss", $imagestring,$imagesize,$txnid);
            $stmt->execute();  
            //var_dump($stmt);          
            $rowid = $stmt->affected_rows;
            return 1;              
        }


        public function setSaveExtract($txnid,$text)
        {
            $stmt = $this->connection->prepare(" UPDATE tbl_txn SET extract = ?  WHERE txnid = ? ");           
            $stmt->bind_param("ss", $text,$txnid);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            return $rowid; 
        }


        public function setSaveJson($txnid,$json)
        {
            $stmt = $this->connection->prepare(" UPDATE tbl_txn SET json = ?  WHERE txnid = ? ");           
            $stmt->bind_param("ss", $json,$txnid);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            return $rowid; 
        }

        public function getPendingExtract()
        {
            $stmt = $this->connection->prepare(" SELECT txnid,extract FROM tbl_txn WHERE status = 2 ORDER BY txnid ASC LIMIT 0,1");           
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;
        }  


        public function getDuplicateCount($invoiceno)
        {
            $stmt = $this->connection->prepare(" SELECT count(invoiceid) AS totalcount FROM tbl_invoicenumbers WHERE invoicenumber LIKE ?  ");
            $stmt->bind_param("s", $invoiceno);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
        }     


        public function setNewInvoiceNumber($invoiceno)
        {
            //Create transaction record first
            $stmt = $this->connection->prepare( " INSERT INTO tbl_invoicenumbers (invoicenumber) VALUES (?)");           
            $stmt->bind_param("s", $invoiceno);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            return $rowid;  
        }

        public function getValidInvoices()
        {
            $stmt = $this->connection->prepare(" SELECT txnid,json FROM tbl_txn WHERE status = 3 AND invoiceextractstatus = 100 ORDER BY txnid ASC ");           
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;            
        }


        public function getInvalidInvoices()
        {
            $stmt = $this->connection->prepare(" SELECT txnid,json FROM tbl_txn WHERE status = 3 AND invoiceextractstatus != 100 ORDER BY txnid ASC ");           
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;            
        }

        public function getInvoicesForArchive()
        {
            $stmt = $this->connection->prepare(" SELECT * FROM tbl_txn WHERE status = 4 ORDER BY txnid ASC ");           
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;              
        }

        public function setInvoiceArchive($txnid, $docname, $docstring, $imgstring, $imgsize, $extract, $json, $status, $invoiceextractstatus, $docdate)
        {
            $stmt = $this->connection->prepare( " INSERT INTO tbl_archive (txnid, docname, docstring, imgstring, imgsize, extract, json, status, invoiceextractstatus, docdate)  VALUES (?,?,?,?,?,?,?,?,?,?)");           
            $stmt->bind_param("ssssssssss", $txnid, $docname, $docstring, $imgstring, $imgsize, $extract, $json, $status, $invoiceextractstatus, $docdate);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            return $rowid;            
        }

        public function setDeleteInvoice($txnid)
        {
            $stmt = $this->connection->prepare("DELETE FROM tbl_txn WHERE txnid = ? ");
            $stmt->bind_param("s",$txnid);
            $stmt->execute();
            $result = $stmt->get_result(); 
        }














//All front end related features

    //Login SQL Queries    
        /**
         * validates a user credentails and logs the access in the database.
         * @param  string $username the username (email address) used to login
         * @param  string $password the users password
         * @return bool           trur or false
         */
        public function getUserValidation($username,$password)
        {
            //step 1: return associated row
            $stmt = $this->connection->prepare("SELECT * FROM tbl_users WHERE username=? AND isactive=1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            //var_dump($result);

            //step 2: validate user exists, log attempt (even if fails)
            $userid=0;
            $status = 0;
            $hashpassword = $result['userpassword'];            
            if(isset($result['userid']))
            {
                $userid = $result['userid'];
                $status = 1;
            }            
            $stmt2 = $this->connection->prepare( "INSERT INTO tbl_login_log (username, userid, loginstatus) VALUES (?,?,?)");           
            $stmt2->bind_param("sss", $username,$userid,$status);
            $stmt2->execute();

            if(password_verify($password, $hashpassword) == true)
            {
            return($result);
            }
            return false; //dont need an else statement, will terminate if true, default is false              
        }

        /**
         * Returns all of the user metadata for associated user id. Used for things like looking up the mail server, looking at active date etc.
         * @param  int $userid The user id requeted
         * @return array         The specific row in the database for that user.
         */
        public function getUserDetails($userid)
        {
            $stmt = $this->connection->prepare(" SELECT *  FROM tbl_users WHERE userid= ?  ");
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
        }


        /**
         * This verifies a single use PIN code which was sent to user via SMS. OTP are set to expire after 5 mins.
         * @param  int $userid The userid
         * @param  int $otp    The OTP supplied by the end user
         * @return array         Array, with single entry 'AuthStatus'. 0 means fail, 1 means pass.
         */
        public function getVerifiyUserOTP($userid,$otp)
        {
            $currenttime = time();
            $maxtime = $currenttime - 300;
            $stmt = $this->connection->prepare(" SELECT count(*) AS 'AuthStatus'  FROM tbl_auth_sms WHERE userid= ? AND passcode = ? AND UNIX_TIMESTAMP(issuetime) > ? ");
            $stmt->bind_param("sss", $userid,$otp,$maxtime);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;            
        }


        /**
         * This verifies a single use PIN code which was sent to user via SMS. OTP are set to expire after 5 mins.
         * @param  int $userid The userid
         * @param  int $otp    The OTP supplied by the end user
         * @return array         Array, with single entry 'AuthStatus'. 0 means fail, 1 means pass.
         */
        public function setVerifiyUserOTP($userid)
        {
            $newOTP = random_int(100000, 999999);
            $stmt = $this->connection->prepare( " INSERT INTO tbl_auth_sms (userid,passcode) VALUES (?,?)");           
            $stmt->bind_param("ss", $userid,$newOTP);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            //temp
            return $newOTP;
            return $rowid;            
        }        



    //File Viewer SQL Queries    
        /**
         * Returns the count of documents for the user in the system. Document status is not important for this - e.g. txn status = 0 or 99, both will count towards total. The exception is if the user has deleted the record, in which case the record is actually moved to a deleted table.
         *
         * The join table determins what repositories the user can see. 
         *
         * @param  [int] $userid [the user id]
         * @return [array]         [the count of records in the system]
         */
        public function getDocumentCount($userid)
        {
            $stmt = $this->connection->prepare(" SELECT count(A.txnid) AS totalcount from tbl_txn AS A INNER JOIN tbl_users AS B ON A.userid = B.userid INNER JOIN join_users_repos AS C ON B.userid = C.repoid WHERE C.userid = ?  ");
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 

            return $result;
        }

        /**
         * Returns the count of all documents grouped by status, e.g. status 0 = 93 , status 1 = 12 etc. Used to populate the table at the top of the fileviewer page.
         *
         * As above, admin users will be returnd all users documents.
         *
         * This method uses the 'getUserDetails' method from witin this class.
         *
         * @param  userid $userid specified user id
         * @return array         array of counts by status code
         */
        public function getDocumentByStatus($userid)
        {
            
            $stmt = $this->connection->prepare(" SELECT A.status, count(A.status) AS totalcount  from tbl_txn AS A INNER JOIN tbl_users AS B ON A.userid = B.userid INNER JOIN join_users_repos AS C ON B.userid = C.repoid WHERE C.userid = ? GROUP BY status ");
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            
            return $result;
        }

        /**
         * Returns the list of documents for a specific user, paged. E.g. User 4 requests all documents from page 4 with next 10 records.
         *
         * The methoc calculates from the page number the start point and uses this to generate the LIMIT part of the query.
         *
         * Again, admin users will see all user documents within their company. 
         *
         * This method uses the 'getUserDetails' method from witin this class.
         * 
         * @param  int $userid  The user id
         * @param  int $pageno  The start page number
         * @param  int $perpage The results per page
         * @return array          The document list, txnid, filename, status etc.
         */
        public function getDocumentList($userid,$pageno,$perpage)
        {
            $stmt = $this->connection->prepare(" SELECT A.txnid, A.emailfilename, A.filetype, A.status, A.lastchanged, B.displayname, C.repoid, D.pagecount from tbl_txn AS A INNER JOIN tbl_users AS B ON A.userid = B.userid INNER JOIN join_users_repos AS C ON B.userid = C.repoid LEFT JOIN tbl_documents AS D ON A.txnid = D.txnid WHERE C.userid = ? ORDER BY txnid DESC LIMIT ? , ?");
            $offset = ($pageno * $perpage) - $perpage; 
            $stmt->bind_param("sss", $userid,$offset,$perpage);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 

            return $result;  
        }


    //for search
        /**
         * Searches the extracted text in tbl_documents for an exact match against the text string. This is a simple search, so multiple words seperated by spaces are treated as one string, e.g. 'word1 word2' and not as two seperate sub clauses in the where statement.
         * 
         * @param  int $userid The user id
         * @param  stirng $text   the search string
         * @return array         the search results
         */
        public function getTxnTextSearch($userid,$text)
        {
            $text = "%" . $text . "%";

            $stmt = $this->connection->prepare("SELECT A.txnid, B.lastchanged, B.emailfilename, B.filetype, C.displayname FROM tbl_extracts AS A INNER JOIN tbl_txn AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C ON C.userid = B.userid INNER JOIN join_users_repos AS D ON C.userid = D.repoid WHERE B.status = 3 AND D.userid = ? AND A.extract LIKE ? LIMIT 0 , 10 ");
            $stmt->bind_param("ss",$userid,$text);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            
            return $result;              
        }

        /**
         * Returns all of the specific details for a specified transaction id.
         *
         * The user id is required to ensure that documents can only be accessed by the authorised user.
         * @param  int $txnid  The transaction id
         * @param  int $userid the user id
         * @return array         All of the document details from the documents and tnx tables for the specified txn.
         */
        public function getTxnDetails($txnid,$userid)
        {
        //looks up the document id to see if the user has permission. E.g. if they are not in the join table for that repo, they cannot see it. E.g. user 48 owns the doc. User 47 has access to 48. User 48 can see it.

            $stmt = $this->connection->prepare("SELECT A.*, B.*, C.displayname FROM tbl_documents AS A INNER JOIN tbl_txn AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C ON C.userid = B.userid INNER JOIN join_users_repos AS D ON C.userid = D.repoid WHERE  A.txnid = ? AND D.userid = ?");
            $stmt->bind_param("ss",$txnid,$userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();            
            return $result;              
        }

    //for advanced search
        public function getUsersSearchableRepos($userid)
        {
            $stmt = $this->connection->prepare("SELECT A.repoid AS repoid, B.displayname FROM join_users_repos AS A  INNER JOIN tbl_users AS B ON A.repoid = B.userid WHERE A.userid = ? ");
            $stmt->bind_param("s",$userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result; 
        }

        public function getSearchResults($userid,$searchbox,$searchrepo,$searchdate,$pageno,$perpage)
        {
            //starting with the search box/
            $phrasearray = array();
            $orarray = array();
            $andarray = array();
            $notarray = array();
            $finalarray = array();

            $offset = ($pageno * $perpage) - $perpage; 

            //step 1 - get phrase out
                if (preg_match('/&quot;([^"]+)&quot;/', $searchbox, $phrase)) 
                {
                    //get quoted string
                    array_push($phrasearray,$phrase[1]) ;
                    //remove from string
                    $searchbox = str_replace("&quot;" . $phrase[1] . "&quot;",'',$searchbox);            
                }
            //step 2 - get OR condition out
                if (preg_match('/\*([^"]+)\*/', $searchbox, $orsearch)) 
                {
                    //get enclosed string and break apart...
                    $exploded = explode(' ',$orsearch[1]);
                    foreach($exploded AS $key => $value)
                    {
                        array_push($orarray,$value);
                    }                    
                    //remove from string
                    $searchbox = str_replace("*" . $orsearch[1] . "*",'',$searchbox);            
                } 
            //step 3 - for the remaining 
            $searchbox = preg_replace('!\s+!', ' ', trim($searchbox));
            $exploded = explode(' ',trim($searchbox));
            foreach($exploded AS $key => $value)
            {
                if(substr($value, 0,1) == "-")
                {
                    array_push($notarray,$value);
                }
                elseif(strlen($value) > 0)
                {
                   array_push($andarray,$value); 
                }
            }

            $finalarray['phrase'] = $phrasearray;
            $finalarray['or'] = $orarray;
            $finalarray['and'] = $andarray;
            $finalarray['not'] = $notarray;

            //var_dump($finalarray);

            //SELECT element.
            $selectSQL = " SELECT A.txnid, A.extract, E.imgstring, E.pagecount, B.lastchanged, B.emailfilename, C.displayname FROM tbl_extracts AS A ";

            //JOINS
            $joinSQL = " INNER JOIN tbl_txn AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C ON C.userid = B.userid INNER JOIN join_users_repos AS D ON C.userid = D.repoid INNER JOIN tbl_documents AS E ON A.txnid = E.txnid "; 

            //WHERE
            $whereSQL = " WHERE B.status = 3 ";

            //Our conditions
            $conditionSQL = "";
            $preparedvars = array();
                //repo id 
                if($searchrepo == "all")
                {
                    //find all repo
                    $allrepo = $this->getUsersSearchableRepos($userid);
                    $conditionSQL .= " AND (";                    
                    foreach($allrepo AS $repokey => $repovalue)
                    {
                        $conditionSQL .= " D.userid = ? OR ";
                        array_push($preparedvars,(string)$repovalue['repoid']);                        
                    }
                    $conditionSQL = substr($conditionSQL, 0, -3);
                    $conditionSQL .= ")";
                }
                else
                {
                    $conditionSQL .= " AND D.userid = ?";
                    array_push($preparedvars,$searchrepo);
                }
                //phrase
                if(isset($finalarray['phrase'][0]))
                {
                    
                    $conditionSQL .= " AND A.extract LIKE ? ";
                    array_push($preparedvars,"%" . $finalarray['phrase'][0] . "%");
                }
                //and
                if(isset($finalarray['and'][0]))
                {
                    foreach($finalarray['and'] AS $key => $value)
                    {
                        $conditionSQL .= " AND A.extract LIKE ? ";
                        $value = str_replace('-','',$value);
                        array_push($preparedvars,"%" . $value. "%");                         
                    }
                }  
                //not
                if(isset($finalarray['not'][0]))
                {
                    foreach($finalarray['not'] AS $key => $value)
                    {
                        $conditionSQL .= " AND A.extract NOT LIKE ? ";
                        $value = str_replace('-','',$value);
                        array_push($preparedvars,"%" . $value. "%");                        
                    }
                }  
                //or...
                if(isset($finalarray['or'][0]))
                {
                    $conditionSQL .= " AND (";                    
                    foreach($finalarray['or'] AS $key => $value)
                    {
                        $conditionSQL .= "  A.extract LIKE ? OR ";
                        array_push($preparedvars,"%" . $value. "%");  
                                               
                    }
                    $conditionSQL = substr($conditionSQL, 0, -3);
                    $conditionSQL .= ")";
                }

            //for the date

            //RegEx loop:
            if(strlen($searchdate) > 0)
            {
                //search date is mm/dd/yyyy format.
                $day   = (int)substr($searchdate, 3,2);
                $month = (int)substr($searchdate, 0, 2);
                $year  = (int)substr($searchdate,6,4);                
                //echo("Date is set");
                $searchdatearray = array();
                //regex format either dd/mm/yyyy or dd-mm-yyyy with as many spaces as needed prefox 0 optional on numeric day or month
                $searchdatearray[0] = '0?' .$day . '([[:space:]]+)?[-|/]([[:space:]]+)?0?' . $month . '([[:space:]]+)?[-|/]([[:space:]]+)?' . $year;      
                //as above, but with long month name
                $monthName   = date("F",strtotime($searchdate));
                $searchdatearray[1] = '0?' .$day . '([[:space:]]+)?[-|/]([[:space:]]+)?' . $monthName . '([[:space:]]+)?[-|/]([[:space:]]+)?' . $year;   
                //as above, but with short month name
                $monthName   = date("M",strtotime($searchdate));
                $searchdatearray[2] ='0?' .$day . '([[:space:]]+)?[-|/]([[:space:]]+)?' . $monthName . '([[:space:]]+)?[-|/]([[:space:]]+)?' . $year;              

                //var_dump($searchdatearray);

                //loop out
                $conditionSQL .= " AND (";  
                foreach($searchdatearray AS $key => $value)
                {
                    $conditionSQL .= "  A.extract REGEXP ? OR ";
                    array_push($preparedvars, $searchdatearray[$key]); 
                }
                $conditionSQL = substr($conditionSQL, 0, -3);
                $conditionSQL .= ")";

            }
            //var_dump($conditionSQL);
                                                           
            //add page numbers to vars
            array_push($preparedvars, $offset);
            array_push($preparedvars, $perpage); 

            //var_dump($preparedvars);

            $types = str_repeat('s', count($preparedvars)); //types            

            //execute:::
            $SQL = $selectSQL . $joinSQL . $whereSQL . $conditionSQL . " LIMIT ?,?" ;          
            $stmt = $this->connection->prepare($SQL);
            $stmt->bind_param($types, ...$preparedvars);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            //var_dump($result);
            
            //base 64 enconding
            foreach($result AS $reskey => $resvalue)
            {
                $imgstring = base64_encode($resvalue['imgstring']);
                $result[$reskey]['imgstring'] = $imgstring;
            }
            //var_dump($result);
            
            //var_dump($SQL);
            //var_dump($types);
            //var_dump($preparedvars);
            
            //
            //now find number of results....we need to remove the limits from the prepared vars array
            $arraycount = count($preparedvars);
            unset($preparedvars[$arraycount-1]);
            unset($preparedvars[$arraycount-2]);
            //var_dump($preparedvars);
            $types = str_repeat('s', count($preparedvars)); //types    
            $countselect = " SELECT count(A.txnid) AS count FROM tbl_extracts AS A ";
            $countSQL = $countselect . $joinSQL . $whereSQL . $conditionSQL;  
            $countstmt = $this->connection->prepare($countSQL);
            $countstmt->bind_param($types, ...$preparedvars);
            $countstmt->execute();
            $countresult = $countstmt->get_result()->fetch_all(MYSQLI_ASSOC); 

            // var_dump($countresult);

            $result['count'] = $countresult[0]['count'];

            return $result;            

            //return $SQL;
        }


    //for settings        
        /**
         * Updates the password for the new user. Note that password hashing takes place in the script and not in this method.
         * @param int $userid   user id to update
         * @param string $password the hashed password
         */
        public function setNewPassword($userid,$password)
        {
            $sql = "UPDATE tbl_users SET userpassword = ? WHERE userid = ?";
            $stmt= $this->connection->prepare($sql);
            $stmt->bind_param("ss", $password, $userid);
            $stmt->execute();
            return true;              
        }


    //For Usage reports
        /**
         * Returns sum of all data size for a specific user.
         * @param  int $userid The user id requeted
         * @return array         The specific row in the database for that user.
         */
        public function getUserUsage($userid)
        {
            $stmt = $this->connection->prepare(" SELECT (sum(char_length(A.docstring)) + sum(char_length(A.imgstring)) + sum(char_length(D.extract))) AS Total FROM tbl_documents AS A INNER JOIN tbl_users AS B ON A.userid = B.userid INNER JOIN join_users_repos AS C ON B.userid = C.repoid INNER JOIN tbl_extracts AS D ON A.txnid = D.txnid WHERE C.userid = ? GROUP BY repoid  ");
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
        }  

        /**
         * Returns sum of all data size by type for a specific user.
         * @param  int $userid The user id requeted
         * @return array         The specific row in the database for that user.
         */
        public function getUserUsageBreakdown($userid)
        {

            $stmt = $this->connection->prepare(" SELECT sum(char_length(A.docstring)) AS PDFTotal , sum(char_length(A.imgstring)) AS ImageTotal, sum(char_length(D.extract)) AS ExtractTotal, (sum(char_length(A.docstring)) + sum(char_length(A.imgstring)) + sum(char_length(D.extract))) AS Total FROM tbl_documents AS A INNER JOIN tbl_users AS B ON A.userid = B.userid INNER JOIN join_users_repos AS C ON B.userid = C.repoid INNER JOIN tbl_extracts AS D ON A.txnid = D.txnid WHERE C.userid = ? GROUP BY C.repoid ");
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
        }

        public function getUsageBreakdownByUser($userid)
        {
            //stmt = $this->connection->prepare(" SELECT B.username, sum(char_length(A.docstring)) AS PDFTotal , sum(char_length(A.imgstring)) AS ImageTotal, sum(char_length(A.extract)) AS ExtractTotal FROM tbl_documents AS A INNER JOIN tbl_users AS B ON A.userid = B.userid  WHERE B.companyid = ? GROUP BY B.userid ");
            
            $stmt = $this->connection->prepare(" SELECT B.displayname, sum(char_length(A.docstring)) AS PDFTotal , sum(char_length(A.imgstring)) AS ImageTotal, sum(char_length(D.extract)) AS ExtractTotal  FROM tbl_documents AS A INNER JOIN tbl_users AS B ON A.userid = B.userid INNER JOIN join_users_repos AS C ON B.userid = C.repoid INNER JOIN tbl_extracts AS D ON A.txnid = D.txnid WHERE C.userid = ? GROUP BY C.repoid ");   
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;
        }

    //for delete and restore txn

        /**
         * Deleting a document off the front end does not really delete it. It moves it to a deleted table. At present there are no time limits, but future releases may have a cron task which deletes documents over a cetain age.
         * @param int $txnid  The txnid to be deleted
         * @param int $userid the userid
         */
        public function setDeleteDoc($txnid,$userid)
        {
            $stmt = $this->connection->prepare("SELECT A.*, B.*,D.extract FROM tbl_documents AS A INNER JOIN tbl_txn AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C ON A.userid = C.userid INNER JOIN tbl_extracts AS D ON A.txnid = D.txnid WHERE  A.txnid = ?  ");
            $stmt->bind_param("s",$txnid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            //now save in to deleted items
            $stmt2 = $this->connection->prepare(" INSERT INTO tbl_deleted (txnid,userid,docstring,imgstring,imgsize,extract,pagecount) VALUES (?,?,?,?,?,?,?) ");
            $stmt2->bind_param("sssssss", $result['txnid'],$result['userid'],$result['docstring'],$result['imgstring'],$result['imgsize'],$result['extract'],$result['pagecount']);
            $stmt2->execute();     
            $rowid = $stmt2->insert_id;

            //now delete record from documents (leave tbl_txn)
            $stmt = $this->connection->prepare("DELETE FROM tbl_documents WHERE txnid = ? ");
            $stmt->bind_param("s",$txnid);
            $stmt->execute();
            $result = $stmt->get_result(); 

            //now delete record from documents (leave tbl_txn)
            $stmt = $this->connection->prepare("DELETE FROM tbl_extracts WHERE txnid = ? ");
            $stmt->bind_param("s",$txnid);
            $stmt->execute();
            $result = $stmt->get_result();             

            //updated tbl_txn status
            $docstatus = $this->setOCRTxnStatus($txnid,4); 
            return true;              
        }

        /**
         * Restores a document from the deleted table to the documents table. Note that the record in tbl_txn was not deleted. The inserted record back in to documents retains the original txnid. 
         * @param int $txnid  the txn id to restore
         * @param int $userid the user id who owns the document
         */
        public function setRestoreDoc($txnid,$userid)
        {
            $stmt = $this->connection->prepare("SELECT A.*, B.* FROM tbl_deleted AS A INNER JOIN tbl_txn AS B ON A.txnid = B.txnid  INNER JOIN tbl_users AS C ON A.userid = C.userid  WHERE  A.txnid = ?  ");
            $stmt->bind_param("s",$txnid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            //now save in to deleted items
            $stmt2 = $this->connection->prepare(" INSERT INTO tbl_documents (txnid,userid,docstring,imgstring,imgsize,pagecount) VALUES (?,?,?,?,?,?) ");
            $stmt2->bind_param("ssssss", $result['txnid'],$result['userid'],$result['docstring'],$result['imgstring'],$result['imgsize'],$result['pagecount']);
            $stmt2->execute();     
            $rowid = $stmt2->insert_id;

            //now save in to deleted items
            $stmt2 = $this->connection->prepare(" INSERT INTO tbl_extracts (extract,txnid,userid) VALUES (?,?,?) ");
            $stmt2->bind_param("sss", $result['extract'],$result['txnid'],$result['userid']);
            $stmt2->execute();     
            $rowid = $stmt2->insert_id;

            //now delete record from documents (leave tbl_txn)
            $stmt = $this->connection->prepare("DELETE FROM tbl_deleted WHERE txnid = ? ");
            $stmt->bind_param("s",$txnid);
            $stmt->execute();
            $result = $stmt->get_result(); 

            //updated tbl_txn status
            $docstatus = $this->setOCRTxnStatus($txnid,3); 
            return true;              
        }

        /**
         * User access rights are stored in the join_users_repos table. We must ensure that the user has permission to delete a file before marking as deleted. This means checking the repo that the txnid belongs in (user id on tbl_txn) and finding that record in the access rights table to confirm user has rights level 2 or higher.
         * @param  INT $userid The user id requesting to delete
         * @param  INT $txnid  The target txnid
         * @return Boolean         Returns True or False;
         */
        public function getDeleteRights($userid,$txnid)
        {
            $stmt = $this->connection->prepare(" SELECT A.rights AS AccessRights FROM join_users_repos AS A INNER JOIN tbl_txn AS B ON A.repoid = B.userid WHERE A.userid = ? AND B.txnid = ? ");
            $stmt->bind_param("ss",$userid,$txnid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            //process the result
                $return = false; //default
                $result['AccessRights'] == 0 ? $return = false : null;
                $result['AccessRights'] == 1 ? $return = false : null;
                $result['AccessRights'] >= 2 ? $return = true : null;
            return $return;
        }

    //For user management elements
    
        public function getAllRepos($userid)
        {
            $userdetails = $this->getUserDetails($userid);
            $companyid = $userdetails['companyid'];

            $stmt = $this->connection->prepare("SELECT A.userid AS repoid, A.displayname FROM tbl_users AS A  WHERE  A.companyid = ? AND A.userclass = 1 ORDER BY A.userid");
            $stmt->bind_param("s",$companyid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;   
        }

        /**
         * Used to get a list of users within a company for a specific administator user.
         * @param  INT $userid the user id
         * @return Array         A list of user access rights within the same company as the associated administrator.
         */
        public function getAllUserRights($userid)
        {
            //first find this admin users company id
            $userdetails = $this->getUserDetails($userid);
            $companyid = $userdetails['companyid'];

            $stmt = $this->connection->prepare("SELECT A.userid, A.username, A.userclass, C.displayname, B.repoid, B.rights FROM tbl_users AS A INNER JOIN join_users_repos AS B ON A.userid = B.userid  LEFT JOIN tbl_users AS C ON C.userid = B.repoid WHERE  B.companyid = ? ORDER BY A.userclass ");
            $stmt->bind_param("s",$companyid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;            
        }

        public function getUserAccessRights($userid)
        {
            $stmt = $this->connection->prepare(" SELECT A.userid, A.repoid, A.rights FROM join_users_repos AS A WHERE A.userid = ? ");
            $stmt->bind_param("s",$userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;            
        }

        public function setDeleteUserRight($userid,$repoid)
        {
            $stmt = $this->connection->prepare("DELETE FROM join_users_repos WHERE userid = ? AND repoid = ? ");
            $stmt->bind_param("ss",$userid,$repoid);
            $stmt->execute();
            $result = $stmt->get_result(); 
            return $result;
        }

        /**
         * Finds out how many repositories can be accessed by a specific user id. E.g. User 48 has access to 5 repositories. Used in user management to ensure user has at least 1 record associated to them.
         * @param  INT $userid The user id
         * @return Array         The number of repositories they can access - returned as array element 'RepoCount'.
         */
        public function getCountRepoAccessByUser($userid)
        {
            $stmt = $this->connection->prepare(" SELECT count(userid) AS RepoCount FROM join_users_repos WHERE userid = ? ");
            $stmt->bind_param("s",$userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
        }

        /**
         * Finds out if a user has access to a specific repository. 
         * @param  INT $userid the user id to be checked
         * @param  INT $repoid the repository id 
         * @return Array         Array containing the result as RepoAccess array element. Result of 0 means no access, result of 1 means access.
         */
        public function getCheckRepoAccessByUser($userid,$repoid)
        {
            $stmt = $this->connection->prepare(" SELECT count(userid) AS RepoAccess FROM join_users_repos WHERE userid = ? AND repoid = ? ");
            $stmt->bind_param("ss",$userid,$repoid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;            
        }

        public function setUpdateRepoAccessByUser($userid,$repoid,$rights)
        {
            $stmt = $this->connection->prepare(" UPDATE join_users_repos SET rights = ? WHERE userid = ? AND repoid = ? ");
            $stmt->bind_param("sss", $rights, $userid,$repoid);
            $stmt->execute();           
            return true;    
        }    

        public function setCreateRepoAccessByUser($userid,$companyid,$repoid,$rights)
        {
            $stmt = $this->connection->prepare( " INSERT INTO join_users_repos (userid,companyid,repoid,rights) VALUES (?,?,?,?)");  
            $stmt->bind_param("ssss", $userid, $companyid,$repoid,$rights);
            $stmt->execute();           
            return true;    
        } 

        public function getAllUsersByAdmin($adminid)
        {
            //first find this admin users company id
            $userdetails = $this->getUserDetails($adminid);
            $companyid = $userdetails['companyid'];

            $stmt = $this->connection->prepare(" SELECT A.userid, A.username, A.userclass, A.isactive FROM tbl_users AS A  WHERE A.companyid = ? ");
            $stmt->bind_param("s",$companyid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;
        }

        public function setUserActiveStatus($adminid,$userid,$state)
        {
            $userdetails = $this->getUserDetails($adminid);
            $companyid = $userdetails['companyid'];            

            $stmt = $this->connection->prepare(" UPDATE tbl_users SET isactive = ? WHERE userid = ? AND companyid = ? ");
            $stmt->bind_param("sss", $state, $userid,$companyid);
            $stmt->execute();           
            return true;               
        }

    //logging related queries - like logging viewing a document etc.   

        /**
         * Sets a log entry for viewing a document. View Type depends on type of action. E.g. 
         * - 1 = View Doc Page
         * - 2 = Downloaded Doc
         * - 3 - Emailed Doc
         * @param INT $userid   User ID attempting to access the document
         * @param INT $txnid    The Transaction ID from tbl_txn
         * @param INt $viewtype As defined above.
         */
        public function setAddViewDocLog($userid,$txnid,$viewtype,$companyid,$action = false)
        {
            $timenow = time();
            $stmt = $this->connection->prepare( " INSERT INTO tbl_log_viewdoc (userid,txnid,viewtype,companyid,logtime,action) VALUES (?,?,?,?,?,?)");  
            $stmt->bind_param("ssssss", $userid, $txnid,$viewtype,$companyid,$timenow,$action);
            $stmt->execute();           
            return true;    
        } 

        public function getViewDocLog($companyid,$txnid,$length)
        {
            $length == "short" ? $limitsql = " LIMIT 0,10" : $limitsql = " ";
            $stmt = $this->connection->prepare(" SELECT A.userid, A.txnid, A.viewtype, A.logtime, A.action, B.username FROM tbl_log_viewdoc AS A INNER JOIN tbl_users AS B ON A.userid = B.userid WHERE A.txnid = ? AND A.companyid = ? ORDER BY logtime DESC  " . $limitsql);
            $stmt->bind_param("ss",$txnid,$companyid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result; 
        }

        public function setAddAdminActionLog($adminid, $userid,$repoid,$rights,$companyid)
        {
            $timenow = time();
            $stmt = $this->connection->prepare( " INSERT INTO tbl_log_adminchanges (adminid, userid,repoid,rights,companyid,logtime) VALUES (?,?,?,?,?,?)");  
            $stmt->bind_param("ssssss", $adminid, $userid, $repoid,$rights,$companyid,$timenow);
            $stmt->execute();           
            return true;    
        } 

        public function getViewUserDocLog($adminid,$userid,$pageno,$perpage)
        {
            $userdetails = $this->getUserDetails($adminid);
            $companyid = $userdetails['companyid']; 

            $offset = ($pageno * $perpage) - $perpage; 
            //echo("Start record is " . $offset);

            $stmt = $this->connection->prepare(" SELECT A.userid, A.txnid, A.viewtype, A.logtime, A.action, B.username, C.emailfilename FROM tbl_log_viewdoc AS A INNER JOIN tbl_users AS B ON A.userid = B.userid INNER JOIN tbl_txn AS C ON A.txnid = C.txnid WHERE A.userid = ? AND A.companyid = ? ORDER BY logtime DESC LIMIT ?,? ");
            $stmt->bind_param("ssss",$userid,$companyid,$offset,$perpage);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            //var_dump($result); 
            return $result; 
        }  

        public function getViewUserDocLogCount($adminid,$userid)
        {
            $userdetails = $this->getUserDetails($adminid);
            $companyid = $userdetails['companyid']; 
            
            $stmt = $this->connection->prepare(" SELECT count(A.trackid) AS TotalCount FROM tbl_log_viewdoc AS A WHERE A.userid = ? AND A.companyid = ?  ");
            $stmt->bind_param("ss",$userid,$companyid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;

        }

           

//All Cron related queries


    //For managing system and transaction states in normal operation
        
        /**
         * There are currently 3 active system states that are tracked. For each state, there are two state codes. The following maps out what the states are and what they are for:
         * 
         * Read Mail Cron Job:  Inactive State: 0,  Active State: 96
         * Image Cron Job:      Inactive State: 1,  Active State: 98
         * Extract Cron Job:    Inactive State 2,   Active State: 99
         *
         * When a cron job starts if looks at current status using the above state codes.
         * @param  Int $statuscode As above, integer to look at specific state
         * @return Array             Count of system state with specific code - $array[0]['counter']
         */
        public function getSystemStatus($statuscode)
        {
                $stmt = $this->connection->prepare(" SELECT count(*) AS counter FROM tbl_status WHERE statuscode = ? ");
                $stmt->bind_param("s", $statuscode);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
                return $result;
        }




        /**
         * If the cron job fails, we may need to reset more than one record. We reset the failed record to 59, but then we need to reset additional records
         * @param INT $statuscode the status code to reset.
         */
        public function setClearByTxnStatus($searchstatuscode,$newstatuscode)
        {
            $stmt = $this->connection->prepare("UPDATE tbl_txn SET status = ? WHERE status = ? ");           
            $stmt->bind_param("ss", $newstatuscode,$searchstatuscode);
            $stmt->execute();
            return true;            
        }

        /**
         * This method is used to update the txn tracker table. This table is used purely for error correction. The cron job will set the latest txn id for the specific state - e.g. we are running the image cron job, so we want to state the current transaction we are working on against that specific code - e.g. 98.
         *
         * This data is used by the trackfix process when something has timed out and hung. It can then know which txnid caused the issue and update its status to an error code and then reset the system status.
         * 
         * @param int $txnid The current txnid being worked on
         * @param int $state The state to map it against
         */
        public function setCurrentTxn($txnid,$state)
        {
            $stmt = $this->connection->prepare(" UPDATE tbl_txn_tracker SET txnid = ? WHERE state = ? ");
            $stmt->bind_param("ss", $txnid,$state);
            $stmt->execute();         
            return true;   
        }

        /**
         * Used to update the user table with the last time that a specific process was run. E.g. update user 8 with the current time on a specific field. Specific allowed fields are:
         * 1. lastimage
         * 2. lastextract
         * 3. lastmerge
         *
         * This should be updated at a later data to pass an integer and not a field name.
         * @param int $userid The User ID
         * @param string $field  The field to be updated.
         */
        public function setSaveLastRun($userid,$field)
        {
            $timenow = time();            
            $stmt = $this->connection->prepare(" UPDATE tbl_users SET ".$field." = ? WHERE userid = ? ");
            $stmt->bind_param("ss", $timenow,$userid);
            $stmt->execute(); 
            //var_dump($stmt); 
            return true;                   
        }


    //For creating new records
        
        /**
         * This returns a list of all users on the platform where active. It is used by the cron_readmail include file (class_mailtools) to obtain a list of all active users, with associated account metadata like the associated mail account, mail server and credentails. The mail cron only runs against active users, so inactive users may have inboxes full of unprocessed mail.
         * - 
         * @return Array Array of active users following db scheema.
         */
        public function getAllUsers()
        {
            $stmt = $this->connection->prepare(" SELECT *  FROM tbl_users WHERE isactive = 1 AND userclass = 1");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;             
        }



    //For converting to images
    
        /**
         * This query is used by many cron jobs, not only the imag cron job. Its main task is to return a list of documents for a specific status, but linked with when the user last had this specific process run. For example, select top 4 records where the user has waited the longest for any movement on their account processing. 
         * @param int $searchstatus [Status you want for the documents, e.g. 0, 1 or 2]
         * @param int $newstatus    [Target new status, e.g. we go from 0->98->1->99->2->100->3]
         * @param int $limit        [The number of records to return]
         */
        public function setPendingDocuments($searchstatus,$newstatus,$limit)
        {
            //define the field based on the search status requested.
            $searchstatus == 0 ? $field = "lastimage" : null;
            $searchstatus == 1 ? $field = "lastextract" : null;
            $searchstatus == 2 ? $field = "lastmerge" : null;

            //generate the query
            $stmt = $this->connection->prepare(" SELECT A.txnid, A.emailfilename, A.filetype, A.userid, B.docstring, B.imgstring, B.imgsize FROM tbl_txn AS A INNER JOIN tbl_documents AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C  ON A.userid = C.userid WHERE A.status = ?  ORDER BY C.".$field." ASC LIMIT 0,?");          
            $stmt->bind_param("ss", $searchstatus,$limit);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            //var_dump($result);
            foreach($result AS $key => $value)
            {
                $txnid = $value['txnid'];
                $this->setOCRTxnStatus($txnid,$newstatus);
            }            
            return $result;   
        }

        //THE api allows for specific users to be given priority for document processing. This also allows us to test changes whilst only impacting one user.
        //
        public function setPendingDocumentsByUserID($searchstatus,$newstatus,$limit,$userid)
        {
            //define the field based on the search status requested.
            $searchstatus == 0 ? $field = "lastimage" : null;
            $searchstatus == 1 ? $field = "lastextract" : null;
            $searchstatus == 2 ? $field = "lastmerge" : null;

            //generate the query
            $stmt = $this->connection->prepare(" SELECT A.txnid, A.emailfilename, A.filetype, A.userid, B.docstring, B.imgstring, B.imgsize FROM tbl_txn AS A INNER JOIN tbl_documents AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C  ON A.userid = C.userid WHERE A.status = ? AND C.userid = ? ORDER BY A.txnid ASC LIMIT 0,?");          
            $stmt->bind_param("sss", $searchstatus,$userid,$limit);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            //var_dump($result);
            foreach($result AS $key => $value)
            {
                $txnid = $value['txnid'];
                $this->setOCRTxnStatus($txnid,$newstatus);
            }            
            return $result;   
        }
        


        /**
         * Saves the extracted single page image to the database
         * @param int $txnid       transaction id
         * @param string $imagestring the image as a string (stored as a BLOB)
         * @param string $imagesize   The image size in bytes
         */
        public function setSaveChunkedImage($txnid,$imagestring,$imagesize)
        {
            //now save in to deleted items
            $stmt = $this->connection->prepare(" INSERT INTO tbl_chunked (txnid,imgstring,imgsize) VALUES (?,?,?) ");
            $stmt->bind_param("sss", $txnid,$imagestring,$imagesize);
            $stmt->execute();    
            //var_dump($stmt); 
            $rowid = $stmt->insert_id;
            return $rowid;              
        }



    //For extracting the text

        /**
         * The tbl_chunked table contains a column called chunkedstatus. There are 3 possible values:
         * 0 -  chunked text not processed
         * 1 -  chunked text extracted
         * 97 - extraction in progress
         *
         * This method allows the cron job to set a specific status for a chunkid (the PK of the table)
         * @param int $chunkid The chunkid 
         * @param int $status  The status code, 0,1 or 97.
         */
        public function setChunkedStatus($chunkid,$status)
        {
            $stmt = $this->connection->prepare(" UPDATE tbl_chunked SET chunkedstatus = ? WHERE chunkid = ? ");
            $stmt->bind_param("ss", $status,$chunkid);
            $stmt->execute(); 
            //var_dump($stmt); 
            return true;             
        }

        /**
         * Returns a list of images to extract against. The query explanation is as follows:
         * 1. Select from tbl_chunked
         * 2. Join with txn table (needed to know the specific status)
         * 3. Join with user table (needed to know when last extracted time stamp was so we process longest waiting user first)
         * 4. Where chunked status = 0 ...e.g. not started
         *
         * There is an argument to say that C.lastextract should be order ASC. In reality, not too important as long as it cycles all th users.
         *
         * There is an argument to say that txnid is not directly needed as this could be found on the chunked table, but this link enforces record integrity - e,g, a chunked record could have existed without associated txnid, but it should not be processed. These 'shards' may happen if someone incorrectly deletes a txn whilst still being processed and they failed to delete associated data in chunked table.
         * @param  [type] $limit [description]
         * @return [type]        [description]
         */
        public function getChunkedImage($limit)
        {
            //now save in to deleted items
            $stmt = $this->connection->prepare(" SELECT A.chunkid,A.txnid,A.imgstring,A.imgsize, C.userid FROM tbl_chunked AS A INNER JOIN tbl_txn AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C ON B.userid = C.userid WHERE A.chunkedstatus = 0 ORDER BY C.lastextract LIMIT 0,? ");  //lastextract        
            $stmt->bind_param("s", $limit);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;            
        }

        /**
         * Saves the extracted text against the specified record and updates chunkedstatus to be 1 (e.g. complete)
         * @param string $extract The extracted text to be saved
         * @param int $chunkid The chunkid 
         */
        public function setSaveChunkedExtract($extract,$chunkid)
        {
            //now save in to deleted items
            $stmt = $this->connection->prepare(" UPDATE tbl_chunked SET extract = ?, chunkedstatus = 1 WHERE chunkid = ? ");
            $stmt->bind_param("ss", $extract,$chunkid);
            $stmt->execute();     
            $rowid = $stmt->insert_id;
            return $rowid;              
        }  

        /**
         * The use case is complex for this method, but in abstact, lets say we have two documents. One is a single page and the other is a 100+ page document.
         *
         * The user will see that the document status is 99 through the front end. This means extract in progress. We want to update that to 'Extract Complete' once all of the chunks are complete for each document.
         *
         * The query to do this is somewhat complex. We have three potential states for any chunk. 0,1 and 97. We are only interested in knowing that all chunks are set to status 1, but we actually need to do this in reverse so the following applies:
         *
         *1. Select count of each status type (1,0,97)
         *2. From tbl_chunked
         *3. Where txnid = supplied txnid
         *4. grouped by txnid (not really needed, but useful for non txn related query later)
         *5. having count chunkedstats 0 = 0, count chunkedstat 97 = 0
         *
         *Note there is no easy way to say only where chunkedstatus = 1 on multipage documents. 
         *
         *The result is either an array (which means above is true, and therefore all chunkedstatus = 1) or a blank array (e.g. txn does not meet this requirement and there must be at least one chunked record with status 0 or 97) 
         * 
         * @param  int $txnid The Transaction ID
         * @return array        Either blank array or single array containing count from all cols as in the Select element.
         */
        public function getCompletedExtractByTxn($txnid)
        {
            $stmt = $this->connection->prepare(" SELECT txnid, count(CASE WHEN chunkedstatus = 1 THEN 1 END) as ExtractedCount, count(CASE WHEN chunkedstatus = 0 THEN 1 END) as NotExtractedCount , count(CASE WHEN chunkedstatus = 97 THEN 1 END) as InProcessCount from tbl_chunked where txnid = ? group by txnid having count(CASE WHEN chunkedstatus = 0 THEN 0 END) = 0 AND count(CASE WHEN chunkedstatus = 97 THEN 0 END) = 0");  //lastextract        
            $stmt->bind_param("s", $txnid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;  
        }  

    //For consolidating the text

        /**
         * This method has the single task of returning all transactions where every chunk has been completed from tbl_chunked.
         *
         * The explanation for the logic behind this is explained in setSaveChunkedExtract.
         *
         * The returned array is grouped by txn. E.g. we have a list of transaction to process.
         * 
         * @return array        Either blank array or single array containing count from all cols as in the Select element.
         */            
        public function getCompletedExtracts()        
        {
            $stmt = $this->connection->prepare(" SELECT txnid, count(CASE WHEN chunkedstatus = 1 THEN 1 END) as ExtractedCount, count(CASE WHEN chunkedstatus = 0 THEN 1 END) as NotExtractedCount , count(CASE WHEN chunkedstatus = 97 THEN 1 END) as InProcessCount from tbl_chunked  group by txnid having count(CASE WHEN chunkedstatus = 0 THEN 0 END) = 0 AND count(CASE WHEN chunkedstatus = 97 THEN 0 END) = 0  ");  //lastextract        
            //$stmt->bind_param("s", $limit);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;  
        }

        /**
         * Now that we have a list of completed transactions from getCompletedExtracts() the Cron job moves on to the next step which is to get all of the text extracts from that specific txnid. 
         * @param  int $txnid The transaction id 
         * @return array        The extracted text from each image in an array
         */
        public function getChunkedByTxnid($txnid)
        {
            $stmt = $this->connection->prepare(" SELECT extract from tbl_chunked WHERE txnid = ? ");  //lastextract        
            $stmt->bind_param("s", $txnid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;              
        }

        public function getChunkedByUserud($txnid)
        {
            $stmt = $this->connection->prepare(" SELECT userid from tbl_txn WHERE txnid = ? ");  //lastextract        
            $stmt->bind_param("s", $txnid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;                
        }

        /**
         * Now that we have the complete extracted text from getChunkedbyTxnid the Cron job now has aggregated all extracted text to a single string and will store them in the documents table against the correct transactionid       
         * @param int $txnid [the transaciton id]
         * @param string $text  [the aggregated string of all text extracted from every chunked image]
         */
        public function setSaveText($txnid,$text,$userid)
        {
            $stmt = $this->connection->prepare(" INSERT INTO  tbl_extracts (txnid,userid,extract) VALUES (?,?,?) ");
            $stmt->bind_param("sss", $txnid,$userid,$text);
            $stmt->execute();  
            //var_dump($stmt);          
            $rowid = $stmt->insert_id;
            return $rowid;            
        }

        /**
         * Once a trasnaction has been consolidated out (all text saved against a specific txn), we no longer need the chunked data. This can now be deleted from the database table.
         * @param [type] $txnid [description]
         */
        public function setDeleteChunkedTxn($txnid)
        {
            $stmt = $this->connection->prepare("DELETE FROM tbl_chunked WHERE txnid = ? ");
            $stmt->bind_param("s",$txnid);
            $stmt->execute();
            $result = $stmt->get_result(); 
            return $result;
        }


    //For the track fix cron job

        /**
         * Returns a list of the current system states. This is only used by trackfix cron job.
         * @return array [List of all the system states, firendly name and last run time]
         */
        public function getAllSystemStates()
        {
                $stmt = $this->connection->prepare(" SELECT * FROM tbl_status ");
                $stmt->execute();
                $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
                return $result;
        }

        /**
         * If the getAllSystemStates returns that a system has been in a speicifc state for longer than the permitted amount of time, then the system needs to know which record was being processed on within this state in order for it to hang.
         *
         * Problem transactions are set to a status of 59. When needed, chunked data is deleted. The system states are then reset to 'inactive' state so that they can start running again.
         * 
         * @param  int $state The specific state being queried against
         * @return array        Array containing the txnid of the problem record.
         */
        public function getCurrentTxn($state)
        {
            $stmt = $this->connection->prepare(" SELECT * FROM tbl_txn_tracker WHERE state = ? ");
            $stmt->bind_param("s", $state);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;               
        }

    //For deleting sales data
    
        /**
         * Deletes the sales data from the database. This is to prevent demo data from customers being exposed to other customers. Data may be in three places, either tbl_txn, tbl_documents or in chunked if in progress.
         * @param INT $userid The user id from the database. 
         */
        public function setDeleteSalesData($userid)
        {
            // $stmt = $this->connection->prepare("DELETE A,B FROM tbl_documents AS A JOIN tbl_txn AS B ON A.txnid = B.txnid WHERE B.userid = ? AND (B.status = 3 OR B.status = 59 OR B.status = 4) ");
            // $stmt->bind_param("s",$userid);
            // $stmt->execute();
            // $result = $stmt->get_result();
            // return $result;  

            $stmt = $this->connection->prepare("DELETE FROM tbl_documents WHERE userid = ? ");
            $stmt->bind_param("s",$userid);
            $stmt->execute();
            $result = $stmt->get_result();

            $stmt = $this->connection->prepare("DELETE FROM tbl_extracts WHERE userid = ? ");
            $stmt->bind_param("s",$userid);
            $stmt->execute();
            $result = $stmt->get_result();

            $stmt = $this->connection->prepare("DELETE FROM tbl_txn  WHERE userid = ? ");
            $stmt->bind_param("s",$userid);
            $stmt->execute();
            $result = $stmt->get_result();

            $stmt = $this->connection->prepare("DELETE FROM tbl_deleted  WHERE userid = ? ");
            $stmt->bind_param("s",$userid);
            $stmt->execute();
            $result = $stmt->get_result();
            return true;             
        }
    



//MONITORING AND SUPPORT RELATED

    //For cron report on dashboard
    
        public function getMonitoringPanelSystemState()
        {
            $stmt = $this->connection->prepare(" SELECT *  FROM tbl_status ");
            //$stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;                             
        }

        public function getMonitoringPanelTxnStateCount()
        {
            $stmt = $this->connection->prepare(" SELECT A.status, count(A.status) AS DocCount, B.description FROM tbl_txn AS A INNER JOIN tbl_statuscodes AS B ON A.status = B.statuscode GROUP BY A.status, B.description ORDER BY A.status ASC ");
            //$stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;                             
        }  

        public function getChunkedQueue()
        {
            $stmt = $this->connection->prepare(" SELECT count(A.chunkid) AS ChunkedCount, A.chunkedstatus FROM tbl_chunked AS A GROUP BY A.chunkedstatus ");
            //$stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;                             
        }           

        //SELECT A.status, A.userid, count(A.txnid) FROM tbl_txn AS A GROUP BY A.status , A.userid
        
        public function getUsageByUser()
        {
            $stmt = $this->connection->prepare(" SELECT A.status, A.userid, count(A.txnid) AS recordcount FROM tbl_txn AS A GROUP BY A.status ,  A.userid ");
            //$stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;             
        }

        public function getAllStatusCodes()
        {
            $stmt = $this->connection->prepare(" SELECT *  FROM tbl_statuscodes ");
            //$stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;  
        }

        public function getAllCompanies()
        {
            $stmt = $this->connection->prepare(" SELECT *  FROM tbl_companies ");
            //$stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;  
        }

        public function getAllCompaniesRepos($companyid)
        {
            $stmt = $this->connection->prepare("SELECT A.userid, A.displayname FROM tbl_users AS A  WHERE  A.companyid = ? AND A.userclass = 1 ORDER BY A.userid");
            $stmt->bind_param("s",$companyid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;               
        }

        public function getAllCompanyUsers($companyid)
        {
            $stmt = $this->connection->prepare("SELECT A.userid, A.displayname, A.username, A.userclass, A.isactive, A.companyid FROM tbl_users AS A  WHERE  A.companyid = ?  ORDER BY A.userid");
            $stmt->bind_param("s",$companyid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result;   
        }

        public function setSaveNewCompany($companyname)
        {
            //check company name does not exist
            $companylist = $this->getAllCompanies();
            $companykey = array_search($companyname, array_column($companylist, 'companyname'),true); 

            if($companykey == false)
            {                
                $stmt = $this->connection->prepare( " INSERT INTO tbl_companies (companyname) VALUES (?)");           
                $stmt->bind_param("s", $companyname);
                $stmt->execute();
                $rowid = $stmt->insert_id;
                return($rowid);                
            }
            else
            {
                return false;                   
            }         
        }

        public function setNewUser($companyid,$userclass,$username,$password,$displayname=null,$mailserver=null,$mailaddress=null,$mailpassword=null,$mailport=null,$mailssl=null)
        {
            //check user name does not exist.
            $userlist = $this->getAllUsers();
            $userkey = array_search($username, array_column($userlist, 'username'),true); 

            if($userkey == false)
            {                
                //generate some required vars
                $isactive = 1;
                $authmethod = 0;
                $activedate = date('Y-m-d');
                $lastimage = 0;
                $lastextract = 0;
                $lastmerge = 0;
                $telephone = "none";

                //generate password string
                $password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $this->connection->prepare( " INSERT INTO tbl_users (companyid,username,userpassword,displayname,userclass,authmethod,activedate,isactive,emailserver,emailaddress,emailpassword,emailport,emailssl,lastimage,lastextract,lastmerge,telephone) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ");           
                $stmt->bind_param("sssssssssssssssss", $companyid,$username,$password,$displayname,$userclass,$authmethod,$activedate, $isactive, $mailserver,$mailaddress,$mailpassword,$mailport,$mailssl,$lastimage,$lastextract,$lastmerge,$telephone);
                $stmt->execute();
                $rowid = $stmt->insert_id;
                //var_dump($stmt);
                return($rowid);                
            }
            else
            {
                return false;                   
            } 

        }

        public function setUserRepoAccess($userid,$repoid,$companyid,$accesslevel)
        {
            $stmt = $this->connection->prepare( " INSERT INTO join_users_repos (userid,companyid,repoid,rights) VALUES (?,?,?,?)");           
            $stmt->bind_param("ssss", $userid,$companyid,$repoid,$accesslevel);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            return($rowid);   
        }

        public function setDeleteCompany($companyid)
        {
            //removes company, users etc....not to be used lightly
            $log = array();
            
            //start with documents
            $stmt = $this->connection->prepare("DELETE A.* FROM tbl_documents AS A  INNER JOIN tbl_users AS B ON A.userid = B.userid WHERE B.companyid = ? ");
            $stmt->bind_param("s",$companyid);
            $stmt->execute();
            $stmt->get_result();
            $log['deleteddoccount'] = $stmt->affected_rows;

            //now clear users
            $stmt = $this->connection->prepare("DELETE A.* FROM tbl_users AS A  WHERE A.companyid = ? ");
            $stmt->bind_param("s",$companyid);
            $stmt->execute();
            $stmt->get_result();
            $log['deletedusercount'] = $stmt->affected_rows;  

            //now delete company
            $stmt = $this->connection->prepare("DELETE A.* FROM tbl_companies AS A  WHERE A.companyid = ? ");
            $stmt->bind_param("s",$companyid);
            $stmt->execute();
            $stmt->get_result();
  

            return($log);                     

        }

        public function setDisableUser($userid,$state)
        {
            $sql = "UPDATE tbl_users SET isactive = ? WHERE userid = ?";
            $stmt= $this->connection->prepare($sql);
            $stmt->bind_param("ss", $state, $userid);
            $stmt->execute();
            return true;              
        }




//API
        public function getAPIAuth($username,$password)
        {
            $stmt = $this->connection->prepare("SELECT userid,mappeduserid FROM api_users WHERE username=? AND password=?");
            $stmt->bind_param("ss", $username,$password);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
            //var_dump($result);            
        }

        public function getAPIBearer($userid)
        {
            $stmt = $this->connection->prepare("SELECT userid,bearer,issuetime FROM api_bearers WHERE userid=? ORDER BY issuetime DESC ");
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
            //var_dump($result);              
        }

        public function setAPIBearer($userid,$bearer,$timenow)
        {
            $stmt = $this->connection->prepare( " INSERT INTO api_bearers (userid,bearer,issuetime) VALUES (?,?,?)");           
            $stmt->bind_param("sss", $userid,$bearer,$timenow);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            return($rowid);
        }

        public function getBearerValidated($bearer) 
        {
            $timenow = time();
            $expires = $timenow - 300;
            $stmt = $this->connection->prepare(" SELECT count(bearer) AS BearerCounter, userid  FROM api_bearers WHERE bearer = ? AND issuetime > ? GROUP BY userid");
            $stmt->bind_param("ss", $bearer,$expires);
            $stmt->execute();    
            $result = $stmt->get_result()->fetch_assoc();         
            return $result;
            //           
        }  

        public function getApiUser($userid)
        {
            $stmt = $this->connection->prepare("SELECT userid, username,isactive,usertype,imageextractstarted,imageextractcomplete,textextractstarted, textextractcomplete,mappeduserid FROM api_users WHERE userid=? ");
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result; 
        }

        public function getAllApiUser()
        {
            $stmt = $this->connection->prepare("SELECT username,isactive,imageextractstarted,imageextractcomplete,textextractstarted, textextractcomplete FROM api_users ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result; 
        }        

        //check count for status code
        public function getDocCountByStatus($status)
        {
            $stmt = $this->connection->prepare(" SELECT count(txnid) AS totalcount FROM tbl_txn WHERE status= ?  ");
            $stmt->bind_param("s", $status);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
        }           


        //check count for status code
        public function getTxnUserID($txnid)
        {
            $stmt = $this->connection->prepare(" SELECT userid FROM tbl_txn WHERE txnid= ?  ");
            $stmt->bind_param("s", $txnid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc(); 
            return $result;
        }  

        public function setApiTxnLog($apiuserid,$txnid)
        {
            $stmt = $this->connection->prepare( " INSERT INTO api_tracking (apiuserid,txnid) VALUES (?,?)");           
            $stmt->bind_param("ss", $apiuserid,$txnid);
            $stmt->execute();
            $rowid = $stmt->insert_id;
            return($rowid);            
        }

        public function getApiCustomQueries($apiuserid)
        {
            $stmt = $this->connection->prepare("SELECT apiuserid, fieldname, searchpattern, searchexclude FROM api_customfields WHERE apiuserid = ? ");
            $stmt->bind_param("s", $apiuserid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
            return $result; 
        }

        public function getApiCustomSearch($userid,$pageno,$resultsperpage)
        {
            $selectSQL = " SELECT A.txnid, A.extract, E.imgstring, E.pagecount, B.lastchanged, B.emailfilename, C.displayname FROM tbl_extracts AS A ";

            //JOINS
            $joinSQL = " INNER JOIN tbl_txn AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C ON C.userid = B.userid INNER JOIN join_users_repos AS D ON C.userid = D.repoid INNER JOIN tbl_documents AS E ON A.txnid = E.txnid "; 

            //WHERE
            $whereSQL = " WHERE B.status = 3 ";

            $conditionSQL = "";
            $preparedvars = array();

            $allrepo = $this->getUsersSearchableRepos($userid);
            $conditionSQL .= " AND (";                    
            foreach($allrepo AS $repokey => $repovalue)
            {
                $conditionSQL .= " D.userid = ? OR ";
                array_push($preparedvars,(string)$repovalue['repoid']);                        
            }
            $conditionSQL = substr($conditionSQL, 0, -3);
            $conditionSQL .= ")";
                                                           
            //add page numbers to vars
            array_push($preparedvars, $pageno);
            array_push($preparedvars, $resultsperpage); 

            //var_dump($preparedvars);

            $types = str_repeat('s', count($preparedvars)); //types            

            //execute:::
            $SQL = $selectSQL . $joinSQL . $whereSQL . $conditionSQL . " LIMIT ?,?" ;  
                   
            $stmt = $this->connection->prepare($SQL);
            $stmt->bind_param($types, ...$preparedvars);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach($result AS $reskey => $resvalue)
            {
                $imgstring = base64_encode($resvalue['imgstring']);
                $result[$reskey]['imgstring'] = $imgstring;
                //var_dump($resvalue['txnid']);
            }

            //get the count
            $arraycount = count($preparedvars);
            unset($preparedvars[$arraycount-1]);
            unset($preparedvars[$arraycount-2]);
            $types = str_repeat('s', count($preparedvars)); 
            $countselect = " SELECT count(A.txnid) AS count FROM tbl_extracts AS A ";
            $countSQL = $countselect . $joinSQL . $whereSQL . $conditionSQL;  
            $countstmt = $this->connection->prepare($countSQL);
            $countstmt->bind_param($types, ...$preparedvars);
            $countstmt->execute();
            $countresult = $countstmt->get_result()->fetch_all(MYSQLI_ASSOC); 

            // var_dump($countresult);

            $result['count'] = $countresult[0]['count'];

            return $result;
        }


        public function getRegExSearch($userid,$searchregex,$pageno,$resultsperpage)
        {
            //SELECT element.
            $selectSQL = " SELECT A.txnid, A.extract, E.imgstring, E.pagecount, B.lastchanged, B.emailfilename, C.displayname FROM tbl_extracts AS A ";

            //JOINS
            $joinSQL = " INNER JOIN tbl_txn AS B ON A.txnid = B.txnid INNER JOIN tbl_users AS C ON C.userid = B.userid INNER JOIN join_users_repos AS D ON C.userid = D.repoid INNER JOIN tbl_documents AS E ON A.txnid = E.txnid "; 

            //WHERE
            $whereSQL = " WHERE B.status = 3 ";



            //Our conditions
            $conditionSQL = "";
            $preparedvars = array();
            //repo id 
            //
            
        //REGEX Where
                $conditionSQL = " AND (";  
                $searchdatearray = array();
                //regex format either dd/mm/yyyy or dd-mm-yyyy with as many spaces as needed prefox 0 optional on numeric day or month
                $conditionSQL .= "  A.extract REGEXP ? OR ";
                array_push($preparedvars, $searchregex); 

                $conditionSQL = substr($conditionSQL, 0, -3);
                $conditionSQL .= ")";

            //find all repo
            $allrepo = $this->getUsersSearchableRepos($userid);
            $conditionSQL .= " AND (";                    
            foreach($allrepo AS $repokey => $repovalue)
            {
                $conditionSQL .= " D.userid = ? OR ";
                array_push($preparedvars,(string)$repovalue['repoid']);                        
            }
            $conditionSQL = substr($conditionSQL, 0, -3);
            $conditionSQL .= ")";
                                                           
            //add page numbers to vars
            array_push($preparedvars, $pageno);
            array_push($preparedvars, $resultsperpage); 

            //var_dump($preparedvars);

            $types = str_repeat('s', count($preparedvars)); //types            

            //execute:::
            $SQL = $selectSQL . $joinSQL . $whereSQL . $conditionSQL . " LIMIT ?,?" ;  
                   
            $stmt = $this->connection->prepare($SQL);
            $stmt->bind_param($types, ...$preparedvars);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach($result AS $reskey => $resvalue)
            {
                $imgstring = base64_encode($resvalue['imgstring']);
                $result[$reskey]['imgstring'] = $imgstring;
                //var_dump($resvalue['txnid']);
            }

            //get the count
            $arraycount = count($preparedvars);
            unset($preparedvars[$arraycount-1]);
            unset($preparedvars[$arraycount-2]);
            $types = str_repeat('s', count($preparedvars)); 
            $countselect = " SELECT count(A.txnid) AS count FROM tbl_extracts AS A ";
            $countSQL = $countselect . $joinSQL . $whereSQL . $conditionSQL;  
            $countstmt = $this->connection->prepare($countSQL);
            $countstmt->bind_param($types, ...$preparedvars);
            $countstmt->execute();
            $countresult = $countstmt->get_result()->fetch_all(MYSQLI_ASSOC); 

            // var_dump($countresult);

            $result['count'] = $countresult[0]['count'];


            return ($result);   
        }


}



?>

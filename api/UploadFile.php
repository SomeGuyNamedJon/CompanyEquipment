<?php

    //usage response
    if(isset($_REQUEST['usage'])){
        updateHeader('application/json', '200 OK');
        $status = "Usage";
        $msg = "This endpoint accepts files for upload to server filesystem and update database table if need be";
        $data = array(
            'Args' => array(
                'usage' => 'Returns usage information; overrides all other arguments',
                'did=Integer' => 'Device ID: id of device to bind file to; this or serial must be set',
                'serial=(SN-)(32char hex)' => 'Serial Number: serial of device to bind file to; alternative to did',
                'mode=(safe|append|overwrite|default)' => 'Specifies how to handle filename collisions; Default behavior overwrite file if file does not exist for did allowing two devices to share a link to the new file, different from overwrite mode which will overwrite regardless',
                'file=@FILE' => 'File to upload; must be set; only pdf, jpg, and png accepted'
            ),
            'Additional Info' => 'Safe mode never writes file if filename exists in database regardless of device database file is linked to; '
            .'Append mode writes file as filename(n).ext as appropriate; Overwrite mode overwrites file regardless (be careful with this one). '
            .'Obviously a file must be sent to endpoint for a file to be uploaded. In case where both did and serial set, did takes precedence.'
        );
        echo buildResponse($status, $msg, $data);
        die();
    }

    //get mode
    if(isset($_REQUEST['mode']))
        $mode = strtolower($_REQUEST['mode']);

    if(isset($_REQUEST['did'])){
        //get device id, make sure not null and numeric
        $did = $_REQUEST['did'];
        if($did == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Device ID must not be NULL";
            echo buildResponse($status, $msg, null);
            die();
        }
        
        if(!is_numeric($did)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Device ID must be numeric";
            echo buildResponse($status, $msg, null);
            die();
        }
        
    }else if(isset($_REQUEST['serial'])){
        //open database for serial request
        $dblink = dbconnect("equipment");
        //make sure not null
        if($_REQUEST['serial'] == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Serial Number must not be null";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //serial set to lower to be easier to work with and allow caps hex nums to be supplied
        $serial = strtolower($_REQUEST['serial']);
        //regex to ensure hex num is indeed a valid hex num
        $hexRegex = "/^[a-f0-9]{32}$/";

        //if serial begins with SN- trim that bit off
        if(strstr($serial, 'sn-'))
            $serial = substr($serial, 3);

        //hex num check
        if(!preg_match($hexRegex, $serial)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Serial Number SN-$serial does not match format$newTypeMsg$newVendorMsg";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //simply get did from serial number and everything else is the same for this block
        $sql = "select `id` from `devices` where `serial#`='SN-$serial'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");
        //if zero rows, no files found for device
        if($result->num_rows == 0){
            updateHeader('application/json', '200 OK');
            $status = "Not Found";
            $msg = "Serial Number: SN-$serial does not exist in database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        $device_data = $result->fetch_array(MYSQLI_ASSOC);
        $did = $device_data['id'];
        $dblink->close(); //close database here as to not cause error when reopened later in code

    }else{
        updateHeader('application/json', '200 OK');
        $status = "Invalid Data";
        $msg = "No device identifier set, either supply Device ID or Serial Number";
        echo buildResponse($status, $msg, null);
        die();
    }

    //if file size not greater than zero then there is no file or something fishy going on
    //either way we shouldn't accept it and respond "No File"
    if($_FILES['file']['size'] <= 0){
        updateHeader('application/json', '200 OK');
        $status = "No File";
        $msg = "File must be provided to upload";
        echo buildResponse($status, $msg, null);
        die();
    }

    //file information
    $uploadDir="/var/www/html/files";
    $tmpName = $_FILES['file']['tmp_name'];
    $fileSize = $_FILES['file']['size'];
    $uploadName = $_FILES['file']['name'];
    $fileType = $_FILES['file']['type'];

    //if file type isn't jpg, png, or pdf do not accept; respond "Invalid File"
    if($fileType != 'image/jpeg' && $fileType != 'image/png' && $fileType != 'application/pdf'){
        updateHeader('application/json', '200 OK');
        $status = "Invalid File";
        $msg = "File must be pdf, jpg, or png";
        echo buildResponse($status, $msg, null);
        die();
    }

    //look to see if filename exists in database, very likely same file or updated file
    //we will do different things with this information based on the mode
    $dblink = dbconnect("equipment");
    $sql = "select * from `device_files` where `filename` = '$uploadName'";
    $file_result = $dblink->query($sql) or die("Something went wrong with $sql");

    //default filename
    $filename = $uploadName;

    //mode only kicks into effect if a file of same name is found
    if($file_result->num_rows > 0){
        
        switch($mode){
            case 'overwrite':
                //just overwrite file and die with response if file already linked to did
                //using while incase multiple entries exist and device we are uploading for
                //is a later entry of the same file
                while($file_data = $file_result->fetch_array(MYSQLI_ASSOC)){
                    if($file_data['device_id'] == $did){
                        $location = "$uploadDir/$filename";
                        move_uploaded_file($tmpName, $location);

                        $fid = $file_data['file_id'];

                        updateHeader('application/json', '200 OK');
                        $status = "OK";
                        $msg = "Overwrite Mode: File Overwritten";
                        $data = array(
                            'file_id' => $fid,
                            'location' => $location
                        );
                        echo buildResponse($status, $msg, $data);
                        $dblink->close();
                        die();
                    }
                }
                //otherwise just hit break
                break;
            case 'append':
                //rewrite filename to include number
                switch($fileType){
                    case 'image/jpeg':
                        $extension = '.jpg';
                        break;
                    case 'image/png':
                        $extension = '.png';
                        break;
                    case 'application/pdf':
                        $extension = '.pdf';
                        break;
                }
                $basename = shell_exec("basename -z $uploadName $extension");
                $basename = preg_replace('/[\x00-\x1F\x7F]/', '', $basename);
                $filename = shell_exec("cd $uploadDir && echo -n $basename\(`ls $basename* | wc -l`\)$extension");
                break;
            case 'safe':
                //die with response for safe mode
                updateHeader('application/json', '200 OK');
                $status = "Safe Mode: File Exists";
                $msg = "File already exists in database, exited without writing";
                echo buildResponse($status, $msg, null);
                $dblink->close();
                die();
                break;
            default:
                //only die with response for default if the file that exists belongs to the same device
                //same thing about while loop from overwrite applies here
                while($file_data = $file_result->fetch_array(MYSQLI_ASSOC)){
                    if($file_data['device_id'] == $did){
                        updateHeader('application/json', '200 OK');
                        $status = "Default Mode: File Exists";
                        $msg = "Device ID: $did is already linked to a version of file, use overwrite mode if you wish to overwrite it";
                        echo buildResponse($status, $msg, null);
                        $dblink->close();
                        die();
                    }
                }
                break;
        }
    }

    //location set here in case filename changed by append mode
    $location = "$uploadDir/$filename";
    
    //insert new entry into table
    $sql = "insert into `device_files` (`filename`,`location`,`type`,`size`,`device_id`) values ('$filename','$location','$fileType','$fileSize','$did')";
    $result = $dblink->query($sql) or die("Something went wrong with $sql");
    //possible bug for fid dealing with servicing multiple requests since this
    //just returns last id inserted into table, but can't be bothered to figure out a fix for now
    $fid = $dblink->insert_id;

    //move file, (will rewrite existing file for default and overwrite modes)
    move_uploaded_file($tmpName, $location);

    //respond with file id just inserted
    updateHeader('application/json', '200 OK');
    $status = "OK";
    $msg = "Upload Success";
    $data = array(
        'file_id' => $fid,
        'location' => $location
    );
    echo buildResponse($status, $msg, $data);
    $dblink->close();
    die();

?>
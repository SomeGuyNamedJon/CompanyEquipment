<?php

    //usage response
    if(isset($_REQUEST['usage'])){
        updateHeader('application/json', '200 OK');
        $status = "Usage";
        $msg = "This endpoint returns device information based on a Device ID";
        $data = array(
            'Args' => array(
                'usage' => 'Returns usage information; overrides all other arguments',
                'did=Integer' => 'Device ID: id of device to get file(s) information of; either this, serial, or fid must be set',
                'fid=Integer' => 'File ID: id of specific file to get information of; either this, serial, or did must be set',
                'serial=(SN-)(32char hex)' => 'Serial Number: serial of device to get file(s) information of; alternative to did',
                'mode=(data|link|download)' => 'Specifies format of information returned; defaults to data mode if not set or set to unrecognized string'
            ),
            'Additional Info' => 'At least a did or fid must be set, but both cannot be set.' 
            .' For modes: data returns information of file(s) from the database; link returns File ID, Filename, and a link to the file'
            .'; download directly downloads the file(s) in question, if invoked through did argument it will return the file(s) as a zip archive. '
            .'For getting files through a specific device, did supercedes serial. In the case that both are set serial will simply be ignored.'
        );
        echo buildResponse($status, $msg, $data);
        die();
    }

    //url for link mode
    $url = 'https://'.$_SERVER['HTTP_HOST'].'/files/';
    
    //set mode
    if(isset($_REQUEST['mode']))
        $mode = strtolower($_REQUEST['mode']);

    //ensure at least fid or did set but not both, respond appropriately for either undesired case
    if(!isset($_REQUEST['fid']) && !isset($_REQUEST['did']) && !isset($_REQUEST['serial'])){
        updateHeader('application/json', '200 OK');
        $status = "Invalid Query";
        $msg = "Please provide either a File ID for specific file or Device ID/Serial Number for files related to a device";
        echo buildResponse($status, $msg, null);
        die();
    }elseif(isset($_REQUEST['fid']) && (isset($_REQUEST['did']) || isset($_REQUEST['serial']))){
        updateHeader('application/json', '200 OK');
        $status = "Invalid Query";
        $msg = "Please provide ONLY File ID or Device ID/Serial Number, not both";
        echo buildResponse($status, $msg, null);
        die();
    }

    $dblink = dbconnect("equipment");

    //code for viewing files based on device id
    if(isset($_REQUEST['did']) || isset($_REQUEST['serial'])){
        if(isset($_REQUEST['did'])){
            $did = $_REQUEST['did'];

            //make sure did not null and numeric
            if($did == null){
                updateHeader('application/json', '200 OK');
                $status = "Invalid Data";
                $msg = "Device ID cannot be NULL";
                echo buildResponse($status, $msg, null);
                $dblink->close();
                die();
            }

            if(!is_numeric($did)){
                updateHeader('application/json', '200 OK');
                $status = "Invalid Data";
                $msg = "Device ID must be numeric";
                echo buildResponse($status, $msg, null);
                $dblink->close();
                die();
            }
        }else{
            //else needs no condition, we already know serial is set through top level if
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
        }

        //query data based on did
        $sql = "select * from `device_files` where `device_id` = $did";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");

        //if zero rows, no files found for device
        if($result->num_rows == 0){
            updateHeader('application/json', '200 OK');
            $status = "Not Found";
            $msg = "Device ID: $did has no associated files in the database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //if mode download, set up start of zip command
        if($mode == "download"){
            $FileLoc = "/var/www/html/files/";
            $zipName = 'device'.$did.'_files.zip';
            $zipCmd = "cd $FileLoc && zip $zipName";
        }

        //iterate through file data and set up data for response based on mode
        while($file_data = $result->fetch_array(MYSQLI_ASSOC)){
            switch($mode){
                case 'download':
                    //concatenate all files to the start of zip command
                    $zipCmd .= ' '."'".$file_data['filename']."'";
                    break;
                case 'link':
                    //populate data array with associative arrays containing fid, filename, and link
                    //filenames have to have spaces replaced with %20 to be clickable
                    $data[] = array(
                        'File ID' => $file_data['file_id'],
                        'Filename' => $file_data['filename'],
                        'Link' => $url.str_replace(' ', '%20', $file_data['filename'])
                    );
                    break;
                case 'data':
                default:
                    //for default "data" case just start amassing data as is
                    $data[] = array(
                        'File ID' => $file_data['file_id'],
                        'Filename' => $file_data['filename'],
                        'Location' => $file_data['location'],
                        'Size' => $file_data['size'],
                        'Type' => $file_data['type']
                    );
                    break;
            }
        }

        //execution based of responses based on mode done outside of loop here
        switch($mode){
            case 'download':
                //execute shell command and return output, if error respond appropriately
                $out = shell_exec($zipCmd);
                if(strstr($out, "error")){
                    updateHeader('application/json', '500 Internal Server Error');
                    $status = "Error";
                    $msg = str_replace(array("\t", "\r", "\n"), ' ', $out);
                    echo buildResponse($status, $msg, null);
                    break;
                }else{
                    updateHeader('application/zip', '200 OK');
                    header('Content-Length: '.filesize($FileLoc.$zipName));
                    header('Content-Disposition: attachment; filename='.$zipName);
                    readfile($FileLoc.$zipName);
                    //file deleted right after it is read
                    unlink($FileLoc.$zipName);
                    break;
                }
            //just build responses for other two modes
            case 'link':
                updateHeader('application/json', '200 OK');
                $status = "OK";
                $msg = "Response sent in Link mode";
                echo buildResponse($status, $msg, $data);
                break;
            case 'data':
            default:
                updateHeader('application/json', '200 OK');
                $status = "OK";
                $msg = "Response sent in Data mode";
                echo buildResponse($status, $msg, $data);
                break;
        }

        //universal die and close for did block
        $dblink->close();
        die();
    }
    
    //code for viewing files based on file id
    if(isset($_REQUEST['fid'])){
        $fid = $_REQUEST['fid'];

        //make sure fid not null and is numeric
        if($fid == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "File ID cannot be NULL";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        if(!is_numeric($fid)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "File ID must be numeric";
            echo buildResponse($status, $msg, null);
            die();
        }

        //query file based on file id
        $sql = "select * from `device_files` where `file_id` = $fid";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");

        //if no rows, no file
        if($result->num_rows == 0){
            updateHeader('application/json', '200 OK');
            $status = "Not Found";
            $msg = "File ID: $fid not found in database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //no loop needed since ostensibly one file, file_id is primary key
        $data = $result->fetch_array(MYSQLI_ASSOC);

        switch($mode){
            case 'download':
                //update header based on file type and read file
                updateHeader($data['type'], '200 OK');
                header('Content-Transfer-Encoding: Binary');
                header('Content-Length: '.filesize($data['location']));
                header('Content-Disposition: attachment; filename='.$data['filename']);
                readfile($data['location']);
            case 'link':
                //create link response
                updateHeader('application/json', '200 OK');
                $status = "OK";
                $msg = "Response sent in Link mode";
                $link_data = array(
                    'Device ID' => $data['device_id'],
                    'Filename' => $data['filename'],
                    'Link' => $url.str_replace(' ', '%20', $data['filename'])
                );
                echo buildResponse($status, $msg, $link_data);
                break;
            case 'data':
            default:
                //just respond with data
                updateHeader("application/json", "200 OK");
                $status = "OK";
                $msg = "Response sent in Data mode";
                $file_data = array(
                    'Device ID' => $data['device_id'],
                    'Filename' => $data['filename'],
                    'Location' => $data['location'],
                    'Size' => $data['size'],
                    'Type' => $data['type']
                );
                echo buildResponse($status, $msg, $file_data);
                break;
        }

        $dblink->close();
        die();
    }    
?>
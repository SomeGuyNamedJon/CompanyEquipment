<?php

    //usage response
    if(isset($_REQUEST['usage'])){
        updateHeader('application/json', '200 OK');
        $status = "Usage";
        $msg = "This endpoint returns device information based on a Device ID";
        $data = array(
            'Args' => array(
                'usage' => 'Returns usage information; overrides all other arguments',
                'did=Integer' => 'Device ID: id of device to get information of; either this or serial must be set',
                'serial=(SN-)(32char hex)' => 'Serial Number: serial of device to get information of; either this or did must be set'
            ),
            'Additional Info' => 'Either did or serial must be set, but not both. Serial is validated to make sure is valid hex number. '
            .'Serial\'s \'SN-\' is optional. No check to see if did is negative, but obviously negative IDs will always return \'Not Found\''
        );
        echo buildResponse($status, $msg, $data);
        die();
    }
    
    if(isset($_REQUEST['did']) && isset($_REQUEST['serial'])){
        updateHeader('application/json', '200 OK');
        $status = "Invalid Data";
        $msg = "Please only use Device ID or Serial Number";
        echo buildResponse($status, $msg, null);
        die();
    }

    if(isset($_REQUEST['serial'])){
        if($_REQUEST['serial'] == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Serial Number must not be null";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //set serial to lowercase to be easier to work with and prepped for final insertion
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
            $msg = "Serial Number SN-$serial does not match format";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //query for device based on serial
        $dblink = dbconnect("equipment");
        $sql="select `id`,`type`,`vendor`,`active` from `devices` where `serial#` = 'SN-$serial'";
        $result=$dblink->query($sql) or die("Something went wrong with $sql");

        $device=$result->fetch_array(MYSQLI_ASSOC);

        //if rows is 1 then we found the device and return as response, otherwise respond "Not Found"
        if($result->num_rows == 1){
            updateHeader('application/json', '200 OK');
            $status = "OK";
            $msg = "";
            $data = array(
                'Device ID' => $device['id'],
                'Device Vendor' => $device['vendor'],
                'Device Type' => $device['type'],
                'Active' => $device['active']
            );
            echo buildResponse($status, $msg, $data);
        }else{
            updateHeader('application/json', '200 OK');
            $status = "Not Found";
            $msg = "Serial Number: SN-$serial not in database";
            echo buildResponse($status, $msg, null);
        }
        $dblink->close();
        die();
    }

    if(isset($_REQUEST['did'])){
        //get did, respond and die if NULL or not numeric
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

        //query for device based on id
        $dblink = dbconnect("equipment");
        $sql="select `serial#`,`type`,`vendor`,`active` from `devices` where `id` = '$did'";
        $result=$dblink->query($sql) or die("Something went wrong with $sql");

        //if rows is 1 then we found the device and return as response, otherwise respond "Not Found"
        if($result->num_rows == 1){
            $device=$result->fetch_array(MYSQLI_ASSOC);

            updateHeader('application/json', '200 OK');
            $status = "OK";
            $msg = "";
            $data = array(
                'Serial Number' => $device['serial#'],
                'Device Vendor' => $device['vendor'],
                'Device Type' => $device['type'],
                'State' => $device['active'] ? 'active' : 'deactivated'
            );
            echo buildResponse($status, $msg, $data);
        }else{
            updateHeader('application/json', '200 OK');
            $status = "Not Found";
            $msg = "Device ID: $did not in database";
            echo buildResponse($status, $msg, null);
        }
        $dblink->close();
        die();
    }

    updateHeader('application/json', '200 OK');
    $status = "Invalid Data";
    $msg = "No valid arguments supplied";
    echo buildResponse($status, $msg, null);
    die();
?>
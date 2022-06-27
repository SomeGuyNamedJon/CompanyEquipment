<?php
    function validateType($input, $existing){
        $type = strtolower($input);

        if($type == $existing)
            return $existing;

        if($type == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Type cannot be null";
            echo buildResponse($status, $msg, null);
            die();
        }

        //if contains non-alpha characters respond and die
        if(!preg_match("/^[a-zA-Z ]*$/", $type)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Type must be alpha only";
            echo buildResponse($status, $msg, null);
            die();
        }

        //make sure type is a valid type in database
        $dblink = dbconnect("equipment");
        $sql = "select * from `device_types` where `type`='$type'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");

        if($result->num_rows < 1){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Type $type does not exist in database, consider creating it through CreateDevice endpoint";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        $dblink->close();
        return $type;
    }

    function validateVendor($input, $existing){
        $vendor = $input;

        if($vendor == $existing)
            return $existing;

        if($vendor == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Vendor cannot be null";
            echo buildResponse($status, $msg, null);
            die();
        }

        //if contains non-alpha characters respond and die
        if(!preg_match("/^[a-zA-Z ]*$/", $vendor)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Vendor must be alpha only";
            echo buildResponse($status, $msg, null);
            die();
        }

        //make sure type is a valid vendor in database
        $dblink = dbconnect("equipment");
        $sql = "select * from `device_vendors` where `vendor`='$vendor'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");

        if($result->num_rows < 1){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Vendor $vendor does not exist in database, consider creating it through CreateDevice endpoint";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        $dblink->close();
        return $vendor;
    }

    function validateState($input){
        $state = $input;
        //state should not be null if set at all
        if($state == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "State must not be null";
            echo buildResponse($status, $msg, null);
            die();
        }

        //state must be one of these inputs, case insensitive
        if($state != "1" && $state != "0" && strcasecmp($state, "true") != 0 
            && strcasecmp($state, "false") != 0 && strcasecmp($state, "active") != 0
            && strcasecmp($state, "deactivated") != 0)
        {
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "State: $state does not match any acceptable input";
            echo buildResponse($status, $msg, null);
            die();
        }

        //set state to lower for switch
        $state = strtolower($state);

        //set state to the appropriate value, 1 or 0
        switch($state){
            case "true":
            case "1":
            case "active":
                $state = 1;
                break;
            case "false":
            case "0":
            case "deactivated":
                $state = 0;
                break;
        }

        return $state;
    }

    function validateSerial($input, $existing){
        $serial = strtolower($input);
        
        //serial cannot be null
        if($serial == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Serial Number must not be null";
            echo buildResponse($status, $msg, null);
            die();
        }

        //regex to ensure hex num is indeed a valid hex num
        $hexRegex = "/^[a-f0-9]{32}$/";

        //if serial begins with SN- trim that bit off
        if(strstr($serial, 'sn-'))
            $serial = substr($serial, 3);

        if($serial == $existing)
            return $existing;

        //hex num check
        if(!preg_match($hexRegex, $serial)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Serial Number SN-$serial does not match format";
            echo buildResponse($status, $msg, null);
            die();
        }

        //make sure serial not already in use by another device, inform user what device id is using that serial if in use
        $dblink = dbconnect("equipment");
        $sql = "select `id`,`serial#` from `devices` where `serial#`='SN-$serial'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");
        
        if($result->num_rows > 0){
            $data = $result->fetch_array(MYSQLI_ASSOC);
            $did = $data['id'];

            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Serial Number SN-$serial already attributed to other device: $did";
            echo buildResponse($status, $msg, $data);
            $dblink->close();
            die();
        }

        $dblink->close();
        return $serial;
    }

    //usage response
    if(isset($_REQUEST['usage'])){
        updateHeader('application/json', '200 OK');
        $status = "Usage";
        $msg = "This endpoint returns device information based on a Device ID";
        $data = array(
            'Args' => array(
                'usage' => 'Returns usage information; overrides all other arguments',
                'did=Integer' => 'Device ID: id of device to update; either this or serial must be set',
                'serial=(SN-)(32char hex)' => 'Serial Number: updated value of serial; alternatively serial of device to update if did not set',
                'type=String' => 'Device Type: updated value of type; can not contain numbers or special characters',
                'vendor=String' => 'Device Vendor: updated value of vendor; can not contain numbers or special characters',
                'state=Boolean|(active|deactivated)' => 'Device State: updated value of state',
                'newSerial=(SN-)(32char hex)' => 'Updated Serial Number: in the case of identifying device to be updated by serial, allows serial to still be updated'

            ),
            'Additional Info' => "'type', 'vendor', 'state', 'newSerial' all completely optional arguments. 'serial' is also optional if device is being identified by id "
            ."Of course if no values to update are set, then nothing is done. If device identified by id and both 'serial' and 'newSerial' are set then 'serial' takes precedent. "
        );
        echo buildResponse($status, $msg, $data);
        die();
    }

    if(!isset($_REQUEST['did']) && !isset($_REQUEST['serial'])){
        updateHeader('application/json', '200 OK');
        $status = "Invalid Data";
        $msg = "No way to indentify device to update; either 'did' or 'serial' must be set";
        echo buildResponse($status, $msg, null);
        die();
    }

    if(isset($_REQUEST['did'])){

        //did id must not be null or non-numeric if supplied
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

        //if no other values supplied, nothing to do
        if(!isset($_REQUEST['serial']) && !isset($_REQUEST['type']) 
        && !isset($_REQUEST['vendor']) && !isset($_REQUEST['newSerial']) && !isset($_REQUEST['state'])){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "No values to update supplied";
            echo buildResponse($status, $msg, null);
            die();
        }

        //query for device based on id
        $dblink = dbconnect("equipment");
        $sql="select `serial#`,`type`,`vendor`,`active` from `devices` where `id` = '$did'";
        $result=$dblink->query($sql) or die("Something went wrong with $sql");

        //if rows is 1 then we found the device, set all update values to default, otherwise respond "Not Found"
        if($result->num_rows == 1){
            $device=$result->fetch_array(MYSQLI_ASSOC);

            $newSerial = substr($device['serial#'],3);
            $newVendor = $device['vendor'];
            $newType = $device['type'];
            $newState = $device['active'];
        }else{
            updateHeader('application/json', '200 OK');
            $status = "Not Found";
            $msg = "Device ID: $did not in database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }
        
        //validate all supplied inputs
        if(isset($_REQUEST['type']))
            $newType = validateType($_REQUEST['type'], $newType);
        
        if(isset($_REQUEST['vendor']))
            $newVendor = validateVendor($_REQUEST['vendor'], $newVendor);

        if(isset($_REQUEST['state']))
            $newState = validateState($_REQUEST['state'], $newState);

        if(isset($_REQUEST['serial']))
            $newSerial = validateSerial($_REQUEST['serial'], $newSerial);
        else if(isset($_REQUEST['newSerial']))
            $newSerial = validateSerial($_REQUEST['newSerial'], $newSerial);

        //update device
        $sql = "update `devices` set `type`='$newType', `vendor`='$newVendor', `active`=$newState, `serial#`='SN-$newSerial' where `id` = $did";
        $dblink->query($sql) or die("Something went wrong with $sql");

        updateHeader('application/json', '200 OK');
        $status = "OK";
        $msg = "Device Updated Successfully";
        $data = array(
            'Device ID' => $did,
            'Serial Number' => 'SN-'.$newSerial,
            'Device Vendor' => $newVendor,
            'Device Type' => $newType,
            'State' => $newState ? 'active' : 'deactivated'
        );
        echo buildResponse($status, $msg, $data);
        $dblink->close();
        die();   

    }else if(isset($_REQUEST['serial'])){

        $serial = strtolower($_REQUEST['serial']);
        if($serial == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Device ID must not be NULL";
            echo buildResponse($status, $msg, null);
            die();
        }
        
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
            die();
        }

        //if no other values supplied, nothing to do
        if(!isset($_REQUEST['newSerial']) && !isset($_REQUEST['type']) 
        && !isset($_REQUEST['vendor']) && !isset($_REQUEST['state'])){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "No values to update supplied";
            echo buildResponse($status, $msg, null);
            die();
        }

        //query for device based on id
        $dblink = dbconnect("equipment");
        $sql="select `id`,`type`,`vendor`,`active` from `devices` where `serial#` = 'SN-$serial'";
        $result=$dblink->query($sql) or die("Something went wrong with $sql");

        //if rows is 1 then we found the device, set all update values to default, otherwise respond "Not Found"
        if($result->num_rows == 1){
            $device=$result->fetch_array(MYSQLI_ASSOC);

            $did = $device['id'];
            $newVendor = $device['vendor'];
            $newType = $device['type'];
            $newState = $device['active'];
        }else{
            updateHeader('application/json', '200 OK');
            $status = "Not Found";
            $msg = "Serial Number: SN-$serial not in database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //validate all supplied inputs
        if(isset($_REQUEST['type']))
            $newType = validateType($_REQUEST['type'], $newType);
        
        if(isset($_REQUEST['vendor']))
            $newVendor = validateVendor($_REQUEST['vendor'], $newVendor);

        if(isset($_REQUEST['state']))
            $newState = validateState($_REQUEST['state'], $newState);

        if(isset($_REQUEST['newSerial']))
            $newSerial = validateSerial($_REQUEST['newSerial'], $newSerial);
        else
            $newSerial = $serial;

        //update device
        $sql = "update `devices` set `type`='$newType', `vendor`='$newVendor', `active`=$newState, `serial#`='SN-$newSerial' where `id` = $did";
        $dblink->query($sql) or die("Something went wrong with $sql");

        updateHeader('application/json', '200 OK');
        $status = "OK";
        $msg = "Device Updated Successfully";
        $data = array(
            'Device ID' => $did,
            'Serial Number' => 'SN-'.$newSerial,
            'Device Vendor' => $newVendor,
            'Device Type' => $newType,
            'State' => $newState ? 'active' : 'deactivated'
        );
        echo buildResponse($status, $msg, $data);
        $dblink->close();
        die();
    }
?>
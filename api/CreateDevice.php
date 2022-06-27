<?php
    //usage response
    if(isset($_REQUEST['usage'])){
        updateHeader('application/json', '200 OK');
        $status = "Usage";
        $msg = "This endpoint is used to create new devices as well as device types and vendors";
        $data = array(
            'Args' => array(
                'usage' => 'Returns usage information; overrides all other arguments',
                'type=String' => 'Device Type for newly created device; can not contain numbers or special characters',
                'vendor=String' => 'Device Vendor for newly created device; can not contain numbers or special characters',
                'serial=(SN-)(32char hex)' => 'Serial Number for newly created device; must be unique; leading \'SN-\' optional',
                'state=Boolean|(active|deactivated)' => 'Device State for newly create device; defaults to \'active\' if not set',
                'newType' => 'Flag dictating intent to create new type; cannot create existing type',
                'newVendor' => 'Flag dictating intent to create new vendor; cannot create existing vendor'
            ),
            'Additional Info' => 'Type, vendor, and serial are non-optional for creating a new device; however, they are not required '
            .'for creating a new type or new vendor. The creation of new types and new vendors is attempted before the creation of a new device; '
            .'furthermore, only the serial argument dictates the intent of creating a new device, so it can be safely omitted if the intent is only '
            .'to create a new type and/or vendor. Error checking for new type and vendor done before either is actually created, so if both are being '
            .'created and there is an error with one, neither will be created. If the intent is to only create new type and/or vendor \'type\' and '
            .'\'vendor\' arguments can be substituted by feeding the input into \'newType\' and \'newVendor\' respectively instead. Case for type '
            .'arguments is inconsequential as it will be entered into the database all lowercase. Vendor must be cased properly, but still will not allow '
            .'new vendors to be created if they match an existing vendor disregarding case. New type and vendor creation will succeed even if device creation fails. '
            .'\'type\' and \'vendor\' arguments mandatory for device creation, even when creating new ones.'
        );
        echo buildResponse($status, $msg, $data);
        die();
    }

    //get type
    if(isset($_REQUEST['type']))
        $type = $_REQUEST['type'];

    //get vendor
    if(isset($_REQUEST['vendor']))
        $vendor = $_REQUEST['vendor'];

    //regex to check that type and vendor are alpha only for new entries
    $alphaRegex = "/^[a-zA-Z ]*$/";

    $dblink = dbconnect("equipment");

    //new type validation
    if(isset($_REQUEST['newType'])){
        $newType = $_REQUEST['newType'];
        //if newtype itself is null, then the arg must be in type, if that is null then respond and die
        if($newType == null){
            if(!isset($_REQUEST['type']) || $type == null){
                updateHeader('application/json', '200 OK');
                $status = "Invalid Data";
                $msg = "New Type flag set while no data for new type was provided";
                echo buildResponse($status, $msg, null);
                $dblink->close();
                die();
            }else{
                $newType = $type;
            }
        }else{
            //if newtype itself isn't null, but type is also set, make sure they match
            if(isset($_REQUEST['type'])){
                if(strcasecmp($newType, $type) != 0){
                    updateHeader('application/json', '200 OK');
                    $status = "Invalid Data";
                    $msg = "New Type has conflicting inputs";
                    echo buildResponse($status, $msg, null);
                    $dblink->close();
                    die();
                }
            }
        }

        //if contains non-alpha characters respond and die
        if(!preg_match($alphaRegex, $newType)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "New Type must be alpha only";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //if type already in database, respond and die
        $sql = "select * from `device_types` where `type` like '$newType'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");

        if($result->num_rows > 0){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "New Type: $newType already exists in database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }
    }

    //newvendor validation, same logic as type
    if(isset($_REQUEST['newVendor'])){
        $newVendor = $_REQUEST['newVendor'];
        if($newVendor == null){
            if(!isset($_REQUEST['vendor']) || $vendor == null){
                updateHeader('application/json', '200 OK');
                $status = "Invalid Data";
                $msg = "New Vendor flag set while no data for new vendor was provided";
                echo buildResponse($status, $msg, null);
                $dblink->close();
                die();
            }else{
                $newVendor = $vendor;
            }
        }else{
            if(isset($_REQUEST['vendor'])){
                if(strcasecmp($newVendor, $vendor) != 0){
                    updateHeader('application/json', '200 OK');
                    $status = "Invalid Data";
                    $msg = "New Vendor has conflicting inputs";
                    echo buildResponse($status, $msg, null);
                    $dblink->close();
                    die();
                }
            }
        }

        if(!preg_match($alphaRegex, $newVendor)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "New Vendor must be alpha only";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        $sql = "select * from `device_vendors` where `vendor` like '$newVendor'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");

        if($result->num_rows > 0){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "New Vendor: $newVendor already exists in database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }
    }

    //set msg variables for new type and vendor creation, will be appended to further messages
    $newTypeMsg = "";
    $newVendorMsg = "";

    //if newtype is set, insert new type, validation has already been done
    if(isset($_REQUEST['newType'])){
        $newType = strtolower($newType);
        $sql = "insert into `device_types` (`type`) values ('$newType')";
        $dblink->query($sql) or die("Something went wrong with $sql");
        $newTypeMsg = "; New Type: $newType successfully created";
    }

    //if newvendor is set, insert new vendor, validation has already been done
    if(isset($_REQUEST['newVendor'])){
        $sql = "insert into `device_vendors` (`vendor`) values ('$newVendor')";
        $dblink->query($sql) or die("Something went wrong with $sql");
        $newTypeMsg = "; New Vendor: $newVendor successfully created";
    }

    //serial being set implies intent to create new device, all logic related to that exists in this block
    if(isset($_REQUEST['serial'])){
        //serial cannot be null
        if($_REQUEST['serial'] == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Serial Number must not be null$newTypeMsg$newVendorMsg";
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
            $msg = "Serial Number SN-$serial does not match format$newTypeMsg$newVendorMsg";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }

        //make sure serial not already in use by another device, inform user what device id is using that serial if in use
        $sql = "select `id`,`serial#` from `devices` where `serial#`='SN-$serial'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");
        
        if($result->num_rows > 0){
            $data = $result->fetch_array(MYSQLI_ASSOC);
            $did = $data['id'];

            updateHeader('application/json', '200 OK');
            $status = "Device Exists";
            $msg = "Serial Number SN-$serial already attributed to device: $did$newTypeMsg$newVendorMsg";
            echo buildResponse($status, $msg, $data);
            $dblink->close();
            die();
        }

        //ensure type is set and not null
        if(!isset($_REQUEST['type']) || $type == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Type argument must be supplied/not null$newTypeMsg$newVendorMsg";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }else{
            //make sure type is a valid type in database
            $sql = "select * from `device_types` where `type`='$type'";
            $result = $dblink->query($sql) or die("Something went wrong with $sql");

            if($result->num_rows < 1){
                updateHeader('application/json', '200 OK');
                $status = "Invalid Data";
                $msg = "Type $type does not exist in database, consider creating it with newType$newVendorMsg";
                echo buildResponse($status, $msg, null);
                $dblink->close();
                die();
            }
        }

        //ensure vendor is set and not null
        if(!isset($_REQUEST['vendor']) || $vendor == null){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Vendor argument must be supplied/not null$newTypeMsg$newVendorMsg";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }else{
            //make sure vendor is a valid type from database
            $sql = "select * from `device_vendors` where `vendor`='$vendor'";
            $result = $dblink->query($sql) or die("Something went wrong with $sql");

            if($result->num_rows < 1){
                updateHeader('application/json', '200 OK');
                $status = "Invalid Data";
                $msg = "Vendor $vendor does not exist in database, consider creating it with newVendor$newTypeMsg";
                echo buildResponse($status, $msg, null);
                $dblink->close();
                die();
            }
        }

        //if state is set, validate it
        if(isset($_REQUEST['state'])){
            $state = $_REQUEST['state'];
            //state should not be null if set at all
            if($state == null){
                updateHeader('application/json', '200 OK');
                $status = "Invalid Data";
                $msg = "State argument must not be null if supplied$newTypeMsg$newVendorMsg";
                echo buildResponse($status, $msg, null);
                $dblink->close();
                die();
            }

            //state must be one of these inputs, case insensitive
            if($state != "1" && $state != "0" && strcasecmp($state, "true") != 0 
                && strcasecmp($state, "false") != 0 && strcasecmp($state, "active") != 0
                && strcasecmp($state, "deactivated") != 0)
            {
                updateHeader('application/json', '200 OK');
                $status = "Invalid Data";
                $msg = "State: $state does not match any acceptable input$newTypeMsg$newVendorMsg";
                echo buildResponse($status, $msg, null);
                $dblink->close();
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
        }else{
            //if state is not set, default to 1
            $state = 1;
        }

        //do the actual device insert
        $sql = "insert into `devices` (`serial#`, `type`, `vendor`, `active`) values ('SN-$serial', '$type', '$vendor', '$state')";
        $dblink->query($sql) or die("Something went wrong with $sql");
        $did = $dblink->insert_id; //again with this subpar method of acquiring inserted id

        updateHeader('application/json', '200 OK');
        $status = "OK";
        $msg = "Device Successfully Inserted$newTypeMsg$newVendorMsg";
        $data = array(
            'Inserted ID' => $did,
            'Serial Number' => "SN-$serial"
        );
        echo buildResponse($status, $msg, $data);
        $dblink->close();
        die();
    }

    if(isset($_REQUEST['newType']) || isset($_REQUEST['newVendor'])){
        if(isset($_REQUEST['newType']) && isset($_REQUEST['newVendor']))
            $msg = "New Type: $newType and Vendor $newVendor created";
        else if(isset($_REQUEST['newType']))
            $msg = "New Type: $newType created";
        else
            $msg = "New Vendor: $newVendor created";
        
        updateHeader('application/json', '200 OK');
        $status = "OK";
        echo buildResponse($status, $msg, null);
        $dblink->close();
        die();
    }

    updateHeader('application/json', '200 OK');
    $status = "Invalid Data";
    $msg = "No or insufficient arguments provided";
    echo buildResponse($status, $msg, null);
    $dblink->close();
    die();

?>
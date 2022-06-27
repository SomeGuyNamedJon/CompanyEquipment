<?php

    //default limit values
    $limit=10;
    $MAX_LIMIT = 1000;

    //usage response
    if(isset($_REQUEST['usage'])){
        updateHeader('application/json', '200 OK');
        $status = "Usage";
        $msg = "This endpoint returns information of devices in the database";
        $data = array(
            'Args' => array(
                'usage' => 'Returns usage information; overrides all other arguments',
                'type=String' => 'Device type to filter by; optional; will return \'No Such Type\' instead if type does not exist in database',
                'vendor=String' => 'Device vendor to filter by; optional; will return \'No Such Vendor\' instead if vendor does not exist in database',
                'limit=Integer' => "Number of results to show; default $limit; max of $MAX_LIMIT",
                'page=Integer' => 'Page of results to show; defaults to first page; based on limit'
            ),
            'Additional Info' => 'Any number of arguments may be used as well as none at all'
        );
        echo buildResponse($status, $msg, $data);
        die();
    }

    //default status and message
    $status = "OK";
    $msg = "";

    //default type and vendor, using 'like' in sql so if these aren't changed they don't affect query
    $type = '%';
    $vendor = '%';

    $dblink = dbconnect("equipment");

    //change type if type is set and not null, check to see if type exists otherwise die with response
    if(isset($_REQUEST['type']) && $_REQUEST['type'] != null){
        $type = $_REQUEST['type'];

        $sql = "select * from `device_types` where `type` = '$type'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");

        //should equal to 1, ensuring 1 and not greater than zero to curb shenanigans
        if($result->num_rows != 1){
            updateHeader('application/json', '200 OK');
            $status = "No Such Type";
            $msg = "Devices of type $type do not exist within database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }
    }

    //same thing with vendor
    if(isset($_REQUEST['vendor']) && $_REQUEST['vendor'] != null){
        $vendor = $_REQUEST['vendor'];

        $sql = "select * from `device_vendors` where `vendor` = '$vendor'";
        $result = $dblink->query($sql) or die("Something went wrong with $sql");

        //should equal to 1, ensuring 1 and not greater than zero to curb shenanigans
        if($result->num_rows != 1){
            updateHeader('application/json', '200 OK');
            $status = "No Such Vendor";
            $msg = "Devices from vendor $vendor do not exist within database";
            echo buildResponse($status, $msg, null);
            $dblink->close();
            die();
        }
    }

    //change limit if set and not null, if limit negative or zero die with response
    if(isset($_REQUEST['limit']) && $_REQUEST['limit'] != null){
        $limit = $_REQUEST['limit'];
        if(!is_numeric($limit)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Limit must be numeric";
            echo buildResponse($status, $msg, null);
            die();
        }elseif($limit <= 0){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Limit must not be less than or equal to ZERO";
            echo buildResponse($status, $msg, null);
            die();
        }
    }
    else
        //add note to msg if limit is not set
        $msg = "Note: limit not set, default $limit results returned";

    //if limit set greater than max, set limit to max and set msg
    if($limit > $MAX_LIMIT){
        $msg = "Note: limit has MAX value of $MAX_LIMIT";
        $limit = $MAX_LIMIT;
    }

    //default page 1, same thing as limit for setting page
    $page=1;
    if(isset($_REQUEST['page']) && $_REQUEST['page'] != null){
        $page = $_REQUEST['page'];
        if(!is_numeric($page)){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Page must be numeric";
            echo buildResponse($status, $msg, null);
            die();
        }elseif($page <= 0){
            updateHeader('application/json', '200 OK');
            $status = "Invalid Data";
            $msg = "Page must not be less than or equal to ZERO";
            echo buildResponse($status, $msg, null);
            die();
        }
    }

    //execute query to return results based on limit, page, vendor and type
    //columns queried explicitly because * causes slowdown on larger limits
    $start=$limit*($page-1);
    $sql="select `id`,`serial#`,`type`,`vendor`,`active` from `devices` where `id` >= $start and `vendor` like '$vendor' and `type` like '$type' limit $limit";
    $result=$dblink->query($sql) or die("Something went wrong with $sql");

    //if no rows, respond accordingly and die
    if($result->num_rows == 0){
        updateHeader('application/json', '200 OK');
        $status = "Not Found";
        $end = $start + $limit;
        $msg = "Database Returned No Results for Given Range: $start-$end";
        echo buildResponse($status, $msg, null);
        $dblink->close();
        die();
    }

    //all else well, loop through results and build data for response
    updateHeader('application/json', '200 OK');
    while($device = $result->fetch_array(MYSQLI_ASSOC)){
        $data[] = array(
            'Device ID' => $device['id'],
            'Device Vendor' => $device['vendor'],
            'Device Type' => $device['type'],
            'Serial Number' => $device['serial#'],
            'Status' => $device['active'] ? 'active' : 'deactivated'
        );
    }

    //status ought to still be OK and msg could include some note about limit
    echo buildResponse($status, $msg, $data);

    $dblink->close();
?>
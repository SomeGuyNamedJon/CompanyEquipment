<?php
    include("dbconnect.php");
    include("functions.php");
    $uri=parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    $uri=explode('&',$uri);
    $endpoint=$uri[0];
    switch($endpoint){
        case "ViewDevice":
            include("ViewDevice.php");
            break;
        case "ListDevices":
            include("ListDevices.php");
            break;
        case "UploadFile":
            include("UploadFile.php");
            break;
        case "ViewFile":
            include("ViewFile.php");
            break;
        case "CreateDevice":
            include("CreateDevice.php");
            break;
        case "UpdateDevice":
            include("UpdateDevice.php");
            break;
        default:
            updateHeader('application/json', '404 Not Found');    
            $status = "Error";
            $msg = "Endpoint Not Found";
            echo buildResponse($status, $msg, null);
            die();
    }
?>
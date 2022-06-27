<!DOCTYPE html>
<html>
    <head>
        <link rel="stylesheet" href="./css/main.css">
        <link rel="stylesheet" href="./css/bootstrap-grid.css">
        <script src="https://kit.fontawesome.com/5a3c76bb3d.js" crossorigin="anonymous"></script> 
    </head>
    <body>
        <?php

        function redirect($url, $statusCode = 303)
        {
            header('Location: ' . $url, true, $statusCode);
            die();
        }

        include("dbconnect.php");
        $dblink = dbconnect("equipment");

        echo '<div class="navmenu">';
        echo '<a href="/" class="btn"><i class="fa-solid fa-house"></i></a>';

        if(!isset($_GET['did'])){
            echo "</div>";
            echo "<p class=\"help-block\">ERROR: No device selected.</p>";
        }else{
            $did=$_GET['did'];
            
            echo '<a href="device.php?did='.$did.'" class="btn"><i class="fa-solid fa-circle-info"></i></a>';
            echo "</div>";
            
            $sql="select * from `devices` where id=$did";
            $result=$dblink->query($sql) or die("Something went wrong with $sql");

            $row = mysqli_fetch_assoc($result);
            $type = $row['type'];
            $vendor = $row['vendor'];
            $serial = $row['serial#'];
            
            if($row['active'])
                $active = "checked";
            else
                $inactive = "checked";

            echo "<h1>Update Device</h1>";

            echo '<div class="display-block" name="updateForm">';
            echo '<form class="form" role="form" method="post" enctype="multipart/form-data" action="">
                    <label>Serial Num:</label>
                    <input type="hidden" name="did" value="'.$did.'">
                    <input class="form-input" type="text" maxlength="35" name="serialnum" value="'.$serial.'">';
                    
                    /*
                    * Types dropdown
                    */
                    
                    $sql = 'select `type` from `device_types`';
                    $result = $dblink->query($sql) or die("Something when wrong with $sql");
                    $types=array();

                    while($row=mysqli_fetch_assoc($result)){
                        $types[]=$row['type'];
                    }
                    
                    echo '<label>Type:</label>';
                    echo '<select class="form-select" name="type">';
                    
                    echo '<option selected="selected">'.$type.'</option>';

                    foreach($types as $key=>$value){
                        if($value != $type)
                            echo '<option value="'. $key .'">'.$value.'</option>';
                    }
                    echo '</select>';

                    /*
                    * Vendors dropdown
                    */

                    $sql = 'select `vendor` from `device_vendors`';
                    $result = $dblink->query($sql) or die("Something when wrong with $sql");
                    $vendors=array();
                    
                    while($row=mysqli_fetch_assoc($result)){
                        $vendors[]=$row['vendor'];
                    }

                    echo '<label>Vendor:</label>';
                    echo '<select class="form-select" name="vendor">';
                    
                    echo '<option selected="selected">'.$vendor.'</option>';

                    foreach($vendors as $key=>$value){
                        if($value != $vendor)
                            echo '<option value="'. $key .'">'.$value.'</option>';
                    }

                    echo '</select>';
                    
                    /*
                    * Active Setting
                    */
                    
                    echo '<div class="row">
                            <div class="col" align="center">
                                <input class="form-radio" type="radio" id="active" name="inuse" value="1" '.$active.'>
                                <label for="active">Active</label>
                            </div>
                            <div class="col" align="center">
                                <input class="form-radio" type="radio" id="inactive" name="inuse" value="0" '.$inactive.'>
                                <label for="inactive">Inactive</label>
                            </div>
                        </div>';
                    
                    echo '<div class="row">
                            <div class="col">
                                <button class="btn form-btn" type="submit" name="Update" value="Update">Update <i class="fa-solid fa-circle-chevron-up"></i></button>
                            </div>
                            <div class="col">
                                <a class="btn form-btn btn-danger a-btn" href="delete.php?did='.$did.'">Delete <i class="fa-solid fa-trash-can"></i></a>
                            </div>
                        </div>';
                    
                echo '</form>';
            echo '</div>';
        }

        if(isset($_POST['Update'])){
            $regEx = "/^SN-[0-9a-f]{32}$/";

            $did = $_POST['did'];
            $updateSerial = $_POST['serialnum'];
            $updateType = $_POST['type'];
            $updateVendor = $_POST['vendor'];
            $updateActive = $_POST['inuse'];
            $valid = 1;

            if(preg_match($regEx, $updateSerial)){
                $time_start = microtime(true);
                if($updateType != $type)
                    $updateType = $types[$updateType];
                
                if($updateVendor != $vendor)
                    $updateVendor = $vendors[$updateVendor]; 
                
                if($updateSerial != $serial){
                    $sql="select id from `devices` where `serial#` = '$updateSerial'";
                    $result=$dblink->query($sql) or die("Something went wrong with $sql");

                    
                    if($result->num_rows > 0){
                        $row = mysqli_fetch_assoc($result);
                        echo '<p class="help-block">Serial Number Invalid: Serial in use by another device ID: '.$row['id'].'</p>';
                        $valid = 0;
                    }
                }

                if($valid){
                    $sql="update `devices` set `type` = '$updateType', `vendor` = '$updateVendor', `serial#` = '$updateSerial', `active` = $updateActive where `id` = $did";
                    $dblink->query($sql) or die("Something went wrong with $sql");
                    $end_time = microtime(true);
                    $exec_time = ($end_time - $time_start);
                    redirect("update.php?did=$did&execTime=$exec_time");
                }
            }else{
                echo '<p class="help-block">Serial Number Invalid: Must match format "SN-(32 character hex)"</p>';
            }
        }

        if(isset($_GET['execTime'])){
            $exec_time = $_GET['execTime'];
            echo "<p class=\"success-block\">Query successfully executed in $exec_time seconds</p>";
        }
        ?>
    </body>
</html>
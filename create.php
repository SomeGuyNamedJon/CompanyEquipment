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
        $dblink=dbconnect("equipment");

        echo '<div class="navmenu">';
        echo '<a href="/" class="btn"><i class="fa-solid fa-house"></i></a>';
        echo "</div>";

        echo "<h1>Create Device</h1>";

        echo '<div class="display-block" name="createForm">';
        echo '<form class="form" role="form" method="post" enctype="multipart/form-data" action="">
                <label>Serial Num:</label>
                <input class="form-input" type="text" maxlength="35" name="serialnum">';
                
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

                echo '<option selected="selected"></option>';
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

                echo '<option selected="selected"></option>';
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
                            <input class="form-radio" type="radio" id="active" name="inuse" value="1">
                            <label for="active">Active</label>
                        </div>
                        <div class="col" align="center">
                            <input class="form-radio" type="radio" id="inactive" name="inuse" value="0">
                            <label for="inactive">Inactive</label>
                        </div>
                    </div>';
                
                echo '<div class="row">
                        <div class="col">
                            <button class="btn form-btn" type="submit" name="Create" value="Create">Create <i class="fa-solid fa-circle-plus"></i></button>
                        </div>
                    </div>';
                
            echo '</form>';
        echo '</div>';

        if(isset($_POST['Create'])){
            $regEx = "/^SN-[0-9a-f]{32}$/";

            $createSerial = $_POST['serialnum'];
            $createType = $_POST['type'];
            $createVendor = $_POST['vendor'];
            $createActive = $_POST['inuse'];
            $valid = 1;

            $time_start = microtime(true);
            
            if($createType != "")
                $createType = $types[$createType];
            else{
                echo '<p class="help-block">Invalid input: Type must be selected</p>';
                $valid = 0;
            }
            
            if($createVendor != "")
                $createVendor = $vendors[$createVendor]; 
            else{
                echo '<p class="help-block">Invalid input: Vendor must be selected</p>';
                $valid = 0;
            }

            if($createActive == ""){
                echo '<p class="help-block">Invalid input: Status must be selected</p>';
                $valid = 0;
            }

            if(preg_match($regEx, $createSerial)){
                
                
                $sql="select id from `devices` where `serial#` = '$createSerial'";
                $result=$dblink->query($sql) or die("Something went wrong with $sql");

                
                if($result->num_rows > 0){
                    $row = mysqli_fetch_assoc($result);
                    echo '<p class="help-block">Serial Number Invalid: Serial in use by another device ID: '.$row['id'].'</p>';
                    $valid = 0;
                }

                if($valid){

                    $sql="insert into `devices` (`serial#`, `vendor`, `type`, `active`) values ('$createSerial', '$createVendor', '$createType', $createActive)";
                    $dblink->query($sql) or die("Something went wrong with $sql");
                    $end_time = microtime(true);
                    $exec_time = ($end_time - $time_start);
                    redirect("create.php?execTime=$exec_time");
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
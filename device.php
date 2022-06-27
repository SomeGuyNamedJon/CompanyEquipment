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
        
        if (!isset($_GET['did'])){
            echo "</div>";
            echo "<p class=\"help-block\">ERROR: No device selected.</p>";
        } else {
            $did = $_GET['did'];
            echo '<a href="update.php?did='.$did.'" class="btn"><i class="fa-solid fa-pen"></i></a>';
            echo '</div>';
            
            echo "<h1>Device Info</h1>";

            echo '<div class="display-block" name="device_info">';
                $sql="select * from `devices` where id=$did";
                $result=$dblink->query($sql) or die("Something went wrong with $sql");

                $row = mysqli_fetch_assoc($result);
                $type = $row['type'];
                $vendor = $row['vendor'];
                $serial = $row['serial#'];
                $status = ($row['active']) ? "Active <i class=\"fa-solid fa-check\"></i>" : "Inactive <i class=\"fa-solid fa-xmark\"></i>";


                echo "<p class=\"displayinfo\"><label>ID:</label>$did<p>";
                echo "<p class=\"displayinfo\"><label>TYPE:</label>$type<p>";
                echo "<p class=\"displayinfo\"><label>VENDOR:</label>$vendor<p>";
                echo "<p class=\"displayinfo\"><label>SERIAL NUM:</label>$serial<p>";
                echo "<p class=\"displayinfo\"><label>STATUS:</label>$status<p>";


            echo '</div><br><hr>';

            echo '<div class="display-block" name="device_files">';

            $sql="select filename from `device_files` where device_id=$did";
            $result=$dblink->query($sql) or die("Something went wrong with $sql");

            if($result->num_rows > 0){
                echo "<h3>Files associated with device:</h3>";
                while($row = mysqli_fetch_assoc($result)){
                    $fileName = $row['filename'];

                    echo "<div class=\"row\">
                            <div class=\"col\">
                                <p>$fileName</p>
                                <a href=\"/files/$fileName\" class=\"viewlink\">View Source <i class=\"fa-solid fa-eye\"></i></a>
                            </div>
                        </div>";
                }
                echo '</div><br><hr>';
            }


            echo '<div class="display-block" name="file_upload">';
                
                echo '<form class="form" role="form" method="post" enctype="multipart/form-data" action="">
                        <input type="hidden" name="MAX_FILE_SIZE" value="50000000">
                        <input type="hidden" name="did" value="'.$did.'">
                        <div class="fileupload fileupload-new" data-provides="fileupload">
                            <div class="flex-row d-flex">
                                <div class="p-md-2">
                                    <input name="userfile" type="file">
                                </div>
                                <div class="p-0">
                                    <button class="btn btn-big" name="Upload" type="submit" value="Upload">Upload <i class="fa-solid fa-file-arrow-up"></i></button>
                                </div>
                                <div class="p-0">
                                    <a href="device.php?did='.$did.'" class="btn a-btn btn-big btn-danger">Cancel <i class="fa-solid fa-xmark"></i></a>
                                </div>
                            </div>
                    </form>';
                    
                if(isset($_GET['execTime'])){
                    $exec_time = $_GET['execTime'];
                    echo "<p class=\"success-block\">Query successfully executed in $exec_time seconds</p>";
                }
                 
            echo "</div>";

            if(isset($_POST["Upload"]) && $_FILES['userfile']['size'] > 0){
                
                $uploadDir="/var/www/html/files";
                $did=$_POST['did'];
                $fileName = $_FILES['userfile']['name'];
                $tmpName = $_FILES['userfile']['tmp_name'];
                $fileSize = $_FILES['userfile']['size'];
                $fileType = $_FILES['userfile']['type'];
                $location = "$uploadDir/$fileName";

                if($fileType != 'image/jpeg' && $fileType != 'image/png' && $fileType != 'application/pdf'){
                    $start_time = microtime(true);

                    $sql="select `file_id` from `device_files` where `location` = '$location' and `device_id` = $did";
                    $result=$dblink->query($sql) or die("Something went wrong with $sql");

                    if($result->num_rows > 0){
                        echo "<p class=help-block>File of same name already linked to device</p>";
                    }else{
                    
                        move_uploaded_file($tmpName, $location);
                        
                        $sql="insert into `device_files` (`filename`,`location`,`type`,`size`,`device_id`) values ('$fileName','$location','$fileType','$fileSize','$did')";
                        $dblink->query($sql) or
                            die("Something went wrong with $sql");
                        
                        $end_time = microtime(true);
                        $exec_time = ($end_time - $start_time);
                        redirect("device.php?did=$did&execTime=$exec_time");
                    }

                }else{
                    echo "<p class=help-block>INVALID FILE TYPE: only accepts pdf, png, or jpg files</p>";
                }
            }
        }
        ?>
    </body>
</html>
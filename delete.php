<!DOCTYPE html>
<html>
    <head>
        <link rel="stylesheet" href="./css/main.css">
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

        if(isset($_GET['did'])){
            $did = $_GET['did'];
            echo "Delete device $did";

            $sql="select `location` from `device_files` where `device_id` = $did";
            $result=$dblink->query($sql) or die("<p class=\"help-block\">Something went wrong with $sql</p>");

            $sql="delete from `devices` where `id` = $did";
            $dblink->query($sql) or die("<p class=\"help-block\">Something went wrong with $sql</p>");

            while($row = mysqli_fetch_assoc($result)){
                $location =  $row['location'];
                if(!unlink($location)){
                    echo "<p class=\"help-block\">Something went wrong with deleting file $location</p>";
                    $redirect = FALSE;
                }
            }

            if($redirect)
                redirect("/");
        }else{
            echo "<p class=\"help-block\">Something went wrong, redirecting</p>";
            redirect("/");
        }
        ?>
    </body>
</html>
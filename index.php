<!DOCTYPE html>
<html>
    <head>
        <script src="./js/jquery-3.5.1.js"></script>
        <script src="./js/jquery.dataTables.min.js"></script>
        <script src="./js/dataTables.responsive.min.js"></script>
        <link rel="stylesheet" href="./css/main.css">
        <link rel="stylesheet" href="./css/bootstrap-grid.css">
        <link rel="stylesheet" href="./css/jquery.dataTables.min.css">
        <link rel="stylesheet" href="./css/responsive.dataTables.min.css">
        <link rel="stylesheet" href="./css/rowReorder.dataTables.min.css">
        <script src="https://kit.fontawesome.com/5a3c76bb3d.js" crossorigin="anonymous"></script>

        <script>
            $(document).ready( function () {
                $('#equipment').DataTable(
                    {
                        rowReorder: {
                            selector: 'td:nth-child(2)'
                        },
                        autoWidth: true,
                        responsive: true,
                        columnDefs: [
                            { width: '20%', targets: 0 }
                        ]
                    }
                );
            } );
        </script>
    </head>
    <body>

        <br><br>
        <h1>Company Equipment</h1>

        <?php
        
        include("dbconnect.php");
        $dblink = dbconnect("equipment");

        /*
         * Types dropdown
         */

        echo "<div align=\"center\" class=\"row\">";

        $sql = 'select `type` from `device_types`';
        $result = $dblink->query($sql) or die("Something when wrong with $sql");
        $types=array();
        
        $types[]='%';
        while($row=mysqli_fetch_assoc($result)){
            $types[]=$row['type'];
        }
        
        if (!isset($_POST['type'])){
            $type = '%';
        } else {
            if(is_numeric($_POST['type']))
                $type = $types[$_POST['type']];
            else
                $type = $_POST['type'];
        }

        echo '<form method="post" action="">';
        echo '<label>Type:</label>';
        echo '<select name="type">';
        
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
        
        $vendors[]='%';
        while($row=mysqli_fetch_assoc($result)){
            $vendors[]=$row['vendor'];
        }

        if (!isset($_POST['vendor'])){
            $vendor = '%';
        } else {
            if(is_numeric($_POST['vendor']))
                $vendor = $vendors[$_POST['vendor']];
            else
                $vendor = $_POST['vendor'];
        }

        echo '<label>Vendor:</label>';
        echo '<select name="vendor">';
        
        echo '<option selected="selected">'.$vendor.'</option>';

        foreach($vendors as $key=>$value){
            if($value != $vendor)
                echo '<option value="'. $key .'">'.$value.'</option>';
        }

        echo '</select>';
        echo '<button class="btn" type="submit" name="submit" value="lookup">Submit</button>';
        echo '</form>';
        echo "</div>";

        /*
         * Table select logic
         */
        
        $sql='select `id`,`type`,`vendor`,`serial#`,`active` from `devices` where type like \''.$type.'\' and vendor like \''.$vendor.'\' order by `id` DESC limit 1000';
        $result=$dblink->query($sql) or die("Something went wrong with $sql");
        
        /*
        *  Table
        */
        echo "<div class=\"display-block\">
            <table id=\"equipment\" class=\"nowrap\" style=\"width:100%\">
                    <thead>
                        <tr>
                            <th>Serial Number</th>
                            <th>Device Type</th>
                            <th>Vendor</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>";
        while($row = mysqli_fetch_assoc($result)){
            $did = $row['id'];
            $device_type = $row['type'];
            $device_vendor = $row['vendor'];
            $serial = $row['serial#'];
            $status = ($row['active']) ? "<i class=\"fa-solid fa-check\"></i>" : "<i class=\"fa-solid fa-xmark\"></i>";
            
            echo "<tr>
                    <td>$serial</td>
                    <td>$device_type</td>
                    <td>$device_vendor</td>
                    <td align=\"center\">$status</td>
                    <td align=\"center\">
                        <div class=\"container-fluid\" style=\"width:100%\">
                        <div class=\"row justify-content-center\">
                            <div class=\"col-4\">
                                <a href=\"device.php?did=$did\" class=\"viewlink\"><i class=\"fa-solid fa-circle-info\"></i></a>
                            </div>
                            <div class=\"col-4\">
                                <a href=\"update.php?did=$did\" class=\"viewlink\"><i class=\"fa-solid fa-pen\"></i></a>
                            </div>
                            <div class=\"col-4\">
                                <a href=\"delete.php?did=$did\" class=\"viewlink btn-danger\"><i class=\"fa-solid fa-trash-can\"></i></a>
                            </div>
                        </div>
                        </div>
                    </td>
                </tr>";
        }
        echo "</table></div>";
        echo "<a href=\"create.php\" class=\"viewlink create-link\">Create New Device <i class=\"fa-solid fa-circle-plus\"></i></a>";
        ?>
    </body>
</html>

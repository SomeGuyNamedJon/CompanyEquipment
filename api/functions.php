<?php
    function redirect($uri)
    { ?>
        <script type="text/javascript">
            document.location.href="<?php echo $uri; ?>";
        </script>
    <?php die;}

    function buildResponse($status, $msg, $data){
        $response = array(
            "Status" => $status,
            "Message" => $msg,
            "Data" => $data
        ); 
        return json_encode($response);
    }

    function updateHeader($type, $code){
        header("Content-Type: $type");
        header("HTTP/1.1 $code");
    }
?>
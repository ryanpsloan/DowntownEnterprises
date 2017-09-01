<?php
session_start();
//var_dump($_FILES);
//includes
if(isset($_FILES)) { //Check to see if a file is uploaded
    try {
        if (($log = fopen("log.txt", "w")) === false) { //open a log file
            //if unable to open throw exception
            throw new RuntimeException("Log File Did Not Open.");
        }
        $today = new DateTime('now'); //create a date for now
        fwrite($log, $today->format("Y-m-d H:i:s") . PHP_EOL); //post the date to the log
        fwrite($log, "--------------------------------------------------------------------------------" . PHP_EOL); //post to log
        $name = $_FILES['file']['name']; //get file name
        fwrite($log, "FileName: $name" . PHP_EOL); //write to log
        $type = $_FILES["file"]["type"];//get file type
        fwrite($log, "FileType: $type" . PHP_EOL); //write to log
        $tmp_name = $_FILES['file']['tmp_name']; //get file temp name
        fwrite($log, "File TempName: $tmp_name" . PHP_EOL); //write to log
        $tempArr = explode(".", $_FILES['file']['name']); //set file name into an array
        $extension = end($tempArr); //get file extension
        fwrite($log, "Extension: $extension" . PHP_EOL); //write to log
        //If any errors throw an exception
        if (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) {
            fwrite($log, "Invalid Parameters - No File Uploaded." . PHP_EOL);
            throw new RuntimeException("Invalid Parameters - No File Uploaded.");
        }
        //switch statement to determine action in relationship to reported error
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                fwrite($log, "No File Sent." . PHP_EOL);
                throw new RuntimeException("No File Sent.");
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                fwrite($log, "Exceeded Filesize Limit." . PHP_EOL);
                throw new RuntimeException("Exceeded Filesize Limit.");
            default:
                fwrite($log, "Unknown Errors." . PHP_EOL);
                throw new RuntimeException("Unknown Errors.");
        }
        //check file size
        if ($_FILES['file']['size'] > 2000000) {
            fwrite($log, "Exceeded Filesize Limit." . PHP_EOL);
            throw new RuntimeException('Exceeded Filesize Limit.');
        }
        //define accepted extensions and types
        $goodExts = array("csv");
        $goodTypes = array("text/csv", "application/vnd.ms-excel", "application/csv");
        //test to ensure that uploaded file extension and type are acceptable - if not throw exception
        if (in_array($extension, $goodExts) === false || in_array($type, $goodTypes) === false) {
            fwrite($log, "This page only accepts .csv files, please upload the correct format." . PHP_EOL);
            throw new Exception("This page only accepts .csv files, please upload the correct format.");
        }
        //move the file from temp location to the server - if fail throw exception
        $directory = "/var/www/html/DowntownEnterprises/Files";
        if (move_uploaded_file($tmp_name, "$directory/$name")) {
            fwrite($log, "File Successfully Uploaded." . PHP_EOL);
        } else {
            fwrite($log, "Unable to Move File to /Files." . PHP_EOL);
            throw new RuntimeException("Unable to Move File to /Files.");
        }
        //rename the file using todays date and time
        $month = $today->format("m");
        $day = $today->format('d');
        $year = $today->format('y');
        $time = $today->format('H-i-s');
        $newName = "$directory/DowntownEnterprises-$month-$day-$year-$time.$extension";
        if ((rename("$directory/$name", $newName))) {
            fwrite($log, "File Renamed to: $newName" . PHP_EOL);
        } else {
            fwrite($log, "Unable to Rename File: $name" . PHP_EOL);
            throw new RuntimeException("Unable to Rename File: $name");
        }
        $handle = fopen($newName, "r");
        $headers = fgets($handle);
        //var_dump($headers);
        $fileData = array();
        //read the data in line by line
        while (!feof($handle)) {
            $fileData[] = fgetcsv($handle);
        }
        //close file reading stream
        fclose($handle);
        var_dump($fileData);
        $assocData = array();
        foreach($fileData as $key => $data) {
            if($data != false) {
                $result = array_filter($data, 'strlen');
                if (count($result) === 14) {

                    $assocData[$data[0]][$data[1]][$data[2]][] = array("ID" => $data[0], "Employee" => $data[1], "Class" => $data[2], "Time In" => $data[3], "Time Out" => $data[4], "Total Hrs" => floatval($data[5]), "Reg Hrs" => floatval($data[6]), "OT Hrs" => floatval($data[7]), "DT Hrs" => floatval($data[8]), "Total Paid" => floatval(str_replace("$","",trim($data[9]))), "Reg Paid" => floatval(str_replace("$","",trim($data[10]))), "OT Paid" => floatval(str_replace("$","",trim($data[11]))), "DT Paid" => floatval(str_replace("$","",trim($data[12]))), "Rate" => floatval(str_replace("$","",trim($data[13]))));

                }
            }
        }
        var_dump($assocData);
        $totals = $rates = array();
        foreach($assocData as $id => $array){
            foreach($array as $name => $arr){
                foreach($arr as $class => $line) {
                    //var_dump(array_column($line, 'Reg Paid'));
                    //var_dump($id, $name, $class);
                    $totals[$id][$name][$class]['regular']['hrs'] = array_sum(array_column($line, 'Reg Hrs'));
                    $totals[$id][$name][$class]['overtime']['hrs'] = array_sum(array_column($line, 'OT Hrs'));
                    $totals[$id][$name][$class]['dt']['hrs'] = array_sum(array_column($line, 'DT Hrs'));
                    $totals[$id][$name][$class]['total']['hrs'] = array_sum(array_column($line, 'Total Hrs'));

                    $totals[$id][$name][$class]['regular']['paid'] = array_sum(array_column($line, 'Reg Paid'));
                    $totals[$id][$name][$class]['overtime']['paid'] = array_sum(array_column($line, 'OT Paid'));
                    $totals[$id][$name][$class]['dt']['paid'] = array_sum(array_column($line, 'DT Paid'));
                    $totals[$id][$name][$class]['total']['paid'] = array_sum(array_column($line, 'Total Paid'));

                    //$totals[$id][$name][$class]['regular']['rate'] = trim(array_unique(array_column($line, 'Rate'))[0]);
                    //$totals[$id][$name][$class]['overtime']['rate'] = trim(array_unique(array_column($line, 'Rate'))[0]);
                    //$totals[$id][$name][$class]['dt']['rate'] = trim(array_unique(array_column($line, 'Rate'))[0]);
                    $totals[$id][$name][$class]['total']['rate'] = array_unique(array_column($line, 'Rate'))[0];

                }
            }
        }

        var_dump($totals, $rates);

        foreach($assocData as $id => $array){
            foreach($array as $name => $arr){
                foreach($arr as $class => $line) {
                    //var_dump($id, $name, $class);
                    $regular = $totals[$id][$name][$class]['regular'][0];
                    $ot = $totals[$id][$name][$class]['overtime'][0];
                    $dt = $totals[$id][$name][$class]['dt'][0];
                    $total = $totals[$id][$name][$class]['total'][0];
                    $rate = $rates[$id][$name][$class]['rate'][0];
                }
            }
        }


        $month = $today->format("m");
        $day = $today->format('d');
        $year = $today->format('y');
        $time = $today->format('H-i-s');
        $fileName = "Files/DowntownEnterprises_EvoImport-" . $month . "-" . $day . "-" . $year . "-". $time. ".csv";
        $handle = fopen($fileName, 'wb');
        foreach($output as $line){
            fputcsv($handle, $line);
        }
        fclose($handle);
        $_SESSION['fileName'] = $fileName;
        $_SESSION['output'] = "Files Successfully Created";
    }catch(Exception $e){
        $_SESSION['output'] = $e->getMessage();
        header('Location: index.php');
    }
}else{
    $_SESSION['output'] = "<p>No File Was Selected</p>";
    header('Location: index.php');
}


?>
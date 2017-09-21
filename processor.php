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
        if(!$handle = fopen($newName, "r")){
            throw new Exception('Unable to open writing stream');
        }
        $headers = fgets($handle);
        //var_dump($headers);
        $fileData = array();
        //read the data in line by line
        while (!feof($handle)) {
            $fileData[] = fgetcsv($handle);
        }
        //close file reading stream
        fclose($handle);
        //var_dump($fileData);
        $assocData = array();

        function fileAnalysisAndLog($passedFile){
            //open a log file
            if(!$handle = fopen('errorLog.txt', 'w')){
                throw new Exception('Unable to create Error Log');
            }
            $errorCount = 0;
            $errorFound = false;
            $today = date('Y-m-d H:i:s');
            fwrite($handle, 'Time of Execution: ' . $today ."\r\n");

            //var_dump($passedFile);
            foreach($passedFile as $key => $array){
                if($array != false){
                    if(count($array) == 14){
                        for($i = 0; $i < 14; $i++){
                            $var = trim($array[$i]);
                            if(!isset($var)){
                                //Document which position and which line
                                $str = 'Row: ' . ($key + 2). ' | Column:' . $i . " | Value for this cell is not set.\r\n";
                                fwrite($handle, $str);
                                $errorFound = true;
                                $errorCount++;
                            }
                            if($i == 13 && trim($array[13]) == ''){
                                fwrite($handle, 'Row: ' . ($key+2) . " Is missing a rate in the rate column.\r\n");
                                $errorFound = true;
                                $errorCount++;
                            }
                        }
                    }else{
                        //not a good line
                        //document line number and count
                        fwrite($handle, 'Row: '. ($key + 2) .' is missing some columns. Column count: ' . count($array)."\r\n");
                        $errorFound = true;
                        $errorCount++;
                        var_dump($array);
                    }

                }

            }
            fwrite($handle, 'Error Count: '. $errorCount);
            fclose($handle);
            return $errorFound;
        }

        if(fileAnalysisAndLog($fileData)){
            $_SESSION['errorLog'] = true;
            $_SESSION['errorFileName'] = 'errorLog.txt';

            throw new Exception('Error(s) found in file. Check the log by clicking the download error log button');
        }
        foreach($fileData as $key => $data) {
            //var_dump($data == false);
            if($data != false) {
                $result = array_filter($data, 'strlen');
                if (count($result) === 14) {
                    $assocData[$data[0]][$data[1]][$data[2]][] = $tempArr = array("ID" => $data[0], "Employee" => $data[1], "Class" => $data[2], "Time In" => $data[3], "Time Out" => $data[4], "Total Hrs" => floatval($data[5]), "Reg Hrs" => floatval($data[6]), "OT Hrs" => floatval($data[7]), "DT Hrs" => floatval($data[8]), "Total Paid" => floatval(str_replace("$","",trim($data[9]))), "Reg Paid" => floatval(str_replace("$","",trim($data[10]))), "OT Paid" => floatval(str_replace("$","",trim($data[11]))), "DT Paid" => floatval(str_replace("$","",trim($data[12]))), "Rate" => isset($data[13]) ? floatval(str_replace("$","",trim($data[13]))) : null);

                }else{
                    $runtimeExceptions['count result'][] = array(count($result), $result);
                }
            }
        }
        //var_dump($assocData);
        $totals = $totalPaid = $totalHrs = array();
        foreach($assocData as $id => $array){
            foreach($array as $name => $arr){
                foreach($arr as $class => $line) {
                    //var_dump(array_column($line, 'Reg Paid'));
                    //var_dump($id, $name, $class);
                    $totals[$id][$name][$class]['regular']['hrs'] = array_sum(array_column($line, 'Reg Hrs'));
                    $totals[$id][$name][$class]['overtime']['hrs'] = array_sum(array_column($line, 'OT Hrs'));
                    $totals[$id][$name][$class]['dt']['hrs'] = array_sum(array_column($line, 'DT Hrs'));
                    $totals[$id][$name][$class]['total']['hrs'] = $totalHrs[] = array_sum(array_column($line, 'Total Hrs'));

                    $totals[$id][$name][$class]['regular']['paid'] = array_sum(array_column($line, 'Reg Paid'));
                    $totals[$id][$name][$class]['overtime']['paid'] = array_sum(array_column($line, 'OT Paid'));
                    $totals[$id][$name][$class]['dt']['paid'] = array_sum(array_column($line, 'DT Paid'));
                    $totals[$id][$name][$class]['total']['paid'] = $totalPaid[] = array_sum(array_column($line, 'Total Paid'));

                    //$totals[$id][$name][$class]['regular']['rate'] = trim(array_unique(array_column($line, 'Rate'))[0]);
                    //$totals[$id][$name][$class]['overtime']['rate'] = trim(array_unique(array_column($line, 'Rate'))[0]);
                    //$totals[$id][$name][$class]['dt']['rate'] = trim(array_unique(array_column($line, 'Rate'))[0]);
                    $totals[$id][$name][$class]['total']['rate'] = array_unique(array_column($line, 'Rate'))[0];

                }
            }
        }
        //var_dump($totalHrs);
        //var_dump($totalPaid);
        //var_dump($totals);
        $output = array();
        foreach($assocData as $id => $array){
            foreach($array as $name => $arr){
                foreach($arr as $class => $line) {
                    //var_dump($id, $name, $class);
                    $regular = $totals[$id][$name][$class]['regular']['hrs'];
                    $ot = $totals[$id][$name][$class]['overtime']['hrs'];
                    //$dt = $totals[$id][$name][$class]['dt']['hrs'];
                    //$total = $totals[$id][$name][$class]['total']['hrs'];
                    $rate = $totals[$id][$name][$class]['total']['rate'] !== null ? $totals[$id][$name][$class]['total']['rate'] : '' ;

                    if($regular > 0){
                        $code = '01';
                        if($class === 'Server' || $class === 'Server-MOD') {
                            if($rate > 8.80) {
                                $code = '61';
                            }
                        }
                        $output[] = array($id,'','','','','E',$code,(string) $rate, (string) $regular,'','','','','','','','','','','','','','','','','','','','');
                    }
                    if($ot > 0){
                        $code = '02';
                        if($rate > 8.80) {
                            $code = '62';
                        }
                        $output[] = array($id,'','','','','E',$code,(string) $rate, (string) $ot,'','','','','','','','','','','','','','','','','','','','');
                    }
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
        $_SESSION['empCount'] = count($assocData);
        $_SESSION['totPaid'] = array_sum($totalPaid);
        $_SESSION['totHrs'] = round(array_sum($totalHrs),2);
        $_SESSION['errorLog'] = null;
        header('Location: index.php');
    }catch(Exception $e){
        $_SESSION['error'] = $e->getMessage();
        header('Location: index.php');
    }
}else{
    $_SESSION['error'] = "<p>No File Was Selected</p>";
    header('Location: index.php');
}


?>
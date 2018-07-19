<!doctype html>
<html>

    <head>
        <title> Testing </title>
    </head>

    <body>

        <?php
        $mysql_host     = 'localhost';
        $mysql_username = 'root';
        $mysql_password = '';
        $mysql_database = 'task-mapper';


        ($connection = @mysqli_connect($mysql_host, $mysql_username, $mysql_password)) or
            die('Error connecting to the database');


        @mysqli_select_db($connection, $mysql_database) or
            die('Error selecting database');


        @mysqli_query($connection, 'TRUNCATE TABLE taskmapper') or
        die('error in truncate table');

        
        require 'TaskMapper.php';

        
        $task = new TaskMapper($connection);

        // populate the table

//- start Level 1

        // add 'Ravi' as a topmost node
        $ravi = $task->add(0, 'Ravi');

//- end Level 1

//- start Level 2
        // 'sunil' and 'raju' and 'deepak' are direct descendants of 'Ravi'
        $sunil = $task->add($ravi, 'Sunil');
        $deepak = $task->add($ravi, 'Deepak');
        $raju = $task->add($ravi, 'Raju');

// end level 2

//- start Level 3

        // 'mahesh' and 'Prashant' and 'Vinod' are direct descendants of 'Sunil'
        $mahesh = $task->add($sunil, 'Mahesh');
        $prashant = $task->add($sunil, 'Prashant');
        $vinod = $task->add($sunil, 'Vinod');


        // add a 'Pooja' direct descendants of Deepak
        $pooja = $task->add($deepak, 'Pooja');


        // add a 'ankit' and 'vishal' and 'ajay' are  direct descendants of raju
        $ankit = $task->add($raju, 'Ankit');
        $vishal = $task->add($raju, 'Vishal');
        $ajay = $task->add($raju, 'Ajay');

// end level 3

// start Level 4
        $babita = $task->add($mahesh, 'Babita');

        $shahid = $task->add($prashant, 'Shahid');
        $salmaan = $task->add($prashant, 'Salmaan');
        $sumit = $task->add($prashant, 'Sumit');

        $rashmi = $task->add($ankit, 'Rashmi');
        $arjoo = $task->add($ankit, 'Arjoo');
        $kulwant = $task->add($ankit, 'Kulwant');

        $praveen = $task->add($ajay, 'Praveen');

// end level 4

// start Level 5

        $anurag = $task->add($babita, 'Anurag');
        $abhishek = $task->add($babita, 'Abhishek');

        $neha = $task->add($salmaan, 'Neha');
        $deepika = $task->add($salmaan, 'Deepika');
        $veena = $task->add($salmaan, 'Veena');

        $rahul = $task->add($rashmi, 'Rahul');

        $akshay = $task->add($praveen, 'Akshay');
        $sonu = $task->add($praveen, 'Sonu');


// end level 5

// start Level 6
        $reena = $task->add($abhishek, 'Reena');

        $saurabh = $task->add($rahul, 'Saurabh');

        $yuvraj = $task->add($akshay, 'Yuvraaj');
        $neeraj = $task->add($akshay, 'Neeraj');
        $manoj = $task->add($akshay, 'Manoj');

// end level 6

// start Level 7

        $sulabh = $task->add($saurabh, 'Sulabh');
        $sagun = $task->add($saurabh, 'Sagun','left');

// end level

        $add_without_position = $task->add($ravi, 'test');

        $delete = $task->delete($add_without_position);
        $delete = $task->delete($sagun);

       // get descendants of 'Ravi'
        print_r('<p>childs of "Ravi"');
        print_r('<pre>');
        print_r($task->get_childs($ravi, false));
        print_r('</pre>');


        ?>

    </body>
</html>

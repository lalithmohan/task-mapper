<!doctype html>
<html>

    <head>
        <title> Testing </title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/gojs/1.8.24/go-debug.js"></script>
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

        //delete node
        $delete = $task->delete($add_without_position);
        $delete = $task->delete($sagun);

        //get Parents
        $task->get_parent($sumit);


        //print_r($task->get_parent($sumit));

        //get childs
        $task->get_childs($ravi);

        // copy from veena from salmaan to sumit
       // $task->copy($veena,$sumit);

        //get next siblings
        $task->get_next_sibling($salmaan);
        //print_r($task->get_next_sibling($salmaan));

        //previous siblings
        $task->get_previous_sibling($salmaan);
       // print_r($task->get_previous_sibling($salmaan));

        $data = $task->get_tree($sunil);
        $tree = $task->getTreeData();
       // echo  $task->getDepth($data);

        // Level check
       // print_r($task->getLevel(3));

        //get path
        echo '<pre>';
        print_r($task->get_path_from_bottom($veena));




    //   print_r($task->getLookup());


       // get data node wise if node is not provide get all data
        //print_r($data);
        //print_r($task->getTreeData());

      //echo  json_encode($tree);



        ?>
        <div id="myDiagramDiv2" class="diagramStyling" style="width:1200px; height:800px; background-color: #F0F0F0;"></div>
        <script>
            var $ = go.GraphObject.make;
            var myDiagram =
                $(go.Diagram, "myDiagramDiv2",
                    {
                        "undoManager.isEnabled": true, // enable Ctrl-Z to undo and Ctrl-Y to redo
                        layout: $(go.TreeLayout, // specify a Diagram.layout that arranges trees
                            { angle: 90, layerSpacing: 35 })
                    });

            // in the model data, each node is represented by a JavaScript object:
            myDiagram.nodeTemplate =
                $(go.Node, "Horizontal",
                    { background: "#44CCFF" },
                    $(go.Picture,
                        { margin: 10, width: 50, height: 50, background: "red" },
                        new go.Binding("source")),
                    $(go.TextBlock, "Default Text",
                        { margin: 12, stroke: "white", font: "bold 16px sans-serif" },
                        new go.Binding("text", "name"))
                );
            var myModel = $(go.TreeModel);
            myModel.nodeDataArray =
               <?php echo  json_encode($tree); ?>

            myDiagram.model = myModel;

        </script>
    </body>
</html>

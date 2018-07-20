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

        $delete = $task->delete($add_without_position);
        $delete = $task->delete($sagun);

       // get descendants of 'Ravi'
        //print_r('<p>childs of "Ravi"');
       //print_r('<pre>');
       // echo '<pre>';
      // print_r($task->get_childs($ravi, false));
       // print_r($task->getLookup(1));
        //print_r($task->get_next_sibling($sunil));
        //   print_r($task->get_path_from_bottom($neha));

       // print_r('</pre>');
        $data = $task->get_tree($sunil);
        $output = array();
        $new = array();
        foreach ($data as $value){
             $output['key']= $value['id'];
             $output['parent']= $value['parent'];
             $output['name']= $value['task'];
             $iterator = new RecursiveIteratorIterator(
                new RecursiveArrayIterator($value['children']),
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );

            foreach ($iterator as $key => $item) {
                if (is_array($item)) {
                    var_dump($item);
                }
            }

            /* foreach ($value['children'] as $child){
                 $output['key']= $child['id'];
                 $output['parent']= $child['parent'];
                 $output['name']= $child['task'];
             }*/
     /*   foreach ($value['children'] as $key => $item) {
            if (is_array($item) && $key === $value['children']['id']) {
                echo "Found xyz: ";
                var_dump($item);
            }*/

            array_push($new,$output);

        }
       // echo '<pre>';
       // print_r($data);
       // echo '</pre>';
     //   echo  json_encode($new);



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
               <?php echo  json_encode($new); ?>

            myDiagram.model = myModel;

        </script>
    </body>
</html>

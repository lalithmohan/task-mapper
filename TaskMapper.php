<?php
class TaskMapper{

    public function __construct(&$connection, $table_name = 'taskmapper', $id_column = 'id', $task_column = 'task', $left_column = 'lft', $right_column = 'rgt', $parent_column = 'parent') {


        if (version_compare(phpversion(), '5.0.0') < 0)
            trigger_error('PHP 5.0.0 or greater required', E_USER_ERROR);


        $this->connection = $connection;


        if (@mysqli_ping($this->connection)){
            $this->properties = array(

                'table_name'    =>  $table_name,
                'id_column'     =>  $id_column,
                'task_column'  =>  $task_column,
                'left_column'   =>  $left_column,
                'right_column'  =>  $right_column,
                'parent_column' =>  $parent_column,

            );
        }else{
            trigger_error('no MySQL connection', E_USER_ERROR);
        }

    }

    public function add($parent,$task, $position = false) {


        $this->_init();

        $parent = (int)$parent;

        if ($parent == 0 || isset($this->lookup[$parent]) || ($this->getLookup($parent)>2)) {

            $descendants = $this->get_childs($parent);

            if ($position === false) {
                $position = count($descendants);
            } else {
                $position = (int)$position;
                if ($position > count($descendants) || $position < 0){
                    $position = count($descendants);
                }
            }

            if (empty($descendants) || $position == 0) {

                $boundary = isset($this->lookup[$parent]) ? $this->lookup[$parent][$this->properties['left_column']] : 0;

            } else {

                $slice = array_slice($descendants, $position - 1, 1);

                $descendants = array_shift($slice);


                $boundary = $descendants[$this->properties['right_column']];

            }

            // iterate through all the records in the lookup array
            foreach ($this->lookup as $id => $properties) {

                // if the node's "left" value is outside the boundary
                if ($properties[$this->properties['left_column']] > $boundary)

                    // increment it with 2
                    $this->lookup[$id][$this->properties['left_column']] += 2;

                // if the node's "right" value is outside the boundary
                if ($properties[$this->properties['right_column']] > $boundary)

                    // increment it with 2
                    $this->lookup[$id][$this->properties['right_column']] += 2;

            }

            // lock table to prevent other sessions from modifying the data and thus preserving data integrity
            mysqli_query($this->connection, 'LOCK TABLE `' . $this->properties['table_name'] . '` WRITE');

            // update the nodes in the database having their "left"/"right" values outside the boundary
            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['left_column'] . '` = `' . $this->properties['left_column'] . '` + 2
                WHERE
                    `' . $this->properties['left_column'] . '` > ' . $boundary . '

            ');

            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['right_column'] . '` = `' . $this->properties['right_column'] . '` + 2
                WHERE
                    `' . $this->properties['right_column'] . '` > ' . $boundary . '

            ');

            // insert the new node into the database
            mysqli_query($this->connection, '
                INSERT INTO
                    `' . $this->properties['table_name'] . '`
                    (
                        `' . $this->properties['task_column'] . '`,
                        `' . $this->properties['left_column'] . '`,
                        `' . $this->properties['right_column'] . '`,
                        `' . $this->properties['parent_column'] . '`
                    )
                VALUES
                    (
                        "' . mysqli_real_escape_string($this->connection, $task) . '",
                        ' . ($boundary + 1) . ',
                        ' . ($boundary + 2) . ',
                        ' . $parent . '
                    )
            ');

            // get the ID of the newly inserted node
            $node_id = mysqli_insert_id($this->connection);

            // release table lock
            mysqli_query($this->connection, 'UNLOCK TABLES');

            // add the node to the lookup array
            $this->lookup[$node_id] = array(
                $this->properties['id_column']      => $node_id,
                $this->properties['task_column']   => $task,
                $this->properties['left_column']    => $boundary + 1,
                $this->properties['right_column']   => $boundary + 2,
                $this->properties['parent_column']  => $parent,
            );


            $this->_reorder_lookup_array();


            return $node_id;

        }

        return false;

    }


    public function copy($source, $target, $position = false) {

        $this->_init();
        if (

            isset($this->lookup[$source]) &&

            (isset($this->lookup[$target]) || $target == 0)

        ) {

            $source_children = $this->get_childs($source, false);

            $sources = array($this->lookup[$source]);

            $sources[0][$this->properties['parent_column']] = $target;

            foreach ($source_children as $child)

                $sources[] = $this->lookup[$child[$this->properties['id_column']]];

            $source_rl_difference =

                $this->lookup[$source][$this->properties['right_column']] -

                $this->lookup[$source][$this->properties['left_column']]

                + 1;

            $source_boundary = $this->lookup[$source][$this->properties['left_column']];

            $target_children = $this->get_childs($target);

            if ($position === false)

                $position = count($target_children);


            else {
                $position = (int)$position;
                if ($position > count($target_children) || $position < 0)
                    $position = count($target_children);
            }
            if (empty($target_children) || $position == 0)
                $target_boundary = isset($this->lookup[$target]) ? $this->lookup[$target][$this->properties['left_column']] : 0;
            else {
                $slice = array_slice($target_children, $position - 1, 1);

                $target_children = array_shift($slice);
                $target_boundary = $target_children[$this->properties['right_column']];

            }
            foreach ($this->lookup as $id => $properties) {

                if ($properties[$this->properties['left_column']] > $target_boundary)

                    $this->lookup[$id][$this->properties['left_column']] += $source_rl_difference;

                if ($properties[$this->properties['right_column']] > $target_boundary)

                    $this->lookup[$id][$this->properties['right_column']] += $source_rl_difference;

            }

            // lock table to prevent other sessions from modifying the data and thus preserving data integrity
            mysqli_query($this->connection, 'LOCK TABLE `' . $this->properties['table_name'] . '` WRITE');

            // update the nodes in the database having their "left"/"right" values outside the boundary
            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['left_column'] . '` = `' . $this->properties['left_column'] . '` + ' . $source_rl_difference . '
                WHERE
                    `' . $this->properties['left_column'] . '` > ' . $target_boundary . '

            ');

            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['right_column'] . '` = `' . $this->properties['right_column'] . '` + ' . $source_rl_difference . '
                WHERE
                    `' . $this->properties['right_column'] . '` > ' . $target_boundary . '

            ');

            // finally, the nodes that are to be inserted need to have their "left" and "right" values updated
            $shift = $target_boundary - $source_boundary + 1;

            // iterate through the nodes that are to be inserted
            foreach ($sources as $id => &$properties) {

                // update "left" value
                $properties[$this->properties['left_column']] += $shift;

                // update "right" value
                $properties[$this->properties['right_column']] += $shift;

                // insert into the database
                mysqli_query($this->connection, '
                    INSERT INTO
                        `' . $this->properties['table_name'] . '`
                        (
                            `' . $this->properties['task_column'] . '`,
                            `' . $this->properties['left_column'] . '`,
                            `' . $this->properties['right_column'] . '`,
                            `' . $this->properties['parent_column'] . '`
                        )
                    VALUES
                        (
                            "' . mysqli_real_escape_string($this->connection, $properties[$this->properties['task_column']]) . '",
                            ' . $properties[$this->properties['left_column']] . ',
                            ' . $properties[$this->properties['right_column']] . ',
                            ' . $properties[$this->properties['parent_column']] . '
                        )
                ');

                $node_id = mysqli_insert_id($this->connection);
                foreach ($sources as $key => $value)

                    if ($value[$this->properties['parent_column']] == $properties[$this->properties['id_column']])

                        $sources[$key][$this->properties['parent_column']] = $node_id;


                $properties[$this->properties['id_column']] = $node_id;


                $sources[$id] = $properties;

            }

            unset($properties);
            mysqli_query($this->connection, 'UNLOCK TABLES');

            $parents = array();


            foreach ($sources as $id => $properties) {


                if (count($parents) > 0)


                    while ($parents[count($parents) - 1]['right'] < $properties[$this->properties['right_column']])


                        array_pop($parents);


                if (count($parents) > 0)


                    $properties[$this->properties['parent_column']] = $parents[count($parents) - 1]['id'];


                $this->lookup[$properties[$this->properties['id_column']]] = $properties;


                $parents[] = array(

                    'id'    =>  $properties[$this->properties['id_column']],
                    'right' =>  $properties[$this->properties['right_column']]

                );

            }


            $this->_reorder_lookup_array();


            return $sources[0][$this->properties['id_column']];

        }


        return false;

    }

    public function delete($node) {
        $this->_init();

        if (isset($this->lookup[$node])) {

            $descendants = $this->get_childs($node, false);

            foreach ($descendants as $descendant)

                unset($this->lookup[$descendant[$this->properties['id_column']]]);

            mysqli_query($this->connection, 'LOCK TABLE `' . $this->properties['table_name'] . '` WRITE');

            mysqli_query($this->connection, '

                DELETE
                FROM
                    `' . $this->properties['table_name'] . '`
                WHERE
                    `' . $this->properties['left_column'] . '` >= ' . $this->lookup[$node][$this->properties['left_column']] . ' AND
                    `' . $this->properties['right_column'] . '` <= ' . $this->lookup[$node][$this->properties['right_column']] . '

            ');

            $target_rl_difference =

                $this->lookup[$node][$this->properties['right_column']] -

                $this->lookup[$node][$this->properties['left_column']]

                + 1;

            $boundary = $this->lookup[$node][$this->properties['left_column']];

            unset($this->lookup[$node]);

            foreach ($this->lookup as $id => $properties) {

                if ($this->lookup[$id][$this->properties['left_column']] > $boundary)

                    $this->lookup[$id][$this->properties['left_column']] -= $target_rl_difference;

                if ($this->lookup[$id][$this->properties['right_column']] > $boundary)

                    $this->lookup[$id][$this->properties['right_column']] -= $target_rl_difference;

            }


            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['left_column'] . '` = `' . $this->properties['left_column'] . '` - ' . $target_rl_difference . '
                WHERE
                    `' . $this->properties['left_column'] . '` > ' . $boundary . '

            ');

            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['right_column'] . '` = `' . $this->properties['right_column'] . '` - ' . $target_rl_difference . '
                WHERE
                    `' . $this->properties['right_column'] . '` > ' . $boundary . '

            ');

            mysqli_query($this->connection, 'UNLOCK TABLES');

            return true;

        }


        return false;

    }


    public function get_childs($node = 0, $direct_descendants_only = true) {

        $this->_init();


        if (isset($this->lookup[$node]) || $node === 0) {

            $descendants = array();


            $keys = array_keys($this->lookup);


            foreach ($keys as $item)


                if (


                    $this->lookup[$item][$this->properties['left_column']] > ($node !== 0 ? $this->lookup[$node][$this->properties['left_column']] : 0) &&


                    $this->lookup[$item][$this->properties['left_column']] < ($node !== 0 ? $this->lookup[$node][$this->properties['right_column']] : PHP_INT_MAX) &&


                    (!$direct_descendants_only || $this->lookup[$item][$this->properties['parent_column']] == $node)


                ) $descendants[$this->lookup[$item][$this->properties['id_column']]] = $this->lookup[$item];


            return $descendants;

        }


        return false;

    }


    public function get_next_sibling($node) {


        if ($siblings = $this->get_siblings($node, true)) {

            // get the node's position among the siblings
            $node_position = array_search($node, array_keys($siblings));

            // get next node
            $sibling = array_slice($siblings, $node_position + 1, 1);

            // return result
            return  !empty($sibling) ? array_pop($sibling) : 0;

        }

        // if script gets this far, return false as something must've went wrong
        return false;

    }


    public function get_parent($node) {

        $this->_init();

        if (isset($this->lookup[$node]))

            return isset($this->lookup[$this->lookup[$node][$this->properties['parent_column']]]) ? $this->lookup[$this->lookup[$node][$this->properties['parent_column']]] : 0;

        return false;

    }


    public function get_path_from_bottom($node) {

        $this->_init();

        $parents = array();

        if (isset($this->lookup[$node]))

            foreach ($this->lookup as $id => $properties)


                if (


                    $properties[$this->properties['left_column']] < $this->lookup[$node][$this->properties['left_column']] &&

                    $properties[$this->properties['right_column']] > $this->lookup[$node][$this->properties['right_column']]

                ) $parents[$properties[$this->properties['id_column']]] = $properties;

        $parents[$node] = $this->lookup[$node];

        return $parents;

    }


    public function get_previous_sibling($node) {

        // if node exists, get its siblings
        // (if $node exists this will never be an empty array as it will contain at least $node)
        if ($siblings = $this->get_siblings($node, true)) {

            // get the node's position among the siblings
            $node_position = array_search($node, array_keys($siblings));

            // get previous node
            $sibling = $node_position > 0 ? array_slice($siblings, $node_position - 1, 1) : array();

            // return result
            return !empty($sibling) ? array_pop($sibling) : 0;

        }

        // if script gets this far, return false as something must've went wrong
        return false;

    }

    public function get_siblings($node, $include_self = false) {


        if (isset($this->lookup[$node])) {


            $properties = $this->lookup[$node];


            $siblings = $this->get_childs($properties['parent']);


            if (!$include_self) unset($siblings[$node]);


            return $siblings;

        }


        return false;

    }

    public function get_tree($node = 0) {


        $descendants = $this->get_childs($node);

        foreach ($descendants as $id => $properties)


            $descendants[$id]['children'] = $this->get_tree($id);

        return $descendants;

    }


    public function move($source, $target, $position = false) {

        $this->_init();

        if (


            isset($this->lookup[$source]) &&


            (isset($this->lookup[$target]) || $target == 0) &&


            !in_array($target, array_keys($this->get_childs($source, false)))

        ) {

            if ($position === 'after' || $position === 'before') {

                $target_parent = $target == 0 ? 0 : $this->lookup[$target]['parent'];


                $descendants = $this->get_childs($target_parent);


                $keys = array_keys($descendants);
                $target_position = array_search($target, $keys);


                if ($position == 'after') return $this->move($source, $target_parent, $target_position + 1);
                else return $this->move($source, $target_parent, $target_position == 0 ? 0 : $target_position - 1);

            }


            $this->lookup[$source][$this->properties['parent_column']] = $target;


            $source_descendants = $this->get_childs($source, false);



            $sources = array($this->lookup[$source]);


            foreach ($source_descendants as $descendant) {


                $sources[] = $this->lookup[$descendant[$this->properties['id_column']]];


                unset($this->lookup[$descendant[$this->properties['id_column']]]);

            }


            $source_rl_difference =

                $this->lookup[$source][$this->properties['right_column']] -

                $this->lookup[$source][$this->properties['left_column']]

                + 1;



            $source_boundary = $this->lookup[$source][$this->properties['left_column']];


            mysqli_query($this->connection, 'LOCK TABLE `' . $this->properties['table_name'] . '` WRITE');



            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['left_column'] . '` = `' . $this->properties['left_column'] . '` * -1,
                    `' . $this->properties['right_column'] . '` = `' . $this->properties['right_column'] . '` * -1
                WHERE
                    `' . $this->properties['left_column'] . '` >= ' . $this->lookup[$source][$this->properties['left_column']] . ' AND
                    `' . $this->properties['right_column'] . '` <= ' . $this->lookup[$source][$this->properties['right_column']] . '

            ');


            unset($this->lookup[$source]);


            foreach ($this->lookup as $id=>$properties) {


                if ($this->lookup[$id][$this->properties['left_column']] > $source_boundary)


                    $this->lookup[$id][$this->properties['left_column']] -= $source_rl_difference;


                if ($this->lookup[$id][$this->properties['right_column']] > $source_boundary)


                    $this->lookup[$id][$this->properties['right_column']] -= $source_rl_difference;

            }


            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['left_column'] . '` = `' . $this->properties['left_column'] . '` - ' . $source_rl_difference . '
                WHERE
                    `' . $this->properties['left_column'] . '` > ' . $source_boundary . '

            ');

            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['right_column'] . '` = `' . $this->properties['right_column'] . '` - ' . $source_rl_difference . '
                WHERE
                    `' . $this->properties['right_column'] . '` > ' . $source_boundary . '

            ');


            $target_childs = $this->get_childs((int)$target);



            if ($position === false) $position = count($target_childs);


            else {


                $position = (int)$position;


                if ($position > count($target_childs) || $position < 0)


                    $position = count($target_childs);

            }




            if (empty($target_childs) || $position == 0)




                $target_boundary = isset($this->lookup[$target]) ? $this->lookup[$target][$this->properties['left_column']] : 0;


            else {


                $slice = array_slice($target_childs, $position - 1, 1);

                $target_childs = array_shift($slice);



                $target_boundary = $target_childs[$this->properties['right_column']];

            }


            foreach ($this->lookup as $id => $properties) {

                // if the "left" value of node is outside the boundary
                if ($properties[$this->properties['left_column']] > $target_boundary)

                    // increment it
                    $this->lookup[$id][$this->properties['left_column']] += $source_rl_difference;

                // if the "left" value of node is outside the boundary
                if ($properties[$this->properties['right_column']] > $target_boundary)

                    // increment it
                    $this->lookup[$id][$this->properties['right_column']] += $source_rl_difference;

            }


            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['left_column'] . '` = `' . $this->properties['left_column'] . '` + ' . $source_rl_difference . '
                WHERE
                    `' . $this->properties['left_column'] . '` > ' . $target_boundary . '

            ');

            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['right_column'] . '` = `' . $this->properties['right_column'] . '` + ' . $source_rl_difference . '
                WHERE
                    `' . $this->properties['right_column'] . '` > ' . $target_boundary . '

            ');


            $shift = $target_boundary - $source_boundary + 1;


            foreach ($sources as $properties) {


                $properties[$this->properties['left_column']] += $shift;


                $properties[$this->properties['right_column']] += $shift;

                $this->lookup[$properties[$this->properties['id_column']]] = $properties;

            }




            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['left_column'] . '` = (`' . $this->properties['left_column'] . '` - ' . $shift . ') * -1,
                    `' . $this->properties['right_column'] . '` = (`' . $this->properties['right_column'] . '` - ' . $shift . ') * -1
                WHERE
                    `' . $this->properties['left_column'] . '` < 0

            ');


            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['parent_column'] . '` = ' . $target . '
                WHERE
                    `' . $this->properties['id_column'] . '` = ' . $source . '

            ');

            // release table lock
            mysqli_query($this->connection, 'UNLOCK TABLES');

            // reorder the lookup array
            $this->_reorder_lookup_array();

            // return true as everything went well
            return true;

        }

        // if scripts gets this far, return false as something must've went wrong
        return false;

    }


    public function update($node, $title) {

        // lazy connection: touch the database only when the data is required for the first time and not at object instantiation
        $this->_init();

        // continue only if target node exists in the lookup array
        if (isset($this->lookup[$node])) {

            // lock table to prevent other sessions from modifying the data and thus preserving data integrity
            mysqli_query($this->connection, 'LOCK TABLE `' . $this->properties['table_name'] . '` WRITE');

            // update node's title
            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['title_column'] . '` = "' . $title . '"
                WHERE
                    `' . $this->properties['id_column'] . '` = ' . $node . '

            ');

            // release table lock
            mysqli_query($this->connection, 'UNLOCK TABLES');

            // update lookup array
            $this->lookup[$node][$this->properties['title_column']] = $title;

            // return true as everything went well
            return true;

        }

        // if scripts gets this far, return false as something must've went wrong
        return false;

    }

    private function _init() {

        if (!isset($this->lookup)) {


            $result = mysqli_query($this->connection, '

                SELECT
                    *
                FROM
                    `' . $this->properties['table_name'] . '`
                ORDER BY
                    `' . $this->properties['left_column'] . '`

            ');

            $this->lookup = array();


            while ($row = mysqli_fetch_assoc($result))

                $this->lookup[$row[$this->properties['id_column']]] = $row;



        }

    }

    private function _reorder_lookup_array() {

        foreach ($this->lookup as $properties)

            ${$this->properties['left_column']} [] = $properties[$this->properties['left_column']];


        array_multisort(${$this->properties['left_column']}, SORT_ASC, $this->lookup);

        $tmp = array();


        foreach ($this->lookup as $properties)


            $tmp[$properties[$this->properties['id_column']]] = $properties;


        $this->lookup = $tmp;


        unset($tmp);

    }

     public function getLookup($node = null){
        $this->_init();

        return isset($node)?$this->lookup[$node]:$this->lookup;

    }

    public function getDepth($array){
        $depth = 1;
        if(is_array($array)){
            foreach ($array as $key =>$value){
                $depth += $this->getDepth($value['children']);

            }

        }
        return $depth;
    }

     public function getTreeData(){
         $this->_init();
         $new = array();
             $dataa =  $this->getLookup();

         foreach ($dataa as $key => $value){
             $output['key']= $value['id'];
             $output['parent']= $value['parent'];
             $output['name']= $value['task'];
             array_push($new,$output);
         }
         return $new;
     }

    public function getLevel($node){
         $this->_init();
             $level = array();
         if(
         $this->getLookup($node)['parent'] == '0'
         ){
             $level[] = $node['id'];
         }
         return $level;

        //return $this->getLookup($node)['parent'];


    }

    public function getGraph(){

    }
}
?>
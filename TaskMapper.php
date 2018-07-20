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

        // lazy connection: touch the database only when the data is required for the first time and not at object instantiation
        $this->_init();

        // continue only if
        if (

            // source node exists in the lookup array AND
            isset($this->lookup[$source]) &&

            // target node exists in the lookup array OR is 0 (indicating a topmost node)
            (isset($this->lookup[$target]) || $target == 0)

        ) {

            // get the source's children nodes (if any)
            $source_children = $this->get_descendants($source, false);

            // this array will hold the items we need to copy
            // by default we add the source item to it
            $sources = array($this->lookup[$source]);

            // the copy's parent will be the target node
            $sources[0][$this->properties['parent_column']] = $target;

            // iterate through source node's children
            foreach ($source_children as $child)

                // save them for later use
                $sources[] = $this->lookup[$child[$this->properties['id_column']]];

            // the value with which items outside the boundary set below, are to be updated with
            $source_rl_difference =

                $this->lookup[$source][$this->properties['right_column']] -

                $this->lookup[$source][$this->properties['left_column']]

                + 1;

            // set the boundary - nodes having their "left"/"right" values outside this boundary will be affected by
            // the insert, and will need to be updated
            $source_boundary = $this->lookup[$source][$this->properties['left_column']];

            // get target node's children (no deeper than the first level)
            $target_children = $this->get_descendants($target);

            // if copy is to be inserted in the default position (as the last of the target node's children)
            if ($position === false)

                // give a numerical value to the position
                $position = count($target_children);

            // if a custom position was specified
            else {

                // make sure given position is an integer value
                $position = (int)$position;

                // if position is a bogus number
                if ($position > count($target_children) || $position < 0)

                    // use the default position (the last of the target node's children)
                    $position = count($target_children);

            }

            // we are about to do an insert and some nodes need to be updated first

            // if target has no children nodes OR the copy is to be inserted as the target node's first child node
            if (empty($target_children) || $position == 0)

                // set the boundary - nodes having their "left"/"right" values outside this boundary will be affected by
                // the insert, and will need to be updated
                // if parent is not found (meaning that we're inserting a topmost node) set the boundary to 0
                $target_boundary = isset($this->lookup[$target]) ? $this->lookup[$target][$this->properties['left_column']] : 0;

            // if target has children nodes and/or the copy needs to be inserted at a specific position
            else {

                // find the target's child node that currently exists at the position where the new node needs to be inserted to
                $slice = array_slice($target_children, $position - 1, 1);

                $target_children = array_shift($slice);

                // set the boundary - nodes having their "left"/"right" values outside this boundary will be affected by
                // the insert, and will need to be updated
                $target_boundary = $target_children[$this->properties['right_column']];

            }

            // iterate through the nodes in the lookup array
            foreach ($this->lookup as $id => $properties) {

                // if the "left" value of node is outside the boundary
                if ($properties[$this->properties['left_column']] > $target_boundary)

                    // increment it
                    $this->lookup[$id][$this->properties['left_column']] += $source_rl_difference;

                // if the "right" value of node is outside the boundary
                if ($properties[$this->properties['right_column']] > $target_boundary)

                    // increment it
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
                            `' . $this->properties['title_column'] . '`,
                            `' . $this->properties['left_column'] . '`,
                            `' . $this->properties['right_column'] . '`,
                            `' . $this->properties['parent_column'] . '`
                        )
                    VALUES
                        (
                            "' . mysqli_real_escape_string($this->connection, $properties[$this->properties['title_column']]) . '",
                            ' . $properties[$this->properties['left_column']] . ',
                            ' . $properties[$this->properties['right_column']] . ',
                            ' . $properties[$this->properties['parent_column']] . '
                        )
                ');

                // get the ID of the newly inserted node
                $node_id = mysqli_insert_id($this->connection);

                // because the node may have children nodes and its ID just changed
                // we need to find its children and update the reference to the parent ID
                foreach ($sources as $key => $value)

                    // if a child node was found
                    if ($value[$this->properties['parent_column']] == $properties[$this->properties['id_column']])

                        // update the reference to the parent ID
                        $sources[$key][$this->properties['parent_column']] = $node_id;

                // update the node's properties with the ID
                $properties[$this->properties['id_column']] = $node_id;

                // update the array of inserted items
                $sources[$id] = $properties;

            }

            // a reference of a $properties and the last array element remain even after the foreach loop
            // we have to destroy it
            unset($properties);

            // release table lock
            mysqli_query($this->connection, 'UNLOCK TABLES');

            // at this point, we have the nodes in the database but we need to also update the lookup array

            $parents = array();

            // iterate through the inserted nodes
            foreach ($sources as $id => $properties) {

                // if the node has any parents
                if (count($parents) > 0)

                    // iterate through the array of parent nodes
                    while ($parents[count($parents) - 1]['right'] < $properties[$this->properties['right_column']])

                        // and remove those which are not parents of the current node
                        array_pop($parents);

                // if there are any parents left
                if (count($parents) > 0)

                    // the last node in the $parents array is the current node's parent
                    $properties[$this->properties['parent_column']] = $parents[count($parents) - 1]['id'];

                // update the lookup array
                $this->lookup[$properties[$this->properties['id_column']]] = $properties;

                // add current node to the stack
                $parents[] = array(

                    'id'    =>  $properties[$this->properties['id_column']],
                    'right' =>  $properties[$this->properties['right_column']]

                );

            }

            // reorder the lookup array
            $this->_reorder_lookup_array();

            // return the ID of the copy
            return $sources[0][$this->properties['id_column']];

        }

        // if scripts gets this far, return false as something must've went wrong
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

        // lazy connection: touch the database only when the data is required for the first time and not at object instantiation
        $this->_init();

        // continue only if
        if (

            // source node exists in the lookup array AND
            isset($this->lookup[$source]) &&

            // target node exists in the lookup array OR is 0 (indicating a topmost node)
            (isset($this->lookup[$target]) || $target == 0) &&

            // target node is not a child node of the source node (that would cause infinite loop)
            !in_array($target, array_keys($this->get_descendants($source, false)))

        ) {

            // if we have to move the node after/before another node
            if ($position === 'after' || $position === 'before') {

                // get the target's parent node
                $target_parent = $target == 0 ? 0 : $this->lookup[$target]['parent'];

                // get the target's parent's descendant nodes
                $descendants = $this->get_descendants($target_parent);

                // get the target's position among the descendants
                $keys = array_keys($descendants);
                $target_position = array_search($target, $keys);

                // move the source node to the desired position
                if ($position == 'after') return $this->move($source, $target_parent, $target_position + 1);
                else return $this->move($source, $target_parent, $target_position == 0 ? 0 : $target_position - 1);

            }

            // the source's parent node's ID becomes the target node's ID
            $this->lookup[$source][$this->properties['parent_column']] = $target;

            // get source node's descendant nodes (if any)
            $source_descendants = $this->get_descendants($source, false);

            // this array will hold the nodes we need to move
            // by default we add the source node to it
            $sources = array($this->lookup[$source]);

            // iterate through source node's descendants
            foreach ($source_descendants as $descendant) {

                // save them for later use
                $sources[] = $this->lookup[$descendant[$this->properties['id_column']]];

                // for now, remove them from the lookup array
                unset($this->lookup[$descendant[$this->properties['id_column']]]);

            }

            // the value with which nodes outside the boundary set below, are to be updated with
            $source_rl_difference =

                $this->lookup[$source][$this->properties['right_column']] -

                $this->lookup[$source][$this->properties['left_column']]

                + 1;

            // set the boundary - nodes having their "left"/"right" values outside this boundary will be affected by
            // the insert, and will need to be updated
            $source_boundary = $this->lookup[$source][$this->properties['left_column']];

            // lock table to prevent other sessions from modifying the data and thus preserving data integrity
            mysqli_query($this->connection, 'LOCK TABLE `' . $this->properties['table_name'] . '` WRITE');

            // we'll multiply the "left" and "right" values of the nodes we're about to move with "-1", in order to
            // prevent the values being changed further in the script
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

            // remove the source node from the list
            unset($this->lookup[$source]);

            // iterate through the remaining nodes in the lookup array
            foreach ($this->lookup as $id=>$properties) {

                // if the "left" value of node is outside the boundary
                if ($this->lookup[$id][$this->properties['left_column']] > $source_boundary)

                    // decrement it
                    $this->lookup[$id][$this->properties['left_column']] -= $source_rl_difference;

                // if the "right" value of item is outside the boundary
                if ($this->lookup[$id][$this->properties['right_column']] > $source_boundary)

                    // decrement it
                    $this->lookup[$id][$this->properties['right_column']] -= $source_rl_difference;

            }

            // update the nodes in the database having their "left"/"right" values outside the boundary
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

            // get descendant nodes of target node (first level only)
            $target_descendants = $this->get_descendants((int)$target);

            // if node is to be inserted in the default position (as the last of target node's children nodes)
            // give a numerical value to the position
            if ($position === false) $position = count($target_descendants);

            // if a custom position was specified
            else {

                // make sure given position is an integer value
                $position = (int)$position;

                // if position is a bogus number
                if ($position > count($target_descendants) || $position < 0)

                    // use the default position (as the last of the target node's children)
                    $position = count($target_descendants);

            }

            // because of the insert, some nodes need to have their "left" and/or "right" values adjusted

            // if target node has no descendant nodes OR the node is to be inserted as the target node's first child node
            if (empty($target_descendants) || $position == 0)

                // set the boundary - nodes having their "left"/"right" values outside this boundary will be affected by
                // the insert, and will need to be updated
                // if parent is not found (meaning that we're inserting a topmost node) set the boundary to 0
                $target_boundary = isset($this->lookup[$target]) ? $this->lookup[$target][$this->properties['left_column']] : 0;

            // if target has any descendant nodes and/or the node needs to be inserted at a specific position
            else {

                // find the target's child node that currently exists at the position where the new node needs to be inserted to
                $slice = array_slice($target_descendants, $position - 1, 1);

                $target_descendants = array_shift($slice);

                // set the boundary - nodes having their "left"/"right" values outside this boundary will be affected by
                // the insert, and will need to be updated
                $target_boundary = $target_descendants[$this->properties['right_column']];

            }

            // iterate through the records in the lookup array
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

            // iterate through the nodes to be inserted
            foreach ($sources as $properties) {

                // update "left" value
                $properties[$this->properties['left_column']] += $shift;

                // update "right" value
                $properties[$this->properties['right_column']] += $shift;

                // add the item to our lookup array
                $this->lookup[$properties[$this->properties['id_column']]] = $properties;

            }

            // also update the entries in the database
            // (notice that we're subtracting rather than adding and that finally we multiply by -1 so that the values
            // turn positive again)
            mysqli_query($this->connection, '

                UPDATE
                    `' . $this->properties['table_name'] . '`
                SET
                    `' . $this->properties['left_column'] . '` = (`' . $this->properties['left_column'] . '` - ' . $shift . ') * -1,
                    `' . $this->properties['right_column'] . '` = (`' . $this->properties['right_column'] . '` - ' . $shift . ') * -1
                WHERE
                    `' . $this->properties['left_column'] . '` < 0

            ');

            // finally, update the parent of the source node
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

    function getLookup($node = false){
        $this->_init();
        $data = array();
        $nodes = array();
        foreach ($this->lookup as $key=>$value){
            $data[$key] = $value;
            if($data[$key]['parent'] == $node){
                $nodes[$key] = $data[$key]['task'];
            }
        }


        return count($nodes);
       // return $data;


    }


}
?>
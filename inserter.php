<?php declare(strict_types=1);

require_once "config.php";
require_once "various.php";
require_once "validate.php";

$supported_operations = [
    "add_node" => "add_node",
    "add_edge" => "add_edge",
    "edit_node" => "edit_node",
    "move_node" => "move_node",
    "del_node" => "del_node",
    "del_edge" => "del_edge",
    "fetch_json" => "fetch_json"
];

/*
 * A function to simplify the flow of the program.
 * Performs all the actions and checks required.
 * Always returns an array. 
 *
 * Error Codes:
 * 0 - Success
 * 1 - Operation not specified
 * 2 - Invalid operation
 * 3 - Missing an argument
 * 4 - Wrong type of argument (or out of range)
 * 5 - Internal Error (details in syslog instead of printed)
 * 6 - Tried to create already-existing object
 * 7 - Tried to perform an operation involving a non-existing object
 * 8 - Arguments are invalid because of other reasons
 * 9 - Tried to perform an operation over an object that could not have it performed
 *      (at the moment, deleting a node with edges)
 */
function api() : array {

    global $config;
    global $supported_operations;
    global $sexes; //In validate.php
    
    $op = get_op();
    
    if ($op === false) return ["status" => 1, "msg"=> "Operation not set"];

    if(!array_key_exists($op, $supported_operations)) return ["status" => 2, "msg" => "Operation not supported"];

    try {
        $servername = $config["server"];
        $username = $config["user"];
        $password = $config["password"];
        $database = $config["database"];
        
        $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if ($op == "add_node") {
            
            //Check for existance of arguments
            $check_error = check_set(["name","x","y","year","sex"]);
            if(is_string($check_error)) return ["status" => 3,"action" => $op,"msg" => $check_error];
            //Get and sanitize name
            $node_name = sanitize_string($_POST["name"]);
            
            //Check validity of all other arguments
            $node_x = $_POST["x"];
            if (!validate_x($node_x)) return ["status" => 4, "action" => $op, "msg" =>"x-coordinate not an integer or out of range"];
            
            $node_y = $_POST["y"];
            if (!validate_y($node_y)) return ["status" => 4, "action" => $op,"msg" =>"y-coordinate not an integer or out of range"];
            
            $node_year = $_POST["year"];
            if (!validate_year($node_year)) return ["status" => 4, "action" => $op,"msg" =>"year not an integer or out of range"];
            
            $node_sex = $_POST["sex"];
            if (!array_key_exists($node_sex, $sexes)) return ["status" => 4, "action" => $op, "msg" => "Invalid sex"]; 
            
            //Get index of the new element
            $node_id = get_ix((int) $config["MIN_NODE_ID"], (int) $config["MAX_NODE_ID"],$conn);
            
            //Save to database
            $stmt = $conn->prepare("INSERT INTO nodes (id, name, x, y, year, sex) VALUES (:id, :name, :x, :y, :year, :sex)");
            $stmt->execute([
                ":id" => $node_id,
                ":name" => $node_name,
                ":x" => $node_x,
                ":y" => $node_y,
                ":year" => $node_year,
                ":sex" => $node_sex,
            ]);
            
            $check_exists = $conn->prepare("SELECT id FROM nodes WHERE id = :id");
            $check_exists->execute([":id" => $node_id]);
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) {
                syslog (LOG_ERR, "writeapi.php Internal ERROR: node does not appear after being inserted");
                return ["status" =>5, "action" => $op, "msg" => "Internal error"];
            }
            
            return ["status" => 0, "action" => $op, "par" => [
                "id" => (int) $node_id,
                "name" => $node_name,
                "x" => (int) $node_x,
                "y" => (int) $node_y,
                "year" => (int) $node_year,
                "sex" => $node_sex,
            ]];
            
        } elseif ($op == "add_edge") {
        
            //Check for existance of arguments
            $check_error = check_set(["a", "b"]);
            if(is_string($check_error)) return ["status" => 3,"action" => $op, "msg" => $check_error];
            
            $a = $_POST["a"];
            if (!validate_node($a)) return ["status" =>4, "action" => $op, "msg" => "Non-valid node a number"];
            
            $b = $_POST["b"];
            if (!validate_node($b)) return ["status" =>4, "action" => $op, "msg" => "Non-valid node b number"];
            
            $a = (int) $a;
            $b = (int) $b;
            
            $check_exists = $conn->prepare("SELECT id FROM nodes WHERE id = :id");
            
            //check existance of nodes a and b in the graf
            $check_exists->execute([":id" => $a]);
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) return ["status" =>7, "action" => $op, "msg" => "a node does not exist", "par" => [
                "a" => $a,
                "b" => $b,
            ]];
            
            $check_exists2 = $conn->prepare("SELECT id FROM nodes WHERE id = :id");
            $check_exists2->execute([":id" => $b]);
            $result2 = $check_exists2->FetchAll(PDO::FETCH_ASSOC);
            if (count($result2) == 0) return ["status" =>7, "action" => $op, "msg" => "b node does not exist", "par" => [
                "a" => $a,
                "b" => $b,
            ]];
            
            //make it so a < b
            if ($a > $b) {
                $t = $b;
                $b = $a;
                $a = $t;
            }
            
            
            if ($a == $b) return ["status" =>8, "action" => $op, "msg" => "The two nodes must be different"];
            
            //check for the existance of the edge
            $check_exists = $conn->prepare("SELECT id FROM edges WHERE a = :a and b = :b" );
            $check_exists->execute([":a" => $a, ":b" => $b]);
            
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) != 0) return ["status" =>6, "action" => $op, "msg" => "Edge already exists"];
            
            //add edge
            $stmt = $conn->prepare("INSERT INTO edges (votes, a, b) VALUES (1, :a, :b)");
            $stmt->execute([":a" => $a, ":b" => $b]);
            
            //check if edge has been added
            $check_exists = $conn->prepare("SELECT id FROM edges WHERE a = :a and b = :b" );
            $check_exists->execute([":a" => $a, ":b" => $b]);
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) {
                //if it hasn't, raise an error
                syslog (LOG_ERR, "writeapi.php Internal ERROR: edge does not appear after being inserted");
                return [5, $op, "msg" => "Internal error"];
            } else {
                //if it has, get the id of the new edge
                $num = (int) $result[0]["id"];
            }
            
            return ["status" => 0, "action" => $op, "par" => [
                "id" => $num,
                "a" => $a,
                "b" => $b,
            ]];
        } elseif ($op == "edit_node") {
            $check_error = check_set(["id"]);
            if(is_string($check_error)) return ["status" => 3,"action" => $op, "msg" => $check_error];
            
            $id = $_POST["id"];
            if (!validate_node($id)) return ["status" =>4, "action" => $op, "msg" => "Non-valid node number"];
            
            $check_exists = $conn->prepare("SELECT id FROM nodes WHERE id = :id");
            $check_exists->execute([":id" => $id]);
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) return ["status" =>7, "action" => $op, "msg" => "node does not exist"];
            
            $executed_ops = 0;
            
            $par = ["id" => (int) $id];
            
            if (check_set(["year"]) === true) {
                $node_year = $_POST["year"];
                if (!validate_year($node_year)) return ["status" => 4, "action" => $op,"msg" =>"year not an integer or out of range"];
                $year = true; 
                $par["year"] = (int) $node_year;
            }
                
            if (check_set(["sex"]) === true) {
                $node_sex = $_POST["sex"];
                if (!array_key_exists($node_sex, $sexes)) return ["status" => 4, "action" => $op, "msg" => "Invalid sex"];
                $sex = true;
                $par["sex"] = $node_sex;
            }
                
            if (check_set(["name"]) === true) {
                $node_name = sanitize_string($_POST["name"]);
                $name = true;
                $par["name"] = $node_name;
            }
            
            if($sex) {
                $stmt = $conn->prepare("UPDATE nodes SET sex =:sex WHERE id = :id");
                $stmt->execute([":id" => $id,":sex" => $node_sex]);
                ++$executed_ops;
            }
            if($year) {
                $stmt = $conn->prepare("UPDATE nodes SET year =:year WHERE id = :id");
                $stmt->execute([":id" => $id,":year" => $node_year]);
                ++$executed_ops;
            }
            if($name) {
                $stmt = $conn->prepare("UPDATE nodes SET name = :name WHERE id = :id");
                $stmt->execute([":id" => $id,":name" => $node_name]);
                ++$executed_ops;
            }
            if ($executed_ops == 0) {
                return ["status" => 3,"action" => $op, "msg" => "Tried to edit a node but specified no fields to edit"];
            } else {
                return ["status" => 0, "action" => $op, "par" => $par];
            }
        } elseif ($op == "move_node") {
            $check_error = check_set(["id","x","y"]);
            if(is_string($check_error)) return ["status" => 3,"action" => $op, "msg" => $check_error];
            
            $id = $_POST["id"];
            if (!validate_node($id)) return ["status" =>4, "action" => $op, "msg" => "Non-valid node number"];
            
            $check_exists = $conn->prepare("SELECT id FROM nodes WHERE id = :id");
            $check_exists->execute([":id" => $id]);
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) return ["status" =>7, "action" => $op, "msg" => "node does not exist"];
        
            $node_x = $_POST["x"];
            if (!validate_x($node_x)) return ["status" => 4, "action" => $op, "msg" =>"x-coordinate not an integer or out of range"];
            
            $node_y = $_POST["y"];
            if (!validate_y($node_y)) return ["status" => 4, "action" => $op,"msg" =>"y-coordinate not an integer or out of range"];
            
            $stmt = $conn->prepare("UPDATE nodes SET x =:x, y=:y WHERE id = :id");
            $stmt->execute([":id" => $id,":x" => $node_x, ":y" => $node_y]);
            
            return ["status" => 0, "action" => $op, "par" => ["id" => $id, "x" => $x, "y" => $y]];
        } elseif ($op == "del_node") {
            
            $check_error = check_set(["id"]);
            if(is_string($check_error)) return ["status" => 3,"action" => $op, "msg" => $check_error];
            
            $id = $_POST["id"];
            if (!validate_node($id)) return ["status" =>4, "action" => $op, "msg" => "Non-valid node number"];
            
            $id = (int) $id;
            
            $check_exists = $conn->prepare("SELECT id FROM nodes WHERE id = :id");
            $check_exists->execute([":id" => $id]);
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) return ["status" =>7, "action" => $op, "msg" => "node does not exist"];
            
            $check_exists = $conn->prepare("SELECT id FROM edges WHERE a = :a or b = :b" );
            $check_exists->execute([":a" => $id, ":b" => $id]);
            
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) != 0) return ["status" =>9, "action" => $op, "msg" => "Trying to delete a node with edges"];
            
            $stmt = $conn->prepare("DELETE FROM nodes WHERE id = :id");
            $stmt->execute([":id" => $id]);
            
            $check_exists = $conn->prepare("SELECT id FROM nodes WHERE id = :id" );
            $check_exists->execute([":id" => $id]);
            
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) != 0) {
                syslog (LOG_ERR, "Node deleted but still present in database");
                return ["status" =>5, "action" => $op, "msg" => "Internal error"];
            }
            return ["status" => 0, "action" => $op, "par" => ["id" => $id]];
            
            
        } elseif ($op == "del_edge") {
            $par = [];
            $check_error = check_set(["id"]);
            if(is_string($check_error)) {
                $check_error = check_set(["a","b"]);
                if(is_string($check_error)) return ["status" => 3,"action" => $op, "msg" => "Missing both id and a and b, one way to identify the edge is needed to delete it"];
                
                $a = $_POST["a"];
                if (!validate_node($a)) return ["status" =>4, "action" => $op, "msg" => "Non-valid node a number"];
                
                $b = $_POST["b"];
                if (!validate_node($b)) return ["status" =>4, "action" => $op, "msg" => "Non-valid node b number"];
                
                $a = (int) $a;
                $b = (int) $b;
                
                if ($a > $b) {
                    $aux = $b;
                    $b = $a;
                    $a = $aux;
                }
                
                $check_exists = $conn->prepare("SELECT id FROM edges WHERE a = :a and b = :b" );
                $check_exists->execute([":a" => $a, ":b" => $b]);
            
                $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
                if (count($result) == 0) return ["status" => 7, "action" => $op, "msg" => "Edge does not exist"];
                
                $id = (int) $result[0]["id"];
                $par = ["a" => $a, "b" => $b, "id" => $id]; 
                
            } else {
                $id = $_POST["id"];
                if (!validate_edge($id)) return ["status" =>4, "action" => $op, "msg" => "Non-valid edge number"];
                $par["id"] = $id;
                
                $check_exists = $conn->prepare("SELECT id FROM edges WHERE id = :id");
                $check_exists->execute([":id" => $id]);
                $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
                if (count($result) == 0) return ["status" =>7, "action" => $op, "msg" => "Edge does not exist"];
            }
            
            $stmt = $conn->prepare("DELETE FROM edges WHERE id = :id");
            $stmt->execute([":id" => $id]);
            
            $check_exists = $conn->prepare("SELECT id FROM edges WHERE id = :id" );
            $check_exists->execute([":id" => $id]);
            
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) != 0) {
                syslog (LOG_ERR, "Edge deleted but still present in database");
                return ["status" =>5, "action" => $op, "msg" => "Internal error"];
            }
            return ["status" => 0, "action" => $op, "par" => $par];
            
        } elseif ($op == "fetch_json") {
            $stmt = $conn->prepare("SELECT id, name, year, x, y, sex FROM nodes");
            $stmt->execute();
            $nodes_prenum = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $nodes = [];
            foreach ($nodes_prenum as $value) 
            $nodes[$value['id']] = [
                "id" => (int)($value['id']),
                "name" => $value['name'],
                "year" => (int) ($value['year']),
                "x" => (int) ($value['x']),
                "y" => (int) ($value['y']),
                "sex" => $value['sex']
            ];

            //echo json_encode($nodes);
            $stmt2 = $conn->prepare("SELECT * FROM edges");
            $stmt2->execute();
            $edgespre = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            $edges = [];
            foreach($edgespre as $value)
            $edges[$value['a'] . '_' . $value['b'] ] = [
                "votes" => (int) $value['votes'],
                "a" => (int) $value['a'],
                "b" => (int) $value['b'] 
            ];
            $graf = ["nodes" => $nodes, "edges" => $edges];
            return ["status" => 0, "action" => $op, "data" => $graf];
        }
        
        
    } catch(PDOException $e) {
        syslog (LOG_ERR, "writeapi.php PDO ERROR:  " . $e->getMessage());
        return ["status" => 5, "action" => $op, "msg"=>"Internal error"];
    }


}
 echo json_encode(api());
?>

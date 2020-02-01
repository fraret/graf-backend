<?php declare(strict_types=1);

require_once "config.php";
require_once "various.php";
require_once "validate.php";

$supported_operations = array(
    "add_node" => "add_node",
    "add_edge" => "add_edge",
);

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
 * 8 - Arguments invalid for other reasons
 */
function api() : array {

    global $config;
    global $supported_operations;
    global $sexes; //In validate.php
    
    $op = get_op();
    
    if ($op === false) return [1, "Operation not set"];

    if(!array_key_exists($op, $supported_operations)) return [ 2, "Operation not supported"];

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
            if(is_string($check_error)) return [3,$op,$check_error];
            //Get and sanitize name
            $node_name = sanitize_string($_POST["name"]);
            
            //Check validity of all other arguments
            $node_x = $_POST["x"];
            if (!validate_x($node_x)) return [4, "x-coordinate not an integer or out of range"];
            
            $node_y = $_POST["y"];
            if (!validate_y($node_y)) return [4, "y-coordinate not an integer or out of range"];
            
            $node_year = $_POST["year"];
            if (!validate_year($node_year)) return [4, "year not an integer or out of range"];
            
            $node_sex = $_POST["sex"];
            if (!array_key_exists($node_sex, $sexes)) return [4, "Invalid sex"]; 
            
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
            
            return [0, $op, [
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
            if(is_string($check_error)) return [3,$op,$check_error];
            
            $a = $_POST["a"];
            if (!validate_node($a)) return [4, $op, "Non-valid node a number"];
            
            $b = $_POST["b"];
            if (!validate_node($b)) return [4, $op, "Non-valid node b number"];
            
            $a = (int) $a;
            $b = (int) $b;
            
            $check_exists = $conn->prepare("SELECT id FROM nodes WHERE id = :id");
            
            $check_exists->execute([":id" => $a]);
            
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) return [7, $op, "a node does not exist"];
            
            $check_exists->execute([":id" => $b]);
            
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) return [7, $op, "b node does not exist"];
            
            if ($a > $b) {
                $t = $b;
                $b = $a;
                $a = $t;
            }
            
            if ($a == $b) return [8, $op, "The two nodes must be different"];
            
            $check_exists = $conn->prepare("SELECT id FROM edges WHERE a = :a and b = :b" );
            $check_exists->execute([":a" => $a, ":b" => $b]);
            
            $result = $check_exists->FetchAll(PDO::FETCH_ASSOC);
            if (count($result) == 0) return [6, $op, "Edge already exists"];
            
            $stmt = $conn->prepare("INSERT INTO edges (votes, a, b) VALUES (1, :a, :b)");
            
            $stmt->execute([":a" => $a, ":b" => $b]);
            
            $check_exists = $conn->prepare("SELECT id FROM edges WHERE a = :a and b = :b" );
            $check_exists->execute([":a" => $a, ":b" => $b]);
            if (count($result) == 0) {
                syslog (LOG_ERR, "writeapi.php Internal ERROR: edge does not appear after being inserted");
                return [5, $op, "Internal error"];
            } else {
                $num = (int) $result[0]["id"];
            }
            
            return [0, $op, [
                "id" => $num,
                "a" => $a,
                "b" => $b,
            ]];
        }
        
        
    } catch(PDOException $e) {
        syslog (LOG_ERR, "writeapi.php PDO ERROR:  " . $e->getMessage());
        return [5, $op, "Internal error"];
    }


}
 echo json_encode(api());
?>

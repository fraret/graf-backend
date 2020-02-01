<?php declare(strict_types=1);

function check_set(array $vars) {
        foreach ($vars as $par) {
            if(!isset($_POST[$par])) {
                return $par . " is not set in the POST request and is a mandatory parameter\n";
            }
        }
        return true;
}

function get_op() {
    if(!empty($_POST["action"])) {
        return $_POST["action"];
    }
    return false;
}

function sanitize_string(string $s) : string {
    return htmlspecialchars(trim($s), ENT_QUOTES);
}


/* Returns the index that should be used for a new element.
 * To do so, it looks in the database for elements with index in [min, max)
 * and sums one to the highest one. If none are found, it returns min.
 */
function get_ix(int $min, int $max, PDO &$conn) : int {

    $stmt = $conn->prepare("SELECT id FROM nodes WHERE id < :max and id >= :min ORDER BY id DESC LIMIT 1");
    $stmt->execute([":min" => $min, ":max" => $max]);
    $result = $stmt->FetchAll(PDO::FETCH_ASSOC);
    if (count($result)) {
        return $result[0]["id"]+1;
    } else {
        return $min;
    }
}

?>

<?php declare(strict_types=1);

require_once "config.php";

$sexes = array(
    "M" => "Male",
    "F" => "Female",
    "U" => "Undefined",
);

function validate_num(string $num, int $min, int $max) : bool {
    return (! ((bool)(filter_var($num, FILTER_VALIDATE_INT, ["options" => ["min_range"=>$min, "max_range"=>$max]]) === false)));
}

function validate_x(string $x) : bool {
    global $config;
    return validate_num($x, (int) $config["MIN_X"], (int) $config["MAX_X"]);
}

function validate_y(string $y) : bool {
    global $config;
    return validate_num($y, (int) $config["MIN_Y"], (int) $config["MAX_Y"]);
}

function validate_year(string $y) : bool {
    global $config;
    return validate_num($y, (int) $config["MIN_YEAR"], (int) $config["MAX_YEAR"]);
}

function validate_edge(string $y) : bool {
    return validate_num($y, 0, MAX_INT);
}

function validate_node(string $num) : bool {
    return validate_num($num, 0, MAX_INT);
}

?>

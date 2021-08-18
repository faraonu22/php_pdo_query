<?php
require("functions.php");

$func = new Functions();

$func->sql_exec("Query...", "return");
$func->sql_exec("Query...", "1");
$func->sql_exec("Query...", "0");

?>
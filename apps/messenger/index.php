<?php

include("controller.php");
include("utils.php");

$parent_path=dirname(dirname(dirname(dirname(__FILE__))));
$webdb_path=$parent_path.DIRECTORY_SEPARATOR."webdb".DIRECTORY_SEPARATOR;
include($webdb_path."index.php");

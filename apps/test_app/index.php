<?php

include("computed_fields.php");
include("stubs.php");

$parent_path=dirname(dirname(dirname(dirname(__FILE__))));
$webdb_path=$parent_path.DIRECTORY_SEPARATOR."webdb".DIRECTORY_SEPARATOR;
include($webdb_path."index.php");

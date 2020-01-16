<?php

namespace webdb\test\functional;

#####################################################################################################

function run_functional_tests()
{
  global $settings;
  require_once("test".DIRECTORY_SEPARATOR."functional_utils.php");
  \webdb\test\utils\test_info_message("STARTING WEBDB FUNCTIONAL TESTS...");
  $settings["test_error_handler"]="\\webdb\\test\\functional\\utils\\functional_test_error_callback";
  \webdb\test\utils\test_cleanup();
  \webdb\test\utils\apply_test_app_settings();
  \webdb\test\functional\filtered_checklist_tests();
  \webdb\test\utils\restore_app_settings();
}

#####################################################################################################

function filtered_checklist_tests()
{
  global $settings;
  #\webdb\test\utils\test_info_message("running filtered checklist tests...");
  # use test app

  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  /*$test_case_msg="";
  $test_success=true;

  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  if (\webdb\test\utils\compare_template("csrf_error",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);*/

}

#####################################################################################################

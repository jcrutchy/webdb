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
  #\webdb\test\functional\filtered_checklist_tests();
  \webdb\test\utils\restore_app_settings();
  \webdb\test\utils\test_info_message("FINISHED WEBDB FUNCTIONAL TESTS");
}

#####################################################################################################

function filtered_checklist_tests()
{
  global $settings;
  #\webdb\test\utils\test_info_message("running filtered checklist tests...");
  # use test app's subform_location_items checklist as an example

  # http://192.168.43.50/webdb/doc/test/test_app/index.php?page=locations&cmd=edit&id=2

  $page_id="locations";
  $form_config=\webdb\forms\get_form_config($page_id,true);
  if ($form_config===false)
  {
    \webdb\test\utils\test_error_message("form config not found: ".$page_id);
  }
  \webdb\forms\checklist_update($form_config);

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

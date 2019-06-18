<?php

namespace webdb\testing;

#####################################################################################################

function output_all_tests()
{
  global $settings;
  $settings["system_message_flag"]=false;
  $settings["system_message_output"]="";
  $settings["overall_test_pass"]=true;
  $settings["test_messages"]=array();
  \webdb\testing\run_all_tests();
  ob_end_clean();
  if ($settings["overall_test_pass"]==true)
  {
    $settings["test_messages"][]="<p style='color: green; font-weight: bold;'>All tests passed!</p>";
  }
  echo "<html><head><title>UNIT TEST RESULTS</title></head><body style='font-family: monospace;'><p style='font-weight: bold;'>UNIT TEST RESULTS</p>";
  for ($i=0;$i<count($settings["test_messages"]);$i++)
  {
    echo $settings["test_messages"][$i];
  }
  echo "</body></html>";
  die();
}

#####################################################################################################

function add_message($message,$success=false)
{
  global $settings;
  if ($success==true)
  {
    $styled_message="<p style='color: green; font-style: italic;'>".$message."</p>";
  }
  else
  {
    $styled_message="<p style='color: red; font-style: italic;'>".$message."</p>";
  }
  $settings["test_messages"][]=$styled_message;
}

#####################################################################################################

function run_all_tests()
{
  \webdb\testing\check_used_templates_exist();
  if (\webdb\utils\is_app_mode()==false)
  {
    return;
  }
  global $settings;
  $file_list=scandir($settings["app_testing_path"]);
  $valid=array();
  for ($i=0;$i<count($file_list);$i++)
  {
    $fn=$file_list[$i];
    if (($fn==".") or ($fn=="..") or ($fn==".git"))
    {
      continue;
    }
    $full=$path.$fn;
    $fext=pathinfo($full,PATHINFO_EXTENSION);
    if (strtolower($ext)<>"php")
    {
      continue;
    }
    include($full);
    $run_function=$settings["app_root_namespace"]."testing\\".pathinfo($full,PATHINFO_FILENAME)."\\run_all_tests";
    if (function_exists($run_function)==false)
    {
      \webdb\utils\system_message("error: missing function '".$run_function."' in file '".$full."'");
    }
    $valid[]=$run_function;
  }
  for ($i=0;$i<count($valid);$i++)
  {
    call_user_func($valid[$i]);
  }
}

#####################################################################################################

function check_used_templates_exist()
{
  global $settings;
  foreach ($settings["templates"] as $key => $value)
  {
#$settings["overall_test_pass"]
  }
}

#####################################################################################################

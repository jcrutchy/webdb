<?php

namespace webdb\cli;

#####################################################################################################

function is_cli_mode()
{
  global $argv;
  if ((isset($_SERVER["argv"])==true) and (isset($argv[1])==true))
  {
    return true;
  }
  else
  {
    return false;
  }
}

#####################################################################################################

function cli_dispatch()
{
  global $settings;
  global $argv;
  switch ($argv[1])
  {
    case "init_webdb_schema":
      \webdb\sql\file_execute_prepare("webdb_schema",array(),true);
      \webdb\utils\system_message("webdb schema initialised");
    case "validate_json":
      echo "validating forms...".PHP_EOL;
      foreach ($settings["forms"] as $form_name => $form_data)
      {
        $result=trim(shell_exec("jsonlint-php ".escapeshellarg($form_data["filename"])));
        echo "validating form '".$form_name."': ".$result.PHP_EOL;
        if ($result<>"Valid JSON")
        {
          die;
        }
      }
      die;
    case "format_json":
      echo "formatting forms...".PHP_EOL;
      foreach ($settings["forms"] as $form_name => $form_data)
      {
        $filename=$form_data["filename"];
        unset($form_data["filename"]);
        $data=json_encode($form_data,JSON_PRETTY_PRINT);
        if ($data===false)
        {
          \webdb\utils\system_message("error encoding form json: ".$filename);
        }
        if (file_put_contents($filename,$data)===false)
        {
          \webdb\utils\system_message("error writing form file: ".$filename);
        }
        echo "formatted form: ".$filename.PHP_EOL;
      }
      die;
    case "init_app_schema":
      $filename=$settings["app_sql_path"]."schema.sql";
      if (file_exists($filename)==true)
      {
        $sql=trim(file_get_contents($filename));
        \webdb\sql\execute_prepare($sql,array(),"",true);
        \webdb\utils\system_message("app schema initialised");
      }
      else
      {
        \webdb\utils\system_message("error: schema file not found: ".$filename);
      }
  }
}

#####################################################################################################

<?php

namespace webdb\cli;

#####################################################################################################

function term_echo($msg,$color=false)
{
  if ($color===false)
  {
    echo $msg.PHP_EOL;
  }
  else
  {
    echo "\033[".$color."m".$msg."\033[0m".PHP_EOL;
    /*
      39 = default
      30 = black
      31 = red
      32 = green
      33 = yellow
      34 = blue
      35 = magenta
      36 = cyan
      37 = light gray
      90 = dark gray
      91 = light red
      92 = light green
      93 = light yellow
      94 = light blue
      95 = light magenta
      96 = light cyan
      97 = white
    */
  }
}

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
    case "run_tests":
      require_once("test".DIRECTORY_SEPARATOR."test.php");
      \webdb\test\run_tests();
      die;
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

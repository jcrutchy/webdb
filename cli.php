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
      $app_file_list=scandir($settings["app_forms_path"]);
      for ($i=0;$i<count($app_file_list);$i++)
      {
        $fn=$app_file_list[$i];
        if (($fn==".") or ($fn==".."))
        {
          continue;
        }
        $full=$settings["app_forms_path"].$fn;
        $result=trim(shell_exec("jsonlint-php ".escapeshellarg($full)));
        echo "validating form '".$fn."': ".$result.PHP_EOL;
        if ($result<>"Valid JSON")
        {
          die;
        }
      }
      die;
    case "format_json":
      echo "formatting forms...".PHP_EOL;
      foreach ($settings["forms"] as $page_id => $form_data)
      {
        $filename=$form_data["filename"];
        unset($form_data["filename"]);
        unset($form_data["basename"]);
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
    case "unused_fields":
      if (isset($argv[2])==false)
      {
        \webdb\utils\system_message("database name not specified");
      }
      $settings["forms"]=array();
      \webdb\forms\load_form_defs();
      $sql_params=array();
      $sql_params["database"]=$argv[2];
      $sql=\webdb\utils\sql_fill("column_list",$sql_params);
      $records=\webdb\sql\fetch_prepare($sql,array(),"",true);
      $unused_columns=array();
      for ($i=0;$i<count($records);$i++)
      {
        $column_record=$records[$i];
        $column_used=false;
        foreach ($settings["forms"] as $page_id => $form_config)
        {
          foreach ($form_config["control_types"] as $field_name => $control_type)
          {
            if (($form_config["database"]==$column_record["TABLE_SCHEMA"]) and ($form_config["table"]==$column_record["TABLE_NAME"]) and ($field_name==$column_record["COLUMN_NAME"]))
            {
              $column_used=true;
              break;
            }
          }
        }
        if ($column_used==false)
        {
          $unused_columns[]=implode(".",$column_record);
        }
      }
      echo implode(PHP_EOL,$unused_columns).PHP_EOL;
      die;
  }
}

#####################################################################################################

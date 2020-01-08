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
      \webdb\cli\term_echo("validating forms...");
      $app_files=\webdb\utils\load_files($settings["app_forms_path"],"","",false);
      $app_file_list=array_keys($app_files);
      for ($i=0;$i<count($app_file_list);$i++)
      {
        $fn=$app_file_list[$i];
        $full=$settings["app_forms_path"].$fn;
        $result=trim(shell_exec("jsonlint-php ".escapeshellarg($full)));
        \webdb\cli\term_echo("validating form '".$fn."': ".$result);
        if ($result<>"Valid JSON")
        {
          die;
        }
      }
      die;
    case "format_json":
      \webdb\cli\term_echo("formatting forms...");
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
      \webdb\cli\unused_fields($argv[2]);
    case "generate_form":
      if (isset($argv[2])==false)
      {
        \webdb\utils\system_message("database name not specified");
      }
      if (isset($argv[3])==false)
      {
        \webdb\utils\system_message("table name not specified");
      }
      if ($argv[3]=="all")
      {
        \webdb\cli\generate_all_forms($argv[2]);
      }
      if (isset($argv[4])==false)
      {
        \webdb\utils\system_message("filename not specified");
      }
      \webdb\cli\generate_form($argv[2],$argv[3],$argv[4]);
      die;
  }
}

#####################################################################################################

function unused_fields($database)
{
  global $settings;
  $settings["forms"]=array();
  \webdb\forms\load_form_defs();
  $sql_params=array();
  $sql_params["database"]=$database;
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
  \webdb\cli\term_echo(implode(PHP_EOL,$unused_columns));
  die;
}

#####################################################################################################

function generate_all_forms($database)
{
  global $settings;
  $settings["forms"]=array();
  \webdb\forms\load_form_defs();
  $cmd="rm -rf ".escapeshellarg($settings["app_forms_path"]);
  shell_exec($cmd);
  mkdir($settings["app_forms_path"]);
  $records=\webdb\cli\table_list($database);
  for ($i=0;$i<count($records);$i++)
  {
    $table_data=$records[$i];
    $table_name=$table_data["TABLE_NAME"];
    \webdb\cli\generate_form($database,$table_name,$table_name.DIRECTORY_SEPARATOR.$table_name.".list");
  }
  die;
}

#####################################################################################################

function generate_form($database,$table,$filename,$link_subforms_only=true)
{
  global $settings;
  $settings["forms"]=array();
  \webdb\forms\load_form_defs();
  $records=\webdb\cli\column_list($database,$table);
  $full_filename=$settings["app_forms_path"].$filename;
  $form_type=pathinfo($full_filename,PATHINFO_EXTENSION);
  $page_id=pathinfo($full_filename,PATHINFO_FILENAME);
  $newname="";
  if (file_exists($full_filename)==true)
  {
    $newname=$full_filename."_old";
    rename($full_filename,$newname);
    \webdb\cli\term_echo("[ALERT] existing file found, renamed from '".$full_filename."' to '".$newname."'",34);
  }
  $form_data=$settings["form_defaults"][$form_type];
  $form_data["title"]=$table;
  $form_data["page_id"]=$page_id;
  $form_data["database"]=$database;
  $form_data["table"]=$table;
  $form_data["primary_key"]=array();
  $foreign_key_defs=\webdb\cli\table_foreign_keys($database,$table);
  $control_types=array(
    "int"=>"text",
    "timestamp"=>"date",
    "varchar"=>"text",
    "longtext"=>"memo",
    "tinyint"=>"checkbox");
  for ($i=0;$i<count($records);$i++)
  {
    $field_data=$records[$i];
    $field_name=$field_data["COLUMN_NAME"];
    $data_type=$field_data["DATA_TYPE"];
    if ($field_data["COLUMN_KEY"]=="PRI")
    {
      $form_data["primary_key"][]=$field_name;
    }
    $control_type=$control_types[$data_type];
    $default_value="";
    switch ($data_type)
    {
      case "tinyint":
        $default_value=false;
        break;
      case "timestamp":
        if ($field_data["COLUMN_DEFAULT"]=="CURRENT_TIMESTAMP")
        {
          $default_value="(auto)";
          $control_type="span";
        }
        break;
      case "int":
        if ($field_data["EXTRA"]=="auto_increment")
        {
          $default_value="(auto)";
          $control_type="span";
          break;
        }
        for ($j=0;$j<count($foreign_key_defs);$j++)
        {
          $fk=$foreign_key_defs[$j];
          if ($field_name==$fk["COLUMN_NAME"])
          {
            $control_type="combobox";
            $lookup_def=array();
            $lookup_def["database"]=$fk["REFERENCED_TABLE_SCHEMA"];
            $lookup_def["table"]=$fk["REFERENCED_TABLE_NAME"];
            $lookup_def["key_field"]=$fk["REFERENCED_COLUMN_NAME"];
            $lookup_def["sibling_field"]=$fk["COLUMN_NAME"];
            $lookup_table_columns=\webdb\cli\column_list($lookup_def["database"],$lookup_def["table"]);
            $display_field=$lookup_def["key_field"];
            for ($k=0;$k<count($lookup_table_columns);$k++)
            {
              if ($lookup_table_columns[$k]["DATA_TYPE"]=="varchar")
              {
                $display_field=$lookup_table_columns[$k]["COLUMN_NAME"];
                break;
              }
            }
            $lookup_def["display_field"]=$display_field;
            $lookup_def["display_format"]="%s";
            $lookup_def["lookup_sql_file"]="";
            $form_data["lookups"][$field_name]=$lookup_def;
            break;
          }
        }
        break;
    }
    $form_data["control_types"][$field_name]=$control_type;
    $form_data["default_values"][$field_name]=$default_value;
    $form_data["captions"][$field_name]=\webdb\cli\captionize($field_name);
    $form_data["visible"][$field_name]=true;
  }
  $edit_cmd_id="";
  $edit_cmd_page_id=$page_id;
  $noun=\webdb\utils\trim_suffix(\webdb\cli\captionize($table),"s");
  if (count($form_data["primary_key"])>1)
  {
    $edit_cmd_id=$form_data["primary_key"][0];
    $page_id_parts=explode("_",$page_id);
    $subform_check_part=array_shift($page_id_parts);
    if ($subform_check_part<>"subform")
    {
      /*for ($i=0;$i<count($form_data["primary_key"]);$i++)
      {
        $key=$form_data["primary_key"][$i];
        $key=\webdb\utils\trim_suffix($key,"_id");
        $subdir=$key;
        if (substr($subdir,-1)<>"s")
        {
          $subdir.="s";
        }
        $full_subdir=$settings["app_forms_path"].$subdir;
        if (file_exists($full_subdir)==false)
        {
          mkdir($full_subdir);
        }
        $other_keys=$form_data["primary_key"];
        unset($other_keys[$i]);
        $other_keys=array_values($other_keys);
        $subform_filename_prefix=$subdir.DIRECTORY_SEPARATOR."subform_".$key;
        for ($j=0;$j<count($other_keys);$j++)
        {
          $other_key=\webdb\utils\trim_suffix($other_keys[$j],"_id");
          $subform_filename=$subform_filename_prefix."_".$other_key."s.".$form_type;
          \webdb\cli\generate_form($database,$table,$subform_filename);
        }
      }*/
      if ($link_subforms_only==true)
      {
        return; # disables generation of form specified in $filename argument
      }
    }
    else
    {
      $edit_cmd_id=array_shift($page_id_parts);
      $noun=\webdb\utils\trim_suffix($edit_cmd_id,"_id");
      $edit_cmd_page_id=$noun;
      if (substr($edit_cmd_page_id,-1)<>"s")
      {
        $edit_cmd_page_id.="s";
      }
      $noun=\webdb\cli\captionize($noun);
    }
  }
  $form_data["primary_key"]=implode(",",$form_data["primary_key"]);
  $form_data["edit_cmd_id"]=$edit_cmd_id;
  $form_data["edit_cmd_page_id"]=$edit_cmd_page_id;
  $form_data["command_caption_noun"]=$noun;
  $form_data["edit_button_caption"]="Edit ".$noun;
  $foreign_key_defs=\webdb\sql\get_foreign_key_defs($database,$table);
  for ($i=0;$i<count($foreign_key_defs);$i++)
  {
    $fk=$foreign_key_defs[$i];
    $link_table=$fk["TABLE_NAME"];
    $link_table_parts=explode("_",$link_table);
    $n=count($link_table_parts);
    for ($j=0;$j<$n;$j++)
    {
      if (($link_table_parts[$j]==strtolower($noun)) or ($link_table_parts[$j]=="links"))
      {
        unset($link_table_parts[$j]);
      }
    }
    $link_page_id=array_values($link_table_parts);
    $link_page_id=implode("_",$link_page_id);
    if (substr($link_page_id,-1)<>"s")
    {
      $link_page_id.="s";
    }
    $subform_page_id="subform_".\webdb\utils\trim_suffix($table,"s")."_".$link_page_id;
    $subform_key=$fk["REFERENCED_COLUMN_NAME"];
    $form_data["edit_subforms"][$subform_page_id]=$subform_key;
    $subform_database=$fk["TABLE_SCHEMA"];
    $subform_table=$fk["TABLE_NAME"];
    $subform_filename=$fk["REFERENCED_TABLE_NAME"].DIRECTORY_SEPARATOR.$subform_page_id.".".$form_type;
    if (file_exists($settings["app_forms_path"].$subform_filename)==false)
    {
      term_echo($subform_database.".".$subform_table." => ".$subform_filename);
      #\webdb\cli\generate_form($subform_database,$subform_table,$subform_filename);
    }
  }
  unset($form_data["filename"]);
  unset($form_data["basename"]);
  $form_data=json_encode($form_data,JSON_PRETTY_PRINT);
  if ($form_data===false)
  {
    \webdb\utils\system_message("error encoding form json: ".$full_filename);
  }
  $path=dirname($full_filename);
  if (file_exists($path)==false)
  {
    mkdir($path);
  }
  if (file_put_contents($full_filename,$form_data)===false)
  {
    \webdb\utils\system_message("error writing form file: ".$full_filename);
  }
  #\webdb\cli\term_echo("generated form: ".$full_filename);
  if ($newname<>"")
  {
    if (file_exists($newname)==true)
    {
      unlink($newname);
      \webdb\cli\term_echo("[ALERT] renamed file '".$newname."' deleted",34);
    }
  }
}

#####################################################################################################

function captionize($field_name)
{
  if ($field_name=="")
  {
    return "";
  }
  $parts=explode("_",$field_name);
  for ($i=0;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    if (($part=="") or ($part=="id"))
    {
      unset($parts[$i]);
      continue;
    }
    $part[0]=strtoupper($part[0]);
    $parts[$i]=$part;
  }
  return implode(" ",array_values($parts));
}

#####################################################################################################

function table_list($database)
{
  $sql_params=array();
  $sql_params["database"]=$database;
  return \webdb\sql\file_fetch_prepare("generate_form_table_list",$sql_params);
}

#####################################################################################################

function column_list($database,$table)
{
  $sql_params=array();
  $sql_params["table"]=$table;
  $sql_params["database"]=$database;
  return \webdb\sql\file_fetch_prepare("generate_form_column_list",$sql_params);
}

#####################################################################################################

function table_foreign_keys($database,$table)
{
  $sql_params=array();
  $sql_params["database"]=$database;
  $sql_params["table"]=$table;
  return \webdb\sql\file_fetch_prepare("generate_form_foreign_keys",$sql_params,$table,$database);
}

#####################################################################################################

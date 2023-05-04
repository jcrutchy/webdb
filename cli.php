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
  if (defined("STDIN")==true)
  {
    return true;
  }
  if (php_sapi_name()==="cli")
  {
    return true;
  }
  if (array_key_exists("SHELL",$_ENV)==true)
  {
    return true;
  }
  if ((empty($_SERVER["REMOTE_ADDR"])==true) and (isset($_SERVER["HTTP_USER_AGENT"])==false) and (count($_SERVER["argv"])>0))
  {
    return true;
  }
  if (array_key_exists("REQUEST_METHOD",$_SERVER)==false)
  {
    return true;
  }
  return false;
}

#####################################################################################################

function cli_dispatch()
{
  global $settings;
  global $argv;
  if (isset($argv[1])==false)
  {
    die;
  }
  $arg=$argv[1];
  ini_set("max_execution_time","0");
  switch ($arg)
  {
    /*case "run_tests":
      \webdb\utils\load_settings();
      \webdb\utils\database_connect();
      require_once("test".DIRECTORY_SEPARATOR."test.php");
      \webdb\test\run_tests();
      die;
    case "init_webdb_schema":
      \webdb\utils\load_settings();
      \webdb\utils\database_connect();
      \webdb\utils\init_webdb_schema();
      \webdb\utils\system_message("webdb schema initialised");
    case "validate_json":
      \webdb\cli\term_echo("validating forms...");
      \webdb\utils\initialize_settings();
      \webdb\utils\load_webdb_settings();
      \webdb\utils\load_application_settings();
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
      \webdb\utils\load_settings();
      \webdb\utils\database_connect();
      \webdb\utils\init_app_schema();
      \webdb\utils\system_message("app schema initialised");
    case "unused_fields":
      \webdb\utils\load_settings();
      \webdb\utils\database_connect();
      if (isset($argv[2])==false)
      {
        \webdb\utils\system_message("database name not specified");
      }
      \webdb\cli\unused_fields($argv[2]);
    case "generate_form":
      \webdb\utils\load_settings();
      \webdb\utils\database_connect();
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
      die;*/
  }
  \webdb\utils\load_settings();
  \webdb\utils\database_connect();
  if (isset($settings["cli_dispatch"][$arg])==false)
  {
    die;
  }
  foreach ($settings["cli_dispatch"][$arg] as $include_filename => $dispatch_function)
  {
    $includes=get_included_files();
    if (in_array($include_filename,$includes)==false)
    {
      require_once($include_filename);
    }
    if (function_exists($dispatch_function)==true)
    {
      call_user_func($dispatch_function,$arg);
    }
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
  system("clear");
  $input=readline("All existing forms will be deleted. Are you sure you want to continue? (type 'yes' to continue, press Enter or type anything else to cancel): ");
  if ($input<>"yes")
  {
    \webdb\test\utils\test_info_message("aborted by user (no changes made)");
    die;
  }
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

function generate_form($database,$table,$filename,$parent_page_id=false,$parent_primary_key=false)
{
  global $settings;
  $settings["forms"]=array();
  \webdb\forms\load_form_defs();
  $records=\webdb\sql\column_list($database,$table);
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
            $lookup_table_columns=\webdb\sql\column_list($lookup_def["database"],$lookup_def["table"]);
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
    if ((in_array($field_name,$form_data["primary_key"])==true) and (count($form_data["primary_key"])==1))
    {
      $form_data["captions"][$field_name]=\webdb\cli\captionize($field_name,false);
    }
    else
    {
      $form_data["captions"][$field_name]=\webdb\cli\captionize($field_name);
    }
    $form_data["visible"][$field_name]=true;
  }
  if ((count($form_data["primary_key"])>1) and ($parent_page_id===false))
  {
    return;
  }
  $edit_cmd_id="";
  $edit_cmd_page_id=$page_id;
  $noun=\webdb\cli\captionize(\webdb\utils\make_singular($table));
  if ($parent_page_id!==false)
  {
    $primary_key=$form_data["primary_key"];
    $n=count($primary_key);
    for ($i=0;$i<$n;$i++)
    {
      if ($primary_key[$i]==$parent_primary_key)
      {
        unset($primary_key[$i]);
      }
    }
    $primary_key=array_values($primary_key);
    $edit_cmd_id=array_shift($primary_key);
    $noun=\webdb\utils\trim_suffix($edit_cmd_id,"_id");
    $edit_cmd_page_id=\webdb\utils\make_plural($noun);
    $noun=\webdb\cli\captionize($noun);
  }
  else
  {
    $edit_cmd_id=$form_data["primary_key"][0];
  }
  $form_data["title"]=\webdb\cli\captionize($table);
  $title_parts=explode("_",$page_id);
  if ($title_parts[0]=="subform")
  {
    $form_data["title"]=\webdb\cli\captionize(\webdb\utils\make_plural($noun));
  }
  $form_data["primary_key"]=implode(",",$form_data["primary_key"]);
  $form_data["edit_cmd_id"]=$edit_cmd_id;
  $form_data["edit_cmd_page_id"]=$edit_cmd_page_id;
  $form_data["command_caption_noun"]=$noun;
  $form_data["edit_button_caption"]="Edit ".$noun;
  $foreign_key_defs=\webdb\sql\get_foreign_key_defs($database,$table);
  $subform_lists=array();
  for ($i=0;$i<count($foreign_key_defs);$i++)
  {
    $fk=$foreign_key_defs[$i];
    if ($fk["REFERENCED_TABLE_NAME"]==$fk["TABLE_NAME"])
    {
      continue;
    }
    $subform_key=$fk["REFERENCED_COLUMN_NAME"];
    $subform_page_id="subform_".\webdb\utils\make_singular($table)."_";
    if ($subform_key==$fk["COLUMN_NAME"])
    {
      $subform_page_id.=\webdb\cli\link_page_id($fk["TABLE_NAME"],$table);
    }
    else
    {
      $subform_page_id.=\webdb\utils\trim_suffix($fk["COLUMN_NAME"],"_id");
    }
    $subform_page_id=\webdb\utils\make_plural($subform_page_id);
    $form_data["edit_subforms"][$subform_page_id]=$subform_key;
    $subform_list=array();
    $subform_list["database"]=$fk["TABLE_SCHEMA"];
    $subform_list["table"]=$fk["TABLE_NAME"];
    $subform_list["filename"]=$fk["REFERENCED_TABLE_NAME"].DIRECTORY_SEPARATOR.$subform_page_id.".".$form_type;
    $subform_list["parent_page_id"]=$form_data["page_id"];
    $subform_list["parent_primary_key"]=$form_data["primary_key"];
    $subform_lists[]=$subform_list;
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
  \webdb\cli\term_echo("generated form: ".$full_filename);
  if ($newname<>"")
  {
    if (file_exists($newname)==true)
    {
      unlink($newname);
      \webdb\cli\term_echo("[ALERT] renamed file '".$newname."' deleted",34);
    }
  }
  for ($i=0;$i<count($subform_lists);$i++)
  {
    $subform_list=$subform_lists[$i];
    if (file_exists($settings["app_forms_path"].$subform_list["filename"])==false)
    {
      \webdb\cli\generate_form($subform_list["database"],$subform_list["table"],$subform_list["filename"],$subform_list["parent_page_id"],$subform_list["parent_primary_key"]);
    }
  }
}

#####################################################################################################

function link_page_id($link_table,$parent_table)
{
  $parent_singular=\webdb\utils\make_singular($parent_table);
  $parent_plural=\webdb\utils\make_plural($parent_singular);
  $link_page_id=\webdb\utils\webdb_str_replace($parent_plural,"",$link_table);
  $link_page_id=\webdb\utils\webdb_str_replace($parent_singular,"",$link_page_id);
  $link_page_id=\webdb\utils\webdb_str_replace("_links","",$link_page_id);
  $link_page_id=\webdb\utils\trim_suffix($link_page_id,"_");
  if (substr($link_page_id,0,1)=="_")
  {
    $link_page_id=substr($link_page_id,1);
  }
  return \webdb\utils\make_singular($link_page_id);
}

#####################################################################################################

function captionize($field_name,$trim_id=true)
{
  $uc_words=array("id","sql");
  if ($trim_id==true)
  {
    $field_name=trim(\webdb\utils\trim_suffix($field_name,"_id"));
  }
  if ($field_name=="")
  {
    return "";
  }
  $parts=explode("_",$field_name);
  for ($i=0;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    if ($part=="")
    {
      unset($parts[$i]);
      continue;
    }
    if (in_array($part,$uc_words)==true)
    {
      $part=strtoupper($part);
    }
    else
    {
      $part[0]=strtoupper($part[0]);
    }
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

function table_foreign_keys($database,$table)
{
  $sql_params=array();
  $sql_params["database"]=$database;
  $sql_params["table"]=$table;
  return \webdb\sql\file_fetch_prepare("generate_form_foreign_keys",$sql_params,$table,$database);
}

#####################################################################################################

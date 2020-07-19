<?php

namespace webdb\sql;

#####################################################################################################

function check_post_params($sql)
{
  global $settings;
  if (\webdb\cli\is_cli_mode()==true)
  {
    return;
  }
  if ($settings["sql_check_post_params_override"]==true)
  {
    $settings["sql_check_post_params_override"]=false;
    return;
  }
  if (count($_POST)>0)
  {
    return;
  }
  if ($sql<>"")
  {
    $query_type=\webdb\sql\get_statement_type($sql);
    switch ($query_type)
    {
      case "INSERT":
      case "UPDATE":
      case "DELETE":
        \webdb\utils\error_message("error: only POST requests are permitted to modify the database: ".$sql);
    }
  }
}

#####################################################################################################

function query_error($sql,$source="",$filename="",$params=array(),$form_config=false)
{
  global $settings;
  $db_engine=$settings["db_engine"];
  $error_code="";
  $source_error="";
  if ($source!=="")
  {
    $err=$source->errorInfo();
    if ($err[0]<>null)
    {
      $error_code=$err[1];
      $source_error=$err[2];
      if ($form_config!==false)
      {
        if (isset($form_config[$db_engine."_errors"])==true)
        {
          foreach ($form_config[$db_engine."_errors"] as $key => $value)
          {
            if (\webdb\utils\wildcard_compare($source_error,$key)==true)
            {
              $custom_templates=array("sql_error"=>$value);
              $message=\webdb\utils\template_fill("sql_error",$params,array(),$custom_templates);
              \webdb\utils\info_message($message);
            }
          }
        }
      }
    }
  }
  if (\webdb\cli\is_cli_mode()==true)
  {
    $source_error=str_replace(PHP_EOL," ",$source_error);
    $sql=str_replace(PHP_EOL," ",$sql);
    \webdb\cli\term_echo("SQL ERROR",31);
    \webdb\cli\term_echo("filename: ".$filename,31);
    \webdb\cli\term_echo("error: ".$source_error,31);
    \webdb\cli\term_echo("sql: ".$sql,31);
    var_dump($params);
    die;
  }
  else
  {
    if ($error_code!=="")
    {
      # refer to https://www.fromdual.com/mysql-error-codes-and-messages
      switch ($error_code)
      {
        case 1048: # field cannot be null
          #\webdb\utils\info_message($source_error);
          break;
        case 1062: # duplicate key
          #\webdb\utils\info_message($source_error);
          break;
      }
    }
    $msg_params=array();
    $msg_params["driver_code"]=$error_code;
    $msg_params["filename"]=$filename;
    $msg_params["source_error"]=htmlspecialchars($source_error);
    $msg_params["sql"]=htmlspecialchars($sql);
    $msg_params["params"]=json_encode($params,JSON_PRETTY_PRINT);
    \webdb\utils\error_message(\webdb\utils\template_fill("sql_error",$msg_params));
  }
}

#####################################################################################################

function get_pdo_object($is_admin)
{
  global $settings;
  if ($is_admin==true)
  {
    return $settings["pdo_admin"];
  }
  else
  {
    return $settings["pdo_user"];
  }
}

#####################################################################################################

function get_statement_type($sql)
{
  $sql_parts=explode(" ",trim($sql));
  return strtoupper(array_shift($sql_parts));
}

#####################################################################################################

function sql_last_insert_autoinc_id($is_admin=false)
{
  $pdo=\webdb\sql\get_pdo_object($is_admin);
  return $pdo->lastInsertId();
}

#####################################################################################################

function sql_insert($items,$table,$database,$is_admin=false,$form_config=false)
{
  global $settings;
  $fieldnames=array_keys($items);
  $placeholders=array_map("\webdb\sql\callback_prepare",$fieldnames);
  $fieldnames=array_map("\webdb\sql\callback_quote",$fieldnames);
  $sql="INSERT INTO `".$database."`.`".$table."` (".implode(",",$fieldnames).") VALUES (".implode(",",$placeholders).")";
  if ($settings["db_engine"]=="sqlsrv")
  {
    $sql="INSERT INTO ".$database.".[".$table."] (".implode(",",$fieldnames).") VALUES (".implode(",",$placeholders).")";
  }
  $settings["sql_database_change"]=true;
  $result=\webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database,$form_config);
  if ($result===true)
  {
    \webdb\sql\sql_change(array(),$sql,array(),$items,$table,$database,$is_admin);
  }
  return $result;
}

#####################################################################################################

function sql_delete($items,$table,$database,$is_admin=false,$form_config=false)
{
  global $settings;
  $where_clause=\webdb\sql\build_prepared_where($items);
  $sql="DELETE FROM `".$database."`.`".$table."` WHERE (".$where_clause.")";
  if ($settings["db_engine"]=="sqlsrv")
  {
    $sql="DELETE FROM ".$database.".[".$table."] WHERE (".$where_clause.")";
  }
  $old_records=\webdb\sql\get_exist_records($database,$table,$items,$is_admin);
  $settings["sql_database_change"]=true;
  $result=\webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database,$form_config);
  if ($result===true)
  {
    \webdb\sql\sql_change($old_records,$sql,$items,array(),$table,$database,$is_admin);
  }
  return $result;
}

#####################################################################################################

function get_exist_records($database,$table,$where_items,$is_admin)
{
  $sql_params=array();
  $sql_params["database"]=$database;
  $sql_params["table"]=$table;
  $sql_params["where_items"]=\webdb\sql\build_prepared_where($where_items);
  $sql=\webdb\utils\sql_fill("sql_change_old_records",$sql_params);
  return \webdb\sql\fetch_prepare($sql,$where_items,"",$is_admin,$table,$database);
}

#####################################################################################################

function sql_update($value_items,$where_items,$table,$database,$is_admin=false,$form_config=false)
{
  global $settings;
  $value_suffix="_value";
  $value_fieldnames=array_keys($value_items);
  $value_placeholder_names=array();
  for ($i=0;$i<count($value_fieldnames);$i++)
  {
    $value_placeholder_names[]=$value_fieldnames[$i].$value_suffix;
  }
  $value_placeholders=array_map("\webdb\sql\callback_prepare",$value_placeholder_names);
  $value_fieldnames=array_map("\webdb\sql\callback_quote",$value_fieldnames);
  $values_array=array();
  for ($i=0;$i<count($value_items);$i++)
  {
    $values_array[]=$value_fieldnames[$i]."=".$value_placeholders[$i];
  }
  $values_string=implode(",",$values_array);
  $update_value_items=array();
  foreach ($value_items as $field_name => $field_value)
  {
    $update_value_items[$field_name.$value_suffix]=$field_value;
  }
  $items=array_merge($update_value_items,$where_items);
  $where_clause=\webdb\sql\build_prepared_where($where_items);
  $sql="UPDATE `".$database."`.`".$table."` SET ".$values_string." WHERE (".$where_clause.")";
  if ($settings["db_engine"]=="sqlsrv")
  {
    $sql="UPDATE ".$database.".[".$table."] SET ".$values_string." WHERE (".$where_clause.")";
  }
  $old_records=\webdb\sql\get_exist_records($database,$table,$where_items,$is_admin);
  $settings["sql_database_change"]=true;
  $result=\webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database,$form_config);
  if ($result===true)
  {
    \webdb\sql\sql_change($old_records,$sql,$where_items,$value_items,$table,$database,$is_admin);
  }
  return $result;
}

#####################################################################################################

function get_foreign_key_defs($database,$table)
{
  global $settings;
  $sql_params=array();
  if ($settings["db_engine"]<>"sqlsrv")
  {
    $database=\webdb\utils\string_template_fill($database);
    $sql_params["database"]=$database;
  }
  $sql_params["table"]=$table;
  $records=\webdb\sql\file_fetch_prepare("foreign_keys",$sql_params,$table,$database);
  if ($settings["db_engine"]=="sqlsrv")
  {
    for ($i=0;$i<count($records);$i++)
    {
      $records[$i]["TABLE_SCHEMA"]=$settings["database_app"];
      $records[$i]["REFERENCED_TABLE_SCHEMA"]=$settings["database_app"];
    }
  }
  return $records;
}

#####################################################################################################

function foreign_key_used($database,$table,$record,$foreign_key_defs=false)
{
  if ($foreign_key_defs===false)
  {
    $foreign_key_defs=\webdb\sql\get_foreign_key_defs($database,$table);
  }
  $foreign_keys=array();
  for ($i=0;$i<count($foreign_key_defs);$i++)
  {
    $fk=$foreign_key_defs[$i];
    $sql=\webdb\utils\sql_fill("foreign_key_check",$fk);
    $sql_params=array();
    $sql_params["referenced_column_value"]=$record[$fk["REFERENCED_COLUMN_NAME"]];
    $def_foreign_keys=\webdb\sql\fetch_prepare($sql,$sql_params,$table,$database);
    if (count($def_foreign_keys)>0)
    {
      $foreign_key=array();
      $foreign_key["def"]=$fk;
      $foreign_key["dat"]=$def_foreign_keys;
      $foreign_keys[]=$foreign_key;
    }
  }
  if (count($foreign_keys)>0)
  {
    return $foreign_keys;
  }
  return false;
}

#####################################################################################################

function current_sql_timestamp()
{
  return gmdate("Y-m-d H:i:s");
}

#####################################################################################################

function callback_quote($field)
{
  global $settings;
  if ($settings["db_engine"]=="sqlsrv")
  {
    return "[$field]";
  }
  return "`$field`";
}

#####################################################################################################

function callback_prepare($field)
{
  return ":$field";
}

#####################################################################################################

function build_prepared_where($items,$operator="=")
{
  $fieldnames=array_keys($items);
  $placeholders=array_map("\webdb\sql\callback_prepare",$fieldnames);
  $fieldnames=array_map("\webdb\sql\callback_quote",$fieldnames);
  $result=array();
  for ($i=0;$i<count($items);$i++)
  {
    $result[]="(".$fieldnames[$i].$operator.$placeholders[$i].")";
  }
  return implode(" AND ",$result);
}

#####################################################################################################

function get_sql_file($filename)
{
  global $settings;
  if (isset($settings["sql"][$filename])==false)
  {
    \webdb\utils\error_message("error: sql file not found: ".$filename);
  }
  return $settings["sql"][$filename];
}

#####################################################################################################

function execute_return($sql,$params=array(),$filename="",$is_admin=false,$table="",$database="",$form_config=false)
{
  global $settings;
  $pdo=\webdb\sql\get_pdo_object($is_admin);
  $sql=\webdb\utils\string_template_fill($sql);
  $statement=$pdo->prepare($sql);
  if ($statement===false)
  {
    \webdb\sql\sql_log("PREPARE ERROR",$sql,$params,$table,$database);
    \webdb\sql\query_error($sql,"",$filename,$params,$form_config);
  }
  foreach ($params as $key => $value)
  {
    if ($value===null)
    {
      $tmp=null;
      if ($statement->bindValue(":$key",$tmp,\PDO::PARAM_INT)==false)
      {
        \webdb\sql\sql_log("BIND NULL VALUE ERROR",$sql,$params,$table,$database);
        \webdb\sql\query_error($sql,$statement,$filename,$params,$form_config);
      }
    }
    elseif (ctype_digit(strval($value))==true)
    {
      if ($statement->bindParam(":$key",$params[$key],\PDO::PARAM_INT)==false)
      {
        \webdb\sql\sql_log("BIND INT PARAM ERROR",$sql,$params,$table,$database);
        \webdb\sql\query_error($sql,$statement,$filename,$params,$form_config);
      }
    }
    else
    {
      if ($statement->bindParam(":$key",$params[$key],\PDO::PARAM_STR)==false)
      {
        \webdb\sql\sql_log("BIND STR PARAM ERROR",$sql,$params,$table,$database);
        \webdb\sql\query_error($sql,$statement,$filename,$params,$form_config);
      }
    }
  }
  if ($statement->execute()===false)
  {
    \webdb\sql\sql_log("EXECUTE ERROR",$sql,$params,$table,$database);
    \webdb\sql\query_error($sql,$statement,$filename,$params,$form_config);
  }
  \webdb\sql\sql_log("SUCCESS",$sql,$params,$table,$database);
  return $statement;
}

#####################################################################################################

function file_execute_prepare($filename,$params=array(),$is_admin=false,$table="",$database="",$form_config=false)
{
  $sql=\webdb\sql\get_sql_file($filename);
  return \webdb\sql\execute_prepare($sql,$params,$filename,$is_admin,$table,$database,$form_config);
}

#####################################################################################################

function execute_prepare($sql,$params=array(),$filename="",$is_admin=false,$table="",$database="",$form_config=false)
{
  global $settings;
  $query_type=\webdb\sql\get_statement_type($sql);
  switch ($query_type)
  {
    case "INSERT":
    case "UPDATE":
    case "DELETE":
      if ($settings["sql_database_change"]==false)
      {
        \webdb\utils\error_message("error executing sql query that changes the database (change flag not set): ".htmlspecialchars($sql));
      }
  }
  $settings["sql_database_change"]=false;
  \webdb\sql\null_user_check($sql,$params,$table,$database);
  \webdb\sql\check_post_params($sql);
  $statement=\webdb\sql\execute_return($sql,$params,$filename,$is_admin,$table,$database,$form_config);
  return true;
}

#####################################################################################################

function file_fetch_prepare($filename,$params=array(),$is_admin=false,$table="",$database="",$form_config=false)
{
  $sql=\webdb\sql\get_sql_file($filename);
  return \webdb\sql\fetch_prepare($sql,$params,$filename,$is_admin,$table,$database,$form_config);
}

#####################################################################################################

function fetch_prepare($sql,$params=array(),$filename="",$is_admin=false,$table="",$database="",$form_config=false)
{
  global $settings;
  $query_type=\webdb\sql\get_statement_type($sql);
  switch ($query_type)
  {
    case "INSERT":
    case "UPDATE":
    case "DELETE":
      \webdb\utils\error_message("error: changing the database not permitted using webdb\\sql\\fetch_prepare function: ".htmlspecialchars($sql));
  }
  $statement=\webdb\sql\execute_return($sql,$params,$filename,$is_admin,$table,$database,$form_config);
  return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

#####################################################################################################

function fetch_all_records($table,$database,$sort_field="",$sort_dir="",$is_admin=false)
{
  $sql_params=array();
  $sql_params["sort_sql"]="";
  if ($sort_field<>"")
  {
    $sql_params["sort_sql"]=$sort_field;
    if ($sort_dir<>"")
    {
      $sql_params["sort_sql"].=" ".$sort_dir;
    }
    else
    {
      $sql_params["sort_sql"].=" ASC";
    }
  }
  $sort_sql=\webdb\utils\sql_fill("sort_clause",$sql_params);
  $sql_params=array();
  $sql_params["database"]=$database;
  $sql_params["table"]=$table;
  $sql_params["selected_filter_sql"]="";
  $sql_params["sort_sql"]=$sort_sql;
  $sql=\webdb\utils\sql_fill("form_list_fetch_all",$sql_params);
  return \webdb\sql\fetch_prepare($sql,array(),"",$is_admin,$table,$database);
}

#####################################################################################################

function sql_log($status,$sql,$params=array(),$table="",$database="")
{
  global $settings;
  if (\webdb\cli\is_cli_mode()==true)
  {
    return;
  }
  if ($database=="webdb")
  {
    switch ($table)
    {
      case "sql_changes":
      case "auth_log":
        return;
    }
  }
  \webdb\users\obfuscate_hashes($params);
  $username="[unauthenticated]";
  if (isset($settings["user_record"]["username"])==true)
  {
    $username=$settings["user_record"]["username"];
  }
  $sql=str_replace(PHP_EOL," ",$sql);
  $content=date("Y-m-d H:i:s")."\t".$username."\t".$status."\t".$sql."\t".json_encode($params);
  $settings["logs"]["sql"][]=$content;
}

#####################################################################################################

function null_user_check($sql,$where_items,$table,$database)
{
  global $settings;
  $user_id=null;
  if (isset($settings["user_record"])==true)
  {
    $user_id=$settings["user_record"]["user_id"];
  }
  if (\webdb\cli\is_cli_mode()==true)
  {
    return $user_id;
  }
  if (is_null($user_id)==false)
  {
    return $user_id;
  }
  # user_id is null from here on
  if (($database=="webdb") and ($table=="auth_log"))
  {
    return $user_id;
  }
  $error_params=array();
  $error_params["database"]=$database;
  $error_params["table"]=$table;
  $error_params["where_items"]=json_encode($where_items,JSON_PRETTY_PRINT);
  $error_params["sql"]=htmlspecialchars($sql);
  \webdb\utils\error_message(\webdb\utils\template_fill("unauthenticated_change_error",$error_params));
}

#####################################################################################################

function sql_change($old_records,$sql,$where_items,$value_items,$table,$database,$is_admin)
{
  global $settings;
  if (\webdb\cli\is_cli_mode()==true)
  {
    return;
  }
  if ($is_admin==true)
  {
    return;
  }
  if ($database=="webdb")
  {
    switch ($table)
    {
      case "sql_changes":
      case "auth_log":
        return;
    }
  }
  \webdb\users\obfuscate_hashes($value_items);
  for ($i=0;$i<count($old_records);$i++)
  {
    \webdb\users\obfuscate_hashes($old_records[$i]);
  }
  $user_id=\webdb\sql\null_user_check($sql,$where_items,$table,$database);
  $items=array();
  $items["user_id"]=$user_id;
  $tmp_sql=array("temp_sql"=>$sql);
  $sql=\webdb\utils\template_fill("temp_sql",false,array(),$tmp_sql);
  $items["sql_statement"]=$sql;
  $tmp_sql=array("temp_sql"=>$database);
  $database=\webdb\utils\template_fill("temp_sql",false,array(),$tmp_sql);
  $items["change_database"]=$database;
  $items["change_table"]=$table;
  $items["change_type"]=\webdb\sql\get_statement_type($sql);
  $items["where_items"]=json_encode($where_items);
  $items["value_items"]=json_encode($value_items);
  $items["old_records"]=json_encode($old_records);
  if (function_exists($settings["sql_change_event_handler"])==true)
  {
    call_user_func($settings["sql_change_event_handler"],$items);
  }
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($items,"sql_changes",$settings["database_webdb"],true);
}

#####################################################################################################

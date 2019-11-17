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
        \webdb\utils\show_message("only POST requests are permitted to modify the database: ".$sql);
    }
  }
}

#####################################################################################################

function query_error($sql,$source="",$filename="")
{
  $source_error="";
  if ($source!=="")
  {
    $err=$source->errorInfo();
    if ($err[0]<>null)
    {
      $source_error=htmlspecialchars($err[2]);
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
  }
  else
  {
    $msg_params=array();
    $msg_params["filename"]=$filename;
    $msg_params["source_error"]=$source_error;
    $msg_params["sql"]=htmlspecialchars($sql);
    \webdb\utils\show_message(\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."sql_error",$msg_params));
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

function sql_insert($items,$table,$database,$is_admin=false)
{
  $fieldnames=array_keys($items);
  $placeholders=array_map("\webdb\sql\callback_prepare",$fieldnames);
  $fieldnames=array_map("\webdb\sql\callback_quote",$fieldnames);
  $sql="INSERT INTO `".$database."`.`".$table."` (".implode(",",$fieldnames).") VALUES (".implode(",",$placeholders).")";
  \webdb\sql\check_post_params($sql);
  return \webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database);
}

#####################################################################################################

function sql_delete($items,$table,$database,$is_admin=false)
{
  $sql="DELETE FROM `".$database."`.`".$table."` WHERE (".\webdb\sql\build_prepared_where($items).")";
  \webdb\sql\check_post_params($sql);
  return \webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database);
}

#####################################################################################################

function sql_update($value_items,$where_items,$table,$database,$is_admin=false)
{
  $value_fieldnames=array_keys($value_items);
  $value_placeholder_names=array();
  for ($i=0;$i<count($value_fieldnames);$i++)
  {
    $value_placeholder_names[]=$value_fieldnames[$i]."_value";
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
    $update_value_items[$field_name."_value"]=$field_value;
  }
  $items=array_merge($update_value_items,$where_items);
  $sql="UPDATE `$database`.`".$table."` SET ".$values_string." WHERE (".\webdb\sql\build_prepared_where($where_items).")";
  \webdb\sql\check_post_params($sql);
  return \webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database);
}

#####################################################################################################

function get_foreign_key_defs($database,$table)
{
  $sql_params=array();
  $sql_params["database"]=$database;
  $sql_params["table"]=$table;
  return \webdb\sql\file_fetch_prepare("foreign_keys",$sql_params,$table,$database);
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
  return date("Y-m-d H:i:s");
}

#####################################################################################################

function callback_quote($field)
{
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
    \webdb\utils\show_message("error: sql file not found: ".$filename);
  }
  return $settings["sql"][$filename];
}

#####################################################################################################

function file_execute_prepare($filename,$params,$is_admin=false,$table="",$database="")
{
  $sql=\webdb\sql\get_sql_file($filename);
  return \webdb\sql\execute_prepare($sql,$params,$filename,$is_admin,$table,$database);
}

#####################################################################################################

function execute_prepare($sql,$params=array(),$filename="",$is_admin=false,$table="",$database="")
{
  \webdb\sql\check_post_params($sql);
  $pdo=\webdb\sql\get_pdo_object($is_admin);
  $statement=$pdo->prepare($sql);
  if ($statement===false)
  {
    \webdb\sql\sql_log("PREPARE ERROR",$sql,$params,$table,$database);
    return \webdb\sql\query_error($sql,"",$filename);
  }
  foreach ($params as $key => $value)
  {
    if ($value===null)
    {
      $tmp=null;
      $statement->bindValue(":$key",$tmp,\PDO::PARAM_INT);
    }
    elseif (ctype_digit(strval($value))==true)
    {
      $statement->bindParam(":$key",$params[$key],\PDO::PARAM_INT);
    }
    else
    {
      $statement->bindParam(":$key",$params[$key],\PDO::PARAM_STR);
    }
  }
  if ($statement->execute()===false)
  {
    \webdb\sql\sql_log("EXECUTE ERROR",$sql,$params,$table,$database);
    return \webdb\sql\query_error($sql,$statement,$filename);
  }
  \webdb\sql\sql_log("SUCCESS",$sql,$params,$table,$database);
  return true;
}

#####################################################################################################

function file_fetch_prepare($filename,$params=array(),$is_admin=false,$table="",$database="")
{
  $sql=\webdb\sql\get_sql_file($filename);
  return \webdb\sql\fetch_prepare($sql,$params,$filename,$is_admin,$table,$database);
}

#####################################################################################################

function fetch_prepare($sql,$params=array(),$filename="",$is_admin=false,$table="",$database="")
{
  $pdo=\webdb\sql\get_pdo_object($is_admin);
  $statement=$pdo->prepare($sql);
  if ($statement===false)
  {
    \webdb\sql\sql_log("PREPARE ERROR",$sql,$params,$table,$database);
    return \webdb\sql\query_error($sql,"",$filename);
  }
  foreach ($params as $key => $value)
  {
    if (ctype_digit(strval($value))==true)
    {
      $err=$statement->bindParam(":$key",$params[$key],\PDO::PARAM_INT);
    }
    else
    {
      $err=$statement->bindParam(":$key",$params[$key],\PDO::PARAM_STR);
    }
    if ($err==false)
    {
      \webdb\sql\sql_log("BIND ERROR",$sql,$params,$table,$database);
      return \webdb\sql\query_error($sql,$statement,$filename);
    }
  }
  if ($statement->execute()===false)
  {
    \webdb\sql\sql_log("EXECUTE ERROR",$sql,$params,$table,$database);
    return \webdb\sql\query_error($sql,$statement,$filename);
  }
  \webdb\sql\sql_log("SUCCESS",$sql,$params,$table,$database);
  return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

#####################################################################################################

function fetch_all_records($table,$database,$sort_field="",$sort_dir="",$is_admin=false)
{
  $sql="SELECT * FROM ".$database.".".$table;
  if ($sort_field<>"")
  {
    $sql.=" ORDER BY ".$sort_field;
    if ($sort_dir<>"")
    {
      $sql.=" ".$sort_dir;
    }
    else
    {
      $sql.=" ASC";
    }
  }
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
      case "sql_log":
      case "sql_changes":
      case "auth_log":
        return;
    }
  }
  \webdb\users\obfuscate_hashes($params);
  $user_id=null;
  if (isset($settings["user_record"])==true)
  {
    $user_id=$settings["user_record"]["user_id"];
  }
  $items=array();
  $items["user_id"]=$user_id;
  $items["sql_statement"]=$sql;
  $items["sql_params"]=json_encode($params);
  $items["sql_status"]=$status;
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($items,"sql_log","webdb",true);
  $settings["sql_check_post_params_override"]=false;
  return \webdb\sql\sql_last_insert_autoinc_id(true);
}

#####################################################################################################

/*function sql_change($status,$sql,$params=array(),$table="",$database="")
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
      case "sql_log":
      case "sql_changes":
      case "auth_log":
        return;
    }
  }
  \webdb\users\obfuscate_hashes($params);
  $user_id=null;
  if ($user_record===false)
  {
    if (isset($settings["user_record"])==true)
    {
      $user_id=$settings["user_record"]["user_id"];
    }
  }
  else
  {
    $user_id=$user_record["user_id"];
  }
  $items=array();
  $items["user_id"]=$user_id;
  $items["sql_statement"]=$sql;
  $items["sql_params"]=json_encode($params);
  $items["sql_status"]=$status;
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($items,"sql_log","webdb",true);
  $settings["sql_check_post_params_override"]=false;
  $sql_parts=explode(" ",$sql);
  $query_type=strtoupper(array_shift($sql_parts));
  switch ($query_type)
  {
    "INSERT":
    "UPDATE":
    "DELETE":
      break;
    default:
      return;
  }
  $sql_log_id=\webdb\sql\get_statement_type($sql);
  $items=array();
  $items["user_id"]=$user_id;
  $items["sql_log_id"]=$sql_log_id;
  $items["change_database"]=$database;
  $items["change_table"]=$table;
  $items["change_type"]=$query_type;

  `key_field` VARCHAR(255) NOT NULL,
  `key_id` INT UNSIGNED NOT NULL,
  `old_record_json` LONGTEXT NOT NULL,
  `new_record_json` LONGTEXT NOT NULL,

  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($items,"sql_changes","webdb",true);
  $settings["sql_check_post_params_override"]=false;
}*/

#####################################################################################################

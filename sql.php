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
  global $settings;
  $fieldnames=array_keys($items);
  $placeholders=array_map("\webdb\sql\callback_prepare",$fieldnames);
  $fieldnames=array_map("\webdb\sql\callback_quote",$fieldnames);
  $sql="INSERT INTO `".$database."`.`".$table."` (".implode(",",$fieldnames).") VALUES (".implode(",",$placeholders).")";
  $settings["sql_database_change"]=true;
  $result=\webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database);
  if ($result===true)
  {
    \webdb\sql\sql_change(array(),$sql,array(),$items,$table,$database,$is_admin);
  }
  return $result;
}

#####################################################################################################

function sql_delete($items,$table,$database,$is_admin=false)
{
  global $settings;
  $sql="DELETE FROM `".$database."`.`".$table."` WHERE (".\webdb\sql\build_prepared_where($items).")";
  $old_records=\webdb\sql\get_exist_records($database,$table,$items,$is_admin);
  $settings["sql_database_change"]=true;
  $result=\webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database);
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

function sql_update($value_items,$where_items,$table,$database,$is_admin=false)
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
  $sql="UPDATE `$database`.`".$table."` SET ".$values_string." WHERE (".$where_clause.")";
  $old_records=\webdb\sql\get_exist_records($database,$table,$where_items,$is_admin);
  $settings["sql_database_change"]=true;
  $result=\webdb\sql\execute_prepare($sql,$items,"",$is_admin,$table,$database);
  if ($result===true)
  {
    \webdb\sql\sql_change($old_records,$sql,$where_items,$value_items,$table,$database,$is_admin);
  }
  return $result;
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
  global $settings;
  $query_type=\webdb\sql\get_statement_type($sql);
  switch ($query_type)
  {
    case "INSERT":
    case "UPDATE":
    case "DELETE":
      if ($settings["sql_database_change"]==false)
      {
        \webdb\utils\show_message("error executing sql query that changes the database (change flag not set): ".htmlspecialchars($sql));
      }
  }
  $settings["sql_database_change"]=false;
  $user_id=null;
  if (isset($settings["user_record"])==true)
  {
    $user_id=$settings["user_record"]["user_id"];
  }
  if ($user_id==null)
  {
    \webdb\sql\null_user_check($sql,$params,$table,$database);
  }
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
  global $settings;
  $query_type=\webdb\sql\get_statement_type($sql);
  switch ($query_type)
  {
    case "INSERT":
    case "UPDATE":
    case "DELETE":
      \webdb\utils\show_message("changing the database not permitted using webdb\\sql\\fetch_prepare function: ".htmlspecialchars($sql));
  }
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
  $log_filename=$settings["sql_log_path"]."sql_".date("Ymd").".log";
  $sql=str_replace(PHP_EOL," ",$sql);
  $content=date("Y-m-d H:i:s")."\t".$username."\t".$status."\t".$sql."\t".json_encode($params).PHP_EOL;
  file_put_contents($log_filename,$content,FILE_APPEND);
}

#####################################################################################################

function null_user_check($sql,$where_items,$table,$database)
{
  if (\webdb\cli\is_cli_mode()==true)
  {
    return true;
  }
  if ($database=="webdb")
  {
    switch ($table)
    {
      case "auth_log":
        return true;
    }
    if (isset($where_items["user_id"])==true)
    {
      switch ($table)
      {
        case "users":
          return true;
      }
    }
  }
  $error_params=array();
  $error_params["database"]=$database;
  $error_params["table"]=$table;
  $error_params["where_items"]=json_encode($where_items,JSON_PRETTY_PRINT);
  $error_params["sql"]=htmlspecialchars($sql);
  \webdb\utils\show_message(\webdb\utils\template_fill("unauthenticated_change_error",$error_params));
}

#####################################################################################################

function sql_change($old_records,$sql,$where_items,$value_items,$table,$database,$is_admin)
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
  $user_id=null;
  if (isset($settings["user_record"])==true)
  {
    $user_id=$settings["user_record"]["user_id"];
  }
  if ($user_id==null)
  {
    if (\webdb\sql\null_user_check($sql,$where_items,$table,$database)==true)
    {
      return;
    }
  }
  $items=array();
  $items["user_id"]=$user_id;
  $items["sql_statement"]=$sql;
  $items["change_database"]=$database;
  $items["change_table"]=$table;
  $items["change_type"]=\webdb\sql\get_statement_type($sql);
  $items["where_items"]=json_encode($where_items);
  $items["value_items"]=json_encode($value_items);
  $items["old_records"]=json_encode($old_records);
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($items,"sql_changes","webdb",true);
}

#####################################################################################################

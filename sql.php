<?php

namespace webdb\sql;

#####################################################################################################

function query_error($sql,$source="",$filename="")
{
  $msg_params=array();
  $msg_params["filename"]=$filename;
  $msg_params["source_error"]="";
  $msg_params["sql"]=$sql;
  if ($source!=="")
  {
    $err=$source->errorInfo();
    if ($err[0]<>null)
    {
      $msg_params["source_error"]=$err[2];
    }
  }
  \webdb\utils\show_message(\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."sql_error",$msg_params));
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

function sql_last_insert_autoinc_id($is_admin=false)
{
  $pdo=\webdb\sql\get_pdo_object($is_admin);
  return $pdo->lastInsertId();
}

#####################################################################################################

function sql_insert($items,$table,$schema,$is_admin=false)
{
  $fieldnames=array_keys($items);
  $placeholders=array_map("\webdb\sql\callback_prepare",$fieldnames);
  $fieldnames=array_map("\webdb\sql\callback_quote",$fieldnames);
  $sql="INSERT INTO `".$schema."`.`".$table."` (".implode(",",$fieldnames).") VALUES (".implode(",",$placeholders).")";
  return \webdb\sql\execute_prepare($sql,$items,"",$is_admin);
}

#####################################################################################################

function sql_delete($items,$table,$schema,$is_admin=false)
{
  $sql="DELETE FROM `".$schema."`.`".$table."` WHERE (".\webdb\sql\build_prepared_where($items).")";
  return \webdb\sql\execute_prepare($sql,$items,"",$is_admin);
}

#####################################################################################################

function sql_update($value_items,$where_items,$table,$schema,$is_admin=false)
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
  $sql="UPDATE `$schema`.`".$table."` SET ".$values_string." WHERE (".\webdb\sql\build_prepared_where($where_items).")";
  return \webdb\sql\execute_prepare($sql,$items,"",$is_admin);
}

#####################################################################################################

function get_foreign_key_defs($database,$table)
{
  $sql_params=array();
  $sql_params["database"]=$database;
  $sql_params["table"]=$table;
  return \webdb\sql\file_fetch_prepare("foreign_keys",$sql_params);
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
    $def_foreign_keys=\webdb\sql\fetch_prepare($sql,$sql_params);
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

function zero_sql_timestamp()
{
  return "0000-00-00 00:00:00";
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

function file_fetch_query($filename,$is_admin=false)
{
  $sql=\webdb\sql\get_sql_file($filename);
  return \webdb\sql\fetch_query($sql,$filename,$is_admin);
}

#####################################################################################################

function fetch_query($sql,$filename="",$is_admin=false)
{
  $pdo=\webdb\sql\get_pdo_object($is_admin);
  $statement=$pdo->query($sql);
  if ($statement===false)
  {
    \webdb\sql\sql_log("ERROR",$sql);
    \webdb\sql\query_error($sql,$pdo,$filename);
  }
  \webdb\sql\sql_log("SUCCESS",$sql);
  return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

#####################################################################################################

function file_execute_prepare($filename,$params,$is_admin=false)
{
  $sql=\webdb\sql\get_sql_file($filename);
  return \webdb\sql\execute_prepare($sql,$params,$filename,$is_admin);
}

#####################################################################################################

function execute_prepare($sql,$params=array(),$filename="",$is_admin=false)
{
  $pdo=\webdb\sql\get_pdo_object($is_admin);
  $statement=$pdo->prepare($sql);
  if ($statement===false)
  {
    \webdb\sql\sql_log("PREPARE ERROR",$sql,$params);
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
    \webdb\sql\sql_log("EXECUTE ERROR",$sql,$params);
    return \webdb\sql\query_error($sql,$statement,$filename);
  }
  \webdb\sql\sql_log("SUCCESS",$sql,$params);
  return true;
}

#####################################################################################################

function file_fetch_prepare($filename,$params=array(),$is_admin=false)
{
  $sql=\webdb\sql\get_sql_file($filename);
  return \webdb\sql\fetch_prepare($sql,$params,$filename,$is_admin);
}

#####################################################################################################

function fetch_prepare($sql,$params=array(),$filename="",$is_admin=false)
{
  $pdo=\webdb\sql\get_pdo_object($is_admin);
  $statement=$pdo->prepare($sql);
  if ($statement===false)
  {
    \webdb\sql\sql_log("PREPARE ERROR",$sql,$params);
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
      \webdb\sql\sql_log("BIND ERROR",$sql,$params);
      return \webdb\sql\query_error($sql,$statement,$filename);
    }
  }
  if ($statement->execute()===false)
  {
    \webdb\sql\sql_log("EXECUTE ERROR",$sql,$params);
    return \webdb\sql\query_error($sql,$statement,$filename);
  }
  \webdb\sql\sql_log("SUCCESS",$sql,$params);
  return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

#####################################################################################################

function fetch_all_records($table,$sort_field="",$sort_dir="",$schema,$is_admin=false)
{
  $sql="SELECT * FROM ".$schema.".".$table;
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
  return \webdb\sql\fetch_query($sql,"",$is_admin);
}

#####################################################################################################

function sql_log($result,$sql,$params=array())
{
  global $settings;
  if (\webdb\cli\is_cli_mode()==true)
  {
    return;
  }
  $username="(no username)";
  if (isset($settings["user_record"]["username"])==true)
  {
    $username=$settings["user_record"]["username"];
  }
  $log_filename=$settings["sql_log_path"]."sql_".date("Ymd").".log";
  \webdb\users\obfuscate_hashes($params);
  $sql=str_replace(PHP_EOL," ",$sql);
  $content=date("Y-m-d H:i:s")."\t".$username."\t".$result."\t".$sql."\t".serialize($params).PHP_EOL;
  file_put_contents($log_filename,$content,FILE_APPEND);
}

#####################################################################################################

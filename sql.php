<?php

namespace webdb\sql;

#####################################################################################################

function query_error($sql,$source="",$filename="")
{
  $params=array();
  $params["filename"]=$filename;
  $params["source_error"]="";
  $params["sql"]=$sql;
  if ($source!=="")
  {
    $err=$source->errorInfo();
    if ($err[0]<>null)
    {
      $params["source_error"]=$err[2];
    }
  }
  \webdb\utils\show_message(\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."sql_error",$params));
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
    \webdb\sql\query_error($sql,$pdo,$filename);
  }
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
    return \webdb\sql\query_error($sql,$statement,$filename);
  }
  else
  {
    return true;
  }
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
      return \webdb\sql\query_error($sql,$statement,$filename);
    }
  }
  if ($statement->execute()===false)
  {
    return \webdb\sql\query_error($sql,$statement,$filename);
  }
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

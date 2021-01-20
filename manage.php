<?php

namespace webdb\manage;

#####################################################################################################

function forms_list($event_params)
{
  global $settings;
  $records=array();
  foreach ($settings["forms"] as $page_id => $form_config)
  {
    $record=array();
    $record["page_id"]=$page_id;
    $records[]=$record;
  }
  $event_params["records"]=$records;
  return $event_params;
}

#####################################################################################################

function forms_insert($form_config,$event_params,$event_name)
{
  global $settings;
  return $event_params;
}

#####################################################################################################

function forms_edit($form_config,$event_params,$event_name)
{
  global $settings;
  return $event_params;
}

#####################################################################################################

function forms_get_record($form_config,$event_params,$event_name)
{
  global $settings;
  $event_params["handled"]=true;
  $record_id=$event_params["record_id"];
  $config=\webdb\forms\get_form_config($record_id,true);
  unset($config["basename"]);
  unset($config["filename"]);
  unset($config["sql_errors"]);
  $record=array();
  $record["page_id"]=$record_id;
  $record["json"]=json_encode($config,JSON_PRETTY_PRINT);
  $event_params["record"]=$record;
  return $event_params;
}

#####################################################################################################

function forms_update_record($form_config,$event_params,$event_name)
{
  global $settings;
  return $event_params;
}

#####################################################################################################

function forms_insert_record($form_config,$event_params,$event_name)
{
  global $settings;
  return $event_params;
}

#####################################################################################################

function forms_above($form_config,$event_params,$event_name)
{
  global $settings;
  return $event_params;
}

#####################################################################################################

function forms_below($form_config,$event_params,$event_name)
{
  global $settings;
  return $event_params;
}

#####################################################################################################

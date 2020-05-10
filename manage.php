<?php

namespace webdb\manage;

#####################################################################################################

function forms_list($event_params)
{
  global $settings;

  $event_params["custom_list_content"]=false;
  #$event_params["content"]="";

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

<?php

namespace test_app\stubs;

#####################################################################################################

function output_item_filter_select($form_config,$form_params)
{
  $filter_select_template="item_filter_select";
  $blank_option="show_all_class";
  $active_template="control_filter_active";
  $select_all_template="show_all_selected";
  $deselect_all_template="show_all_deselected";
  return \webdb\stubs\output_filter_select($form_config,$filter_select_template,$blank_option,$active_template,$select_all_template,$deselect_all_template);
}

#####################################################################################################

function item_filter_select_change($form_config,$field_name,$event_type,$event_data)
{
  \webdb\stubs\filter_select_change($form_config);
}

#####################################################################################################

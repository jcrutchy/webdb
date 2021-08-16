<?php

namespace webdb\dbquery;

#####################################################################################################

function dbquery_page_stub($form_config)
{
  global $settings;
  # log separately
  $page_params=array();
  $page_params["database"]=$settings["dbquery_database"];
  $results_head_row="";
  $results_rows="";
  if (isset($_POST["execute_query"])==true)
  {
    $sql=""; # TODO
    $records=\webdb\sql\fetch_prepare($sql);
    for ($i=0;$i<count($records);$i++)
    {
      $record=$records[$i];
      $fields="";
      foreach ($record as $fieldname => $value)
      {
        if ($i==0)
        {
          $head_params=array();
          $head_params["fieldname"]=$fieldname;
          $results_head_row.=\webdb\utils\template_fill("dbquery/result_head_field",$head_params);
        }
        $field_params=array();
        $field_params["value"]=$value;
        $fields.=\webdb\utils\template_fill("dbquery/result_field",$field_params);
      }
      $row_params=array();
      $row_params["fields"]=$fields;
      $row=\webdb\utils\template_fill("dbquery/result_row",$row_params);
      $results_rows.=$row;
    }
    if ($results_head_row<>"")
    {
      $head_params=array();
      $head_params["fields"]=$results_head_row;
      $results_head_row=\webdb\utils\template_fill("dbquery/result_row",$head_params);
    }
  }
  $page_params["results_head_row"]=$results_head_row;
  $page_params["results_rows"]=$results_rows;
  $sql_params=array();
  $sql_params["database"]=$settings["dbquery_database"];
  $sql=\webdb\utils\sql_fill("dbquery/table_list",$sql_params);
  $tables=\webdb\sql\fetch_prepare($sql);
  $options="";
  for ($i=0;$i<count($tables);$i++)
  {
    $table_name=$tables[$i]["TABLE_NAME"];
    $option_params=array();
    $option_params["value"]=$table_name;
    $option_params["caption"]=$table_name;
    $options.=\webdb\utils\template_fill("select_option",$option_params);
  }
  $page_params["from_options"]=$options;
  $content=\webdb\utils\template_fill("dbquery/page",$page_params);
  \webdb\utils\output_page($content,$form_config["title"]);
}

#####################################################################################################

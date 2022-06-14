<?php

namespace webdb\word;

#####################################################################################################

function load_word_doc($filename)
{
  $xml=file_get_contents("zip://".$filename."#word/document.xml");
  $xml=simplexml_load_string($xml,null,0,"w",true);
  $body=$xml->body;
  return $body;
}

#####################################################################################################

function get_para($body)
{
  $para=array();
  foreach ($body[0] as $type => $o)
  {
    $obj=false;
    switch ($type)
    {
      case "p":
        foreach ($o[0] as $key => $t)
        {
          if ($key=="r")
          {
            if (isset($t->t)==true)
            {
              if ($obj===false)
              {
                $obj=array();
                $obj["type"]="p";
                $obj["content"]="";
              }
              $obj["content"].=(string)$t->t;
            }
          }
        }
        break;
      case "tbl":
        $rows=array();
        foreach ($o->tr as $i => $tr)
        {
          $cells=array();
          foreach ($tr->tc as $j => $tc)
          {
            if (isset($tc->p)==true)
            {
              $cells[]=\webdb\word\get_para($tc);
            }
          }
          $rows[]=$cells;
        }
        $obj=array();
        $obj["type"]="tbl";
        $obj["content"]=$rows;
        break;
    }
    if ($obj!==false)
    {
      $para[]=$obj;
    }
  }
  return $para;
}

#####################################################################################################

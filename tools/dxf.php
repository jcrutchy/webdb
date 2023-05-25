<?php

namespace webdb\dxf;

#####################################################################################################

function parse_dxf($data)
{
  $result=array();
  $entity_types=array("LINE","CIRCLE");
  $dxf_lines=\webdb\utils\webdb_explode("\n",$data);
  $line_count=count($dxf_lines);
  $group_code="";
  $entity_data=false;
  for ($i=0;$i<$line_count;$i++)
  {
    $dxf_line=trim($dxf_lines[$i]);
    if ($dxf_line=="")
    {
      continue;
    }
    if (($i%2)==0)
    {
      $group_code=$dxf_line;
      continue;
    }
    if ($group_code=="0")
    {
      if ($entity_data!==false)
      {
        $result[]=$entity_data;
      }
      if (in_array($dxf_line,$entity_types)==true)
      {
        $entity_data=array();
      }
      else
      {
        $entity_data=false;
      }
    }
    if ($entity_data!==false)
    {
      $entity_data[$group_code]=$dxf_line;
    }
  }
  if ($entity_data!==false)
  {
    $result[]=$entity_data;
  }
  return $result;
}

#####################################################################################################

function dxf_image($dxf,$scale=100)
{
  $min_x=PHP_INT_MAX;
  $max_x=0;
  $min_y=PHP_INT_MAX;
  $max_y=0;
  $entity_count=count($dxf);
  for ($i=0;$i<$entity_count;$i++)
  {
    $entity=$dxf[$i];
    foreach ($entity as $group_code => $value)
    {
      switch ($group_code)
      {
        case 10:
        case 11:
          if ($value<$min_x)
          {
            $min_x=$value;
          }
          if ($value>$max_x)
          {
            $max_x=$value;
          }
          break;
        case 20:
        case 21:
          if ($value<$min_y)
          {
            $min_y=$value;
          }
          if ($value>$max_y)
          {
            $max_y=$value;
          }
          break;
      }
    }
  }
  if ($min_x>$max_x)
  {
    $min_x=$max_x;
  }
  if ($min_y>$max_y)
  {
    $min_y=$max_y;
  }
  $w=($max_x-$min_x)*$scale;
  $h=($max_y-$min_y)*$scale;
  $buffer=imagecreatetruecolor($w+1,$h+1);
  $bg_color=imagecolorallocate($buffer,255,0,255); # magenta
  imagecolortransparent($buffer,$bg_color);
  imagefill($buffer,0,0,$bg_color);
  $line_color=imagecolorallocate($buffer,0,0,0); # black
  for ($i=0;$i<$entity_count;$i++)
  {
    $entity=$dxf[$i];
    switch ($entity[0])
    {
      case "LINE":
        $x1=($entity[10]-$min_x)*$scale;
        $y1=$h-($entity[20]-$min_y)*$scale;
        $x2=($entity[11]-$min_x)*$scale;
        $y2=$h-($entity[21]-$min_y)*$scale;
        imageline($buffer,$x1,$y1,$x2,$y2,$line_color);
        break;
      case "CIRCLE":
        $x=($entity[10]-$min_x)*$scale;
        $y=$h-($entity[20]-$min_y)*$scale;
        $r=$entity[40]*$scale;
        imageellipse($buffer,$x,$y,2*$r,2*$r,$line_color);
        break;
    }
  }
  return $buffer;
}

#####################################################################################################

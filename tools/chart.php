<?php

namespace webdb\chart;

#####################################################################################################

function perpendicular_distance($x,$y,$L1x,$L1y,$L2x,$L2y)
{
  # https://www.loughrigg.org/rdp/viewsource.php
  if ($L1x==$L2x)
  {
    return abs($x-$L2x);
  }
  else
  {
    $m=(($L2y-$L1y)/($L2x-$L1x));
    $c=(0-$L1x)*$m+$L1y;
    return (abs($m*$x-$y+$c))/(sqrt($m*$m+1));
  }
}

#####################################################################################################

function chart_colors()
{
  $colors=array();
  $colors["teal"]=array(11,132,165);
  $colors["yellow"]=array(246,200,95);
  $colors["purple"]=array(111,78,124);
  $colors["light_green"]=array(157,216,102);
  $colors["red"]=array(202,71,47);
  $colors["orange"]=array(255,160,86);
  $colors["sky_blue"]=array(141,221,208);
  $colors["magenta"]=array(211,54,130);
  $colors["blue"]=array(38,139,210);
  return $colors;
}

#####################################################################################################

function assign_plot_data($chart_data,$series_data,$x_key,$y_key,$color_key,$marker="",$assign_limits=true)
{
  $series=array();
  $series["color"]=$color_key;
  $series["type"]="plot";
  $series["marker"]=$marker;
  $series["x_values"]=array();
  $series["y_values"]=array();
  $min_x=PHP_INT_MAX;
  $max_x=0;
  $min_y=PHP_INT_MAX;
  $max_y=0;
  $n=count($series_data);
  for ($i=0;$i<$n;$i++)
  {
    $coord=$series_data[$i];
    $x=$coord[$x_key];
    $y=$coord[$y_key];
    $series["x_values"][]=$x;
    $series["y_values"][]=$y;
    if ($x<$min_x)
    {
      $min_x=$x;
    }
    if ($x>$max_x)
    {
      $max_x=$x;
    }
    if ($y<$min_y)
    {
      $min_y=$y;
    }
    if ($y>$max_y)
    {
      $max_y=$y;
    }
  }
  $chart_data["series"][]=$series;
  if ($assign_limits==true)
  {
    if (($min_x<$max_x) and ($min_y<$max_y))
    {
      $chart_data["x_min"]=$min_x;
      $chart_data["x_max"]=$max_x;
      $chart_data["y_min"]=$min_y;
      $chart_data["y_max"]=$max_y;
    }
  }
  return $chart_data;
}

#####################################################################################################

function initilize_chart()
{
  $data=array();
  $data["w"]=1800;
  $data["h"]=800;
  $data["left"]=60;
  $data["right"]=10;
  $data["bottom"]=60;
  $data["top"]=10;
  $data["series"]=array();
  $data["grid_x"]=1;
  $data["grid_y"]=1;
  $data["x_min"]=0;
  $data["x_max"]=10;
  $data["y_min"]=0;
  $data["y_max"]=10;
  $data["x_range_override"]=false;
  $data["y_range_override"]=false;
  $data["x_title"]="";
  $data["y_title"]="";
  return $data;
}

#####################################################################################################

function get_time_captions($scale,&$data)
{
  $min_x=$data["x_min"];
  $max_x=$data["x_max"];
  $x_captions=array();
  switch ($scale)
  {
    case "day":
      # TODO
      break;
    case "month":
      $min_x=strtotime("-1 month",$min_x);
      $min_x=strtotime(date("M-Y",$min_x)."-01");
      $data["x_min"]=$min_x;
      $max_x=strtotime("+2 month",$max_x);
      $max_x=strtotime(date("M-Y",$max_x)."-01");
      $data["x_max"]=$max_x;
      $d1=new \DateTime("@".$min_x);
      $d2=new \DateTime("@".$max_x);
      $diff=$d1->diff($d2);
      $n=$diff->y*12+$diff->m;
      $x=$min_x;
      for ($i=0;$i<=$n;$i++)
      {
        $x_captions[]=date("M-Y",$x);
        $x=strtotime("+1 month",$x);
      }
      $data["grid_x"]=($max_x-$min_x)/$n;
      break;
    case "year":
      # TODO
      break;
  }
  $data["x_captions"]=$x_captions;
}

#####################################################################################################

function output_legend_line($series)
{
  $chart_colors=\webdb\chart\chart_colors();
  $color=$series["color"];
  $color=$chart_colors[$color];
  $w=60;
  $h=20;
  $buffer=imagecreatetruecolor($w,$h);
  $bg_color=imagecolorallocate($buffer,255,0,255); # magenta
  imagecolortransparent($buffer,$bg_color);
  imagefill($buffer,0,0,$bg_color);
  $line_color=imagecolorallocate($buffer,$color[0],$color[1],$color[2]);
  imageline($buffer,0,$h/2,$w-1,$h/2,$line_color);
  return \webdb\graphics\base64_image($buffer,"png");
}

#####################################################################################################

function output_chart($data,$filename=false)
{
  global $settings;
  $text_file=$settings["gd_ttf"];
  $font_size=10;
  $title_font_size=12;
  $tick_length=5;
  $label_space=4;
  $left=$data["left"];
  $right=$data["right"];
  $top=$data["top"];
  $bottom=$data["bottom"];
  $title_margin=5;
  $chart_colors=\webdb\chart\chart_colors();
  $w=$data["w"];
  $h=$data["h"];
  $min_x=$data["x_min"];
  $max_x=$data["x_max"];
  $min_y=$data["y_min"];
  $max_y=$data["y_max"];
  $grid_x=$data["grid_x"];
  $grid_y=$data["grid_y"];
  $dx=$max_x-$min_x;
  $dy=$max_y-$min_y;
  $buffer=imagecreatetruecolor($w,$h);
  imageantialias($buffer,true);
  $bg_color=imagecolorallocate($buffer,253,253,253);
  imagefill($buffer,0,0,$bg_color);
  $line_color=imagecolorallocate($buffer,230,230,250);
  imagerectangle($buffer,0,0,$w-1,$h-1,$line_color);
  $line_color=imagecolorallocate($buffer,230,230,230);
  $n=round($dx/$grid_x);
  for ($i=0;$i<=$n;$i++)
  {
    $rx=$grid_x*$i+$min_x;
    $x=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$rx);
    imageline($buffer,$x,$top,$x,$h-$bottom-1,$line_color);
  }
  $n=round($dy/$grid_y);
  for ($i=0;$i<=$n;$i++)
  {
    $ry=$grid_y*$i+$min_y;
    $y=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$ry);
    imageline($buffer,$left,$y,$w-$right-1,$y,$line_color);
  }
  for ($i=0;$i<count($data["series"]);$i++)
  {
    $series=$data["series"][$i];
    $color=$series["color"];
    $color=$chart_colors[$color];
    $line_color=imagecolorallocate($buffer,$color[0],$color[1],$color[2]);
    $x_values=$series["x_values"];
    $y_values=$series["y_values"];
    switch ($series["type"])
    {
      case "plot":
        $n=count($x_values)-1;
        for ($j=0;$j<$n;$j++)
        {
          $x1=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$x_values[$j]);
          $y1=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$y_values[$j]);
          if ($series["marker"]=="box")
          {
            imagerectangle($buffer,$x1-2,$y1-2,$x1+2,$y1+2,$line_color);
          }
          $x2=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$x_values[$j+1]);
          $y2=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$y_values[$j+1]);
          imageline($buffer,$x1,$y1,$x2,$y2,$line_color);
        }
        if (($series["marker"]=="box") and ($n>1))
        {
          imagerectangle($buffer,$x2-2,$y2-2,$x2+2,$y2+2,$line_color);
        }
        break;
      case "step":
        $x2=false;
        $y2=false;
        $n=count($x_values)-1;
        for ($j=0;$j<$n;$j++)
        {
          $min_x_exceeded=false;
          if ($x_values[$j+1]<$min_x)
          {
            if ($x_values[$j+1]<>end($x_values))
            {
              continue;
            }
            else
            {
              $x_values[$j+1]=$min_x;
              $min_x_exceeded=true;
            }
          }
          if ($x_values[$j]>$max_x)
          {
            continue;
          }
          if ($x_values[$j]<$min_x)
          {
            $x_values[$j]=$min_x;
          }
          $max_x_exceeded=false;
          if ($x_values[$j+1]>$max_x)
          {
            $x_values[$j+1]=$max_x;
            $y_values[$j+1]=$y_values[$j];
            $max_x_exceeded=true;
          }
          $x1=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$x_values[$j]);
          $y1=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$y_values[$j]);
          $x2=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$x_values[$j+1]);
          imageline($buffer,$x1,$y1,$x2,$y1,$line_color);
          if ($max_x_exceeded==false)
          {
            $y2=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$y_values[$j+1]);
            imageline($buffer,$x2,$y1,$x2,$y2,$line_color);
            if ($min_x_exceeded==false)
            {
              imagerectangle($buffer,$x2-2,$y2-2,$x2+2,$y2+2,$line_color);
            }
          }
        }
        $rx=time();
        if ((end($x_values)<$rx) and (isset($data["today_mark"])==true) and ($x2!==false) and ($y2!==false))
        {
          $x=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$rx);
          imageline($buffer,$x2,$y2,$x,$y2,$line_color);
        }
        break;
    }
  }
  if (isset($data["today_mark"])==true)
  {
    $rx=time();
    if (($rx>$min_x) and ($rx<$max_x))
    {
      $color=$data["today_mark"];
      $color=$chart_colors[$color];
      $line_color=imagecolorallocate($buffer,$color[0],$color[1],$color[2]);
      $x=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$rx);
      $y1=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$max_y);
      $y2=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$min_y);
      imageline($buffer,$x,$y1,$x,$y2,$line_color);
    }
  }
  $line_color=imagecolorallocate($buffer,50,50,50);
  $x=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$min_x);
  imageline($buffer,$x,$top,$x,$h-$bottom-1,$line_color);
  $n=round($dy/$grid_y);
  for ($i=0;$i<=$n;$i++)
  {
    $ry=$grid_y*$i+$min_y;
    $y=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$ry);
    $caption=$ry;
    if (isset($data["y_axis_format"])==true)
    {
      $caption=sprintf($data["y_axis_format"],$ry);
    }
    if (isset($data["y_captions"][$i])==true)
    {
      $caption=$data["y_captions"][$i];
    }
    imageline($buffer,$x,$y,$x-$tick_length,$y,$line_color);
    $bbox=imagettfbbox($font_size,0,$text_file,$caption);
    $text_w=$bbox[2]-$bbox[0];
    $text_h=$bbox[1]-$bbox[7];
    $text_x=$x-$text_w-$tick_length-$label_space;
    $text_y=$y+round($text_h/2);
    imagettftext($buffer,$font_size,0,$text_x,$text_y,$line_color,$text_file,$caption);
  }
  $y=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$min_y);
  imageline($buffer,$left,$y,$w-$right-1,$y,$line_color);
  $grid_x_pixels=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$grid_x);
  $n=round($dx/$grid_x);
  for ($i=0;$i<=$n;$i++)
  {
    $rx=$grid_x*$i+$min_x;
    $x=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$rx);
    $caption=$rx;
    if (isset($data["x_axis_format"])==true)
    {
      $caption=sprintf($data["x_axis_format"],$rx);
    }
    if (isset($data["x_captions"][$i])==true)
    {
      $caption=$data["x_captions"][$i];
    }
    $bbox=imagettfbbox($font_size,0,$text_file,$caption);
    $text_w=$bbox[2]-$bbox[0];
    $text_h=$bbox[1]-$bbox[7];
    if ($grid_x_pixels<($text_h*2))
    {
      if (($i%2)>0)
      {
        continue;
      }
    }
    imageline($buffer,$x,$y,$x,$y+$tick_length,$line_color);
    imageline($buffer,$x,$y+$tick_length,$x-$tick_length,$y+2*$tick_length,$line_color);
    $text_x=$x-round($text_w/sqrt(2))-$tick_length;
    $text_y=$y+round($text_w/sqrt(2))+2*$tick_length+$label_space+2;
    imagettftext($buffer,$font_size,45,$text_x,$text_y,$line_color,$text_file,$caption);
  }
  if ($data["x_title"]<>"")
  {
    $title=$data["x_title"];
    $cx=($w-$left-$right)/2+$left;
    $bbox=imagettfbbox($title_font_size,0,$text_file,$title);
    $text_w=$bbox[2]-$bbox[0];
    $text_h=$bbox[1]-$bbox[7];
    $text_x=$cx-round($text_w/2);
    $text_y=$h-$title_margin;
    imagettftext($buffer,$title_font_size,0,$text_x,$text_y,$line_color,$text_file,$title);
  }
  if ($data["y_title"]<>"")
  {
    $title=$data["y_title"];
    $cy=($h-$bottom-$top)/2+$top;
    $bbox=imagettfbbox($title_font_size,0,$text_file,$title);
    $text_w=$bbox[2]-$bbox[0];
    $text_h=$bbox[1]-$bbox[7];
    $text_x=$title_margin+$text_h;
    $text_y=$cy+round($text_w/2);
    imagettftext($buffer,$title_font_size,90,$text_x,$text_y,$line_color,$text_file,$title);
  }
  if ($filename!==false)
  {
    imagepng($buffer,$filename);
  }
  else
  {
    return \webdb\graphics\base64_image($buffer,"png");
  }
}

#####################################################################################################

function pixels_per_unit($pix,$min,$max)
{
  return (($pix-1)/($max-$min));
}

#####################################################################################################

function output_chart_pix_series($data,$key)
{
  $result=array();
  $s=$data["series"][$key];
  for ($i=0;$i<count($s["x_values"]);$i++)
  {
    $x=$s["x_values"][$i];
    $x=\webdb\chart\real_to_pixel_x($data["w"],$data["left"],$data["right"],$data["x_min"],$data["x_max"],$x);
    $y=$s["y_values"][$i];
    $y=\webdb\chart\real_to_pixel_y($data["h"],$data["top"],$data["bottom"],$data["y_min"],$data["y_max"],$y);
    $result[]=array($x,$y);
  }
  return $result;
}

#####################################################################################################

function get_caption($data,$series_key,$axis,$val)
{
  $captions=$data[$axis."_captions"];
  $min=$data[$axis."_min"];
  $max=$data[$axis."_max"];
  $delta=$max-$min;
  $grid=$data["grid_".$axis];
  $result=false;
  $min_error=$max;
  for ($i=0;$i<count($captions);$i++)
  {
    $test=$grid*$i+$min;
    $error=abs($test-$val);
    if ($error<$min_error)
    {
      $min_error=$error;
      $result=$i;
    }
  }
  if ($result!==false)
  {
    $result=$captions[$result];
  }
  return $result;
}

#####################################################################################################

function pixel_to_chart_x($pix,$data)
{
  $w=$data["w"];
  $left=$data["left"];
  $min=$data["x_min"];
  $max=$data["x_max"];
  $ppu=\webdb\chart\pixels_per_unit($pix,$min,$max);
  $chart_w=$w-$left-$right;
  return ($pix-$left)/$ppu+$min_x;
}

#####################################################################################################

function pixel_to_chart_y($pix,$data)
{
  $h=$data["h"];
  $bottom=$data["bottom"];
  $min=$data["y_min"];
  $max=$data["y_max"];
  $ppu=\webdb\chart\pixels_per_unit($pix,$min,$max);
  $chart_h=$h-$top-$bottom;
  return ($chart_h-$pix+$top-1)/$ppu+$min_y;
}

#####################################################################################################

function real_to_pixel_x($w,$left,$right,$min_x,$max_x,$rx)
{
  $chart_w=$w-$left-$right;
  return round(($rx-$min_x)*\webdb\chart\pixels_per_unit($chart_w,$min_x,$max_x))+$left;
}

#####################################################################################################

function real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$ry)
{
  $chart_h=$h-$top-$bottom;
  return ($chart_h-1-round(($ry-$min_y)*\webdb\chart\pixels_per_unit($chart_h,$min_y,$max_y)))+$top;
}

#####################################################################################################

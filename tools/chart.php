<?php

namespace webdb\chart;

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
      $d1=date("Y-m-d",$min_x);
      $d2=date("Y-m-d",$max_x);
      $diff=date_diff(date_create($d1),date_create($d2));
      $n=$diff->format("%m");
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

function output_chart($data)
{
  global $settings;
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
  $left=60;
  $right=10;
  $top=10;
  $bottom=60;
  $buffer=imagecreatetruecolor($w,$h);
  imageantialias($buffer,true);
  $bg_color=imagecolorallocate($buffer,253,253,253);
  imagefill($buffer,0,0,$bg_color);
  $line_color=imagecolorallocate($buffer,230,230,250);
  imagerectangle($buffer,0,0,$w-1,$h-1,$line_color);
  $text_file=$settings["gd_ttf"];
  $font_size=10;
  $tick_length=5;
  $label_space=4;
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
  $line_color=imagecolorallocate($buffer,50,50,50);
  $x=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$min_x);
  imageline($buffer,$x,$top,$x,$h-$bottom-1,$line_color);
  $n=round($dy/$grid_y);
  for ($i=0;$i<=$n;$i++)
  {
    $ry=$grid_y*$i+$min_y;
    $y=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$ry);
    imageline($buffer,$x,$y,$x-$tick_length,$y,$line_color);
    $bbox=imagettfbbox($font_size,0,$text_file,$ry);
    $text_w=$bbox[2]-$bbox[0];
    $text_h=$bbox[1]-$bbox[7];
    $text_x=$x-$text_w-$tick_length-$label_space;
    $text_y=$y+round($text_h/2);
    imagettftext($buffer,$font_size,0,$text_x,$text_y,$line_color,$text_file,$ry);
  }
  $y=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$min_y);
  imageline($buffer,$left,$y,$w-$right-1,$y,$line_color);
  $n=round($dx/$grid_x);
  for ($i=0;$i<=$n;$i++)
  {
    $rx=$grid_x*$i+$min_x;
    $x=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$rx);
    imageline($buffer,$x,$y,$x,$y+$tick_length,$line_color);
    imageline($buffer,$x,$y+$tick_length,$x-$tick_length,$y+2*$tick_length,$line_color);
    $caption=$rx;
    if (isset($data["x_captions"][$i])==true)
    {
      $caption=$data["x_captions"][$i];
    }
    $bbox=imagettfbbox($font_size,0,$text_file,$caption);
    $text_w=$bbox[2]-$bbox[0];
    $text_h=$bbox[1]-$bbox[7];
    #$text_x=$x-round($text_w/2); # for horizontal text
    #$text_y=$y+$text_h+$tick_length+$label_space; # for horizontal text
    $text_x=$x-round($text_w/sqrt(2))-$tick_length;
    $text_y=$y+round($text_w/sqrt(2))+2*$tick_length+$label_space+2;
    imagettftext($buffer,$font_size,45,$text_x,$text_y,$line_color,$text_file,$caption);
  }
  for ($i=0;$i<count($data["series"]);$i++)
  {
    $series=$data["series"][$i];
    $color=$series["color"];
    $color=$chart_colors[$color];
    $line_color=imagecolorallocate($buffer,$color[0],$color[1],$color[2]);
    $x_values=$series["x_values"];
    $y_values=$series["y_values"];
    $n=count($x_values)-1;
    for ($j=0;$j<$n;$j++)
    {
      $x1=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$x_values[$j]);
      $y1=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$y_values[$j]);
      $x2=\webdb\chart\real_to_pixel_x($w,$left,$right,$min_x,$max_x,$x_values[$j+1]);
      $y2=\webdb\chart\real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$y_values[$j+1]);
      imageline($buffer,$x1,$y1,$x2,$y2,$line_color);
    }
  }
  return \webdb\graphics\base64_image($buffer,"png");
}

#####################################################################################################

function pixels_per_unit($pix,$min,$max)
{
  return (($pix-1)/($max-$min));
}

#####################################################################################################

function real_to_pixel_x($w,$left,$right,$min_x,$max_x,$rx)
{
  $chart_w=$w-$left-$right;
  return round(($rx-$min_x)*pixels_per_unit($chart_w,$min_x,$max_x))+$left;
}

#####################################################################################################

function real_to_pixel_y($h,$top,$bottom,$min_y,$max_y,$ry)
{
  $chart_h=$h-$top-$bottom;
  return ($chart_h-1-round(($ry-$min_y)*pixels_per_unit($chart_h,$min_y,$max_y)))+$top;
}

#####################################################################################################

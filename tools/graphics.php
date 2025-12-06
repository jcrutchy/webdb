<?php

namespace webdb\graphics;

#####################################################################################################

function blend_rect_horz($buffer,$x1,$y1,$x2,$y2,$from_color,$to_color,$transparent=false,$reverse=false) # colors are both 3-element RGB arrays
{
  # ref: https://geekthis.net/post/php-gradient-images-rectangle-gd/
  $delta=$x2-$x1;
  for ($i=0;$i<$delta;$i++)
  {
    $r=$from_color[0]-((($from_color[0]-$to_color[0])/$delta)*$i);
    $g=$from_color[1]-((($from_color[1]-$to_color[1])/$delta)*$i);
    $b=$from_color[2]-((($from_color[2]-$to_color[2])/$delta)*$i);
    if ($transparent==false)
    {
      $color=imagecolorallocate($buffer,round($r),round($g),round($b));
    }
    else
    {
      if ($reverse==false)
      {
        $a=floor($i/$delta*128);
      }
      else
      {
        $a=127-floor($i/$delta*128);
      }
      $color=imagecolorallocatealpha($buffer,round($r),round($g),round($b),$a);
    }
    imagefilledrectangle($buffer,$x1+$i,$y1,$x1+$i+1,$y2,$color);
  }
}

#####################################################################################################

function base64_image_encode($image_data,$type,$template="base64_image",$display="block",$border="0")
{
  $params=array();
  $params["border"]=$border;
  $params["display"]=$display;
  $params["type"]=$type;
  $encoded=base64_encode($image_data);
  $params["data"]=chunk_split($encoded,76,"\r\n");
  return \webdb\utils\template_fill($template,$params);
}

#####################################################################################################

function base64_image($image,$type,$display="block",$border="0")
{
  ob_start();
  switch ($type)
  {
    case "gif":
      {
        imagegif($image);
        break;
      }
    case "jpg":
    case "jpeg":
      {
        imagejpeg($image);
        break;
      }
    case "png":
      {
        imagepng($image);
        break;
      }
  }
  $image_data=ob_get_contents();
  ob_end_clean();
  return \webdb\graphics\base64_image_encode($image_data,$type,"base64_image",$display,$border);
}

#####################################################################################################

function scale_img(&$buffer,$scale,$w,$h,$transparent_color=false)
{
  $final_w=round($w*$scale);
  $final_h=round($h*$scale);
  $buffer_resized=imagecreatetruecolor($final_w,$final_h);
  if ($transparent_color!==false)
  {
    imagecolortransparent($buffer_resized,$transparent_color);
    imagealphablending($buffer_resized,true);
    imagesavealpha($buffer_resized,true);
    imagefill($buffer_resized,0,0,$transparent_color);
  }
  if (imagecopyresampled($buffer_resized,$buffer,0,0,0,0,$final_w,$final_h,$w,$h)==false)
  {
    return false;
  }
  imagedestroy($buffer);
  $buffer=imagecreate($final_w,$final_h);
  if ($transparent_color!==false)
  {
    imagecolortransparent($buffer,$transparent_color);
    imagealphablending($buffer,true);
    imagesavealpha($buffer,true);
    imagefill($buffer,0,0,$transparent_color);
  }
  if (imagecopy($buffer,$buffer_resized,0,0,0,0,$final_w,$final_h)==false)
  {
    return false;
  }
  imagedestroy($buffer_resized);
}

#####################################################################################################

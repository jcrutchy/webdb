<?php

namespace webdb\pdf;

#####################################################################################################

# apt-get install wkhtmltopdf
# https://wkhtmltopdf.org/

# todo: needs to be able to authenticate
# todo: limit to localhost (change url to uri and prefix localhost)
# todo: make $name into a setting
# todo: zoom parameter doesn't seem to have any effect

#####################################################################################################

function output_pdf()
{
  global $settings;
  if (isset($_GET["url"])==false)
  {
    \webdb\utils\system_message("url not specified");
  }
  $name="output.pdf";
  \webdb\utils\check_required_setting_exists("pdf_temp_path");
  \webdb\utils\check_required_file_exists($settings["pdf_temp_path"],true);
  $filename=microtime(true)."_".bin2hex(random_bytes(10)).".pdf";
  $full=$settings["pdf_temp_path"].$filename;
  $cmd="export DISPLAY=':0.0'; wkhtmltopdf --zoom 0.2 --print-media-type ".escapeshellarg($_GET["url"])." ".escapeshellarg($full)." 2>&1";
  exec($cmd,$result_array,$result_value);
  if (file_exists($full)==false)
  {
    \webdb\utils\system_message("pdf file not found");
  }
  $handle=fopen($full,"r");
  if ($handle===false)
  {
    \webdb\utils\system_message("error reading pdf file");
  }
  $fsize=filesize($full);
  header("Content-type: application/pdf");
  header("Content-Disposition: inline; filename=\"".$name."\"");
  header("Content-length: ".$fsize);
  header("Cache-control: private");
  echo fread($handle,$fsize);
  fclose($handle);
  unlink($full);
}

#####################################################################################################

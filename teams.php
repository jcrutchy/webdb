<?php

namespace webdb\teams;

#####################################################################################################

function teams_message_card($title,$username,$message)
{
  global $settings;
  $card_data=\webdb\utils\template_fill("teams/message_card");
  $card_data=json_decode($card_data,true);
  $card_data["summary"]=$title;
  $card_data["sections"][0]["activityTitle"]=$title;
  $card_data["sections"][0]["activitySubtitle"]=$message;
  $fact=array();
  $fact["name"]="username";
  $fact["value"]=$username;
  $card_data["sections"][0]["facts"][]=$fact;
  return $card_data;
}

#####################################################################################################

function teams_record_card($title,$subtitle,$caption,$url,$record)
{
  global $settings;
  $card_data=\webdb\utils\template_fill("teams/record_card_json");
  $card_data=json_decode($card_data,true);
  $card_data["summary"]=$title;
  $card_data["sections"][0]["activityTitle"]=$title;
  $card_data["sections"][0]["activitySubtitle"]=$subtitle;
  foreach ($record as $key => $value)
  {
    $fact=array();
    $fact["name"]=$key;
    $fact["value"]=$value;
    $card_data["sections"][0]["facts"][]=$fact;
  }
  $card_data["potentialAction"][0]["name"]=$caption;
  $card_data["potentialAction"][0]["targets"][0]["uri"]=$url;
  return $card_data;
}

#####################################################################################################

function teams_notify($card_data,$webhook_url,$peer_name="*.webhook.office.com")
{
  global $settings;
  if ($settings["dev_env"]==true)
  {
    return;
  }
  $cookie_jar=array();
  $headers=array();
  $headers[]="Accept: application/json";
  $headers[]="Content-Type: application/json";
  $headers[]="Connection: close";
  $response=\webdb\http\wpost($webhook_url,json_encode($card_data),$peer_name,$cookie_jar,$headers);
  $content=\webdb\http\get_content($response);
  return json_decode($content,true);
}

#####################################################################################################

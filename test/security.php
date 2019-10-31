<?php

namespace webdb\test\security;

define("webdb\\test\\security\\ERROR_COLOR",31);
define("webdb\\test\\security\\SUCCESS_COLOR",32);
define("webdb\\test\\security\\INFO_COLOR",94);

#####################################################################################################

function start()
{
  \webdb\cli\term_echo("STARTING SECURITY TESTS...",\webdb\test\security\INFO_COLOR);
  \webdb\test\security\remote_address_change();
  \webdb\cli\term_echo("FINISHED SECURITY TESTS",\webdb\test\security\INFO_COLOR);
}

#####################################################################################################

function start_test_user()
{
  if (\webdb\test\security\get_test_user()===false)
  {
    \webdb\test\security\create_test_user();
    if (\webdb\test\security\get_test_user()===false)
    {
      \webdb\cli\term_echo("ERROR STARTING TEST USER: USER NOT FOUND AFTER INSERT",\webdb\test\security\ERROR_COLOR);
      die;
    }
  }
  \webdb\cli\term_echo("TEST USER STARTED",\webdb\test\security\INFO_COLOR);
}

#####################################################################################################

function finish_test_user()
{
  if (\webdb\test\security\get_test_user()===false)
  {
    \webdb\cli\term_echo("ERROR FINISHING TEST USER: USER NOT FOUND",\webdb\test\security\ERROR_COLOR);
    die;
  }
  \webdb\test\security\delete_test_user();
  if (\webdb\test\security\get_test_user()!==false)
  {
    \webdb\cli\term_echo("ERROR FINISHING TEST USER: ERROR DELETING",\webdb\test\security\ERROR_COLOR);
    die;
  }
  \webdb\cli\term_echo("TEST USER FINISHED",\webdb\test\security\INFO_COLOR);
}

#####################################################################################################

function get_test_user()
{
  $sql_params=array();
  $sql_params["username"]="test_user";
  $sql="SELECT * FROM webdb.users WHERE username=:username";
  $records=\webdb\sql\fetch_prepare($sql,$sql_params);
  if (count($records)==1)
  {
    return $records[0];
  }
  return false;
}

#####################################################################################################

function create_test_user()
{
  $items=array();
  $items["username"]="test_user";
  $items["enabled"]=1;
  $items["email"]="";
  $items["pw_change"]=0;
  $items["user_agent"]="webdb_test/1.0";
  $items["remote_address"]="192.168.0.21";
  \webdb\sql\sql_insert($items,"users","webdb");
}

#####################################################################################################

function delete_test_user()
{
  $sql_params=array();
  $sql_params["username"]="test_user";
  \webdb\sql\sql_delete($sql_params,"users","webdb");
}

#####################################################################################################

function remote_address_change()
{
  \webdb\cli\term_echo("if any of the higher 3 octets of the user's remote address change, invalidate cookie login (require password)",\webdb\test\security\INFO_COLOR);
  # create test user record
  \webdb\test\security\start_test_user();
  # password login as test user
  # get home page as test user with last octet changed
  # check result
  # delete test user record
  \webdb\test\security\finish_test_user();
}

#####################################################################################################

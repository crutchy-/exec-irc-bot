<?php

# gpl2
# by crutchy
# 26-may-2014

# Ref: https://www.alien.net.au/irc/irc2numerics.html

#####################################################################################################

define("CHAN_CIV","#civ");
define("NICK","exec");

ini_set("display_errors","on");

$cmd=$argv[1];
$trailing=$argv[2];
$data=$argv[3];
$dest=$argv[4];
$params=$argv[5];
$nick=$argv[6];

require_once("lib.php");

switch ($cmd)
{
  case "330": # is logged in as
    $parts=explode(" ",$params);
    if ((count($parts)==3) and ($parts[0]==NICK))
    {
      $nick=$parts[1];
      $account=$parts[2];
      if ($nick<>NICK)
      {
        echo ":".NICK." NOTICE ".CHAN_CIV." :civ login $nick $account\n";
        /*echo ":$nick NOTICE ".CHAN_CIV." :~lock civ\n";
        sleep(1);
        echo ":$nick NOTICE ".CHAN_CIV." :flag public_status\n";
        sleep(1);
        echo ":$nick NOTICE ".CHAN_CIV." :status\n";*/
      }
    }
    break;
  case "353": # channel names list
    sleep(3);
    $parts=explode(" = ",$params);
    if (count($parts)==2)
    {
      if (($parts[0]==NICK) and ($parts[1]==CHAN_CIV))
      {
        $names=explode(" ",$trailing);
        for ($i=0;$i<count($names);$i++)
        {
          $name=$names[$i];
          if ((substr($name,0,1)=="+") or (substr($name,0,1)=="@"))
          {
            $name=substr($name,1);
          }
          if ($name==NICK)
          {
            continue;
          }
          echo "IRC_RAW WHOIS $name\n";
          sleep(1);
        }
      }
    }
    break;
  case "JOIN":
    if ($dest==CHAN_CIV)
    {
      if ($nick==NICK)
      {
        echo ":crutchy NOTICE #civ :civ-map generate\n";
      }
      echo "IRC_RAW WHOIS $nick\n";
    }
    break;
  case "KILL":
  case "KICK":
  case "QUIT":
  case "PART":
    if ($dest==CHAN_CIV)
    {
      echo ":".NICK." NOTICE ".CHAN_CIV." :civ logout $nick\n";
    }
    break;
  #case "043": # Sent to the client when their nickname was forced to change due to a collision
  #case "436": # Returned by a server to a client when it detects a nickname collision
  case "NICK":
    echo ":".NICK." NOTICE ".CHAN_CIV." :civ rename $nick $trailing\n";
    break;
  case "PRIVMSG":
    echo ":$nick NOTICE $dest :~AUJ73HF839CHH2933HRJPA8N2H $trailing\n"; # last.php
    #echo ":$nick NOTICE $dest :mackey $trailing\n"; # /nas/server/git/chromas/mackey.php
    break;
  case "NOTICE":
    break;
  case "MODE":
    break;
  case "PING":
    break;
  case "263": # When a server drops a command without processing it, it MUST use this reply.
    break;
  case "471": # Returned when attempting to join a channel which is set +l and is already full
    break;
  case "404":
    break;
  case "311":
    #:irc.sylnt.us 311 exec tme520 ~TME520 218-883-738-54.tpgi.com.au * :TME520
    if ($trailing=="SedBot")
    {
      echo ":$nick NOTICE $dest :~AUJ73HF839CHH2933HRJPA8N2H exec.sed.disable\n"; # last.php
    }
    break;
  case "401": # No such nick/channel
    #echo "IRC_RAW :".NICK_EXEC." PRIVMSG #soylent :SedBot not found\n";
    $parts=explode(" ",$params);
    if (count($parts)==2)
    {
      if ($parts[1]=="SedBot")
      {
        #echo "IRC_RAW :".NICK_EXEC." PRIVMSG #soylent :SedBot not found\n";
        echo ":$nick NOTICE $dest :~AUJ73HF839CHH2933HRJPA8N2H exec.sed.enable\n"; # last.php
      }
    }
    break;
}

#####################################################################################################

?>

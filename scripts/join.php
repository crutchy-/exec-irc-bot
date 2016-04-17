<?php

#####################################################################################################

/*
exec:~join|5|0|0|0|+||||php scripts/join.php %%trailing%%
*/

#####################################################################################################

require_once("lib.php");
$parts=explode(",",$argv[1]);
$prefixes="#&";
for ($i=0;$i<count($parts);$i++)
{
  $parts[$i]=trim($parts[$i]);
  if (strpos($prefixes,substr($parts[$i],0,1))===False)
  {
    privmsg("invalid channel: \"".$parts[$i]."\" (skipping)");
    unset($parts[$i]);
  }
}
if (get_bot_nick()<>"exec")
{
  $parts=array_values($parts);
  users_get_account("exec");
  sleep(3);
  $exec_channels=users_get_channels("exec");
  for ($i=0;$i<count($parts);$i++)
  {
    if ((in_array($parts[$i],$exec_channels)==True) and ($parts[$i]<>"#debug"))
    {
      term_echo("exec is in channel \"".$parts[$i]."\" (skipping)");
      privmsg("exec is in channel \"".$parts[$i]."\" (skipping)");
      unset($parts[$i]);
    }
  }
}
$parts=array_values($parts);
for ($i=0;$i<count($parts);$i++)
{
  echo "/IRC JOIN ".$parts[$i]."\n";
}

#####################################################################################################

?>

<?php

# gpl2
# by crutchy

#####################################################################################################

/*
exec:~php|5|0|0|0||||0|php scripts/php.php %%trailing%%
*/

#####################################################################################################

# TODO: privmsg URL to php.net function reference

# thanks to prospectacle for link to download doc files

require_once("lib.php");
$msg=$argv[1];
$msg=str_replace("_","-",$msg);
$msg=filter($msg,VALID_UPPERCASE.VALID_LOWERCASE.VALID_NUMERIC."-");
#$filename="/var/include/vhosts/irciv.us.to/data/php_manual/php-chunked-xhtml/function.".$msg.".html";
$filename="../data/php_manual/php-chunked-xhtml/function.".$msg.".html";
if (file_exists($filename)==False)
{
  privmsg("function not found");
  return;
}
$html=file_get_contents($filename);
$delim1="<div class=\"methodsynopsis dc-description\">";
$delim2="<p class=\"para rdfs-comment\">";
$i=strpos($html,$delim1);
if ($i===False)
{
  echo "delim1 not found\n";
  privmsg("script error");
  return;
}
$i=$i+strlen($delim1);
$html=substr($html,$i);
$i=strpos($html,$delim2);
if ($i===False)
{
  echo "delim2 not found\n";
  privmsg("script error");
  return;
}
$syntax=trim(strip_tags(substr($html,0,$i)));
$syntax=str_replace("\n","",$syntax);
$syntax=str_replace("  ","",$syntax);
if (strlen($syntax)<500)
{
  if ($syntax=="")
  {
    privmsg("unable to find syntax");
  }
  else
  {
    privmsg($syntax);
  }
}

#####################################################################################################

?>

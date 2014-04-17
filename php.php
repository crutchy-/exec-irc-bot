<?php

# gpl2
# by crutchy
# 16-april-2014

# 0/0/php/php php.php "%%msg%%"

$msg=$argv[1];
$msg=str_replace("_","-",$msg);
$html=wget("www.php.net","/manual/en/function.$msg.php",80);
$delim1="<div class=\"methodsynopsis dc-description\">";
$delim2="<p class=\"para rdfs-comment\">";
$i=strpos($html,$delim1);
if ($i===False)
{
  echo "delim1 not found\n";
  echo "privmsg script error\n";
  return;
}
$i=$i+strlen($delim1);
$html=substr($html,$i);
$i=strpos($html,$delim2);
if ($i===False)
{
  echo "delim2 not found\n";
  echo "privmsg script error\n";
  return;
}
$syntax=trim(strip_tags(substr($html,0,$i)));
$syntax=str_replace("\n","",$syntax);
$syntax=str_replace("  "," ",$syntax);
if (strlen($syntax)<500)
{
  if ($syntax=="")
  {
    echo "privmsg unable to find syntax\n";
  }
  else
  {
    echo "privmsg $syntax\n";
  }
}

function wget($host,$uri,$port)
{
  $fp=fsockopen($host,$port);
  if ($fp===False)
  {
    term_echo("Error connecting to \"$host\".");
    return;
  }
  fwrite($fp,"GET $uri HTTP/1.0\r\nHost: $host\r\nConnection: Close\r\n\r\n");
  $response="";
  while (!feof($fp))
  {
    $response=$response.fgets($fp,1024);
  }
  fclose($fp);
  return $response;
}

?>
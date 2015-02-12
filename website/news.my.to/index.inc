<?php

#####################################################################################################

/*
  RewriteEngine On
  RewriteRule ^/stories/(.*)$ /index.php?sid=$1 [L,QSA]
  RewriteRule ^/img/(.*)$ /index.php?img=$1 [L,QSA]
  RewriteRule ^/op/(.*)$ /index.php?op=$1 [L,QSA]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.php [L]
*/

#####################################################################################################

require_once(__DIR__."/db.inc");

define("TEMPLATES_PATH",__DIR__."/templates");

$page=trim(file_get_contents(TEMPLATES_PATH."/template_page.txt"));

$css=trim(file_get_contents(__DIR__."/default.css"));
$page=str_replace("%%css%%",$css,$page);

$menu=trim(file_get_contents(TEMPLATES_PATH."/template_menu.txt"));
$menu_item_template=trim(file_get_contents(TEMPLATES_PATH."/template_menu_item.txt"));
$active_menu_item_template=trim(file_get_contents(TEMPLATES_PATH."/template_menu_item_active.txt"));
$menu_items_html="";
$menu_items_data=file_get_contents(__DIR__."/menu_items.txt");
$menu_items_data=explode("\n",$menu_items_data);
$settings=array();
for ($i=0;$i<count($menu_items_data);$i++)
{
  $parts=explode("=",$menu_items_data[$i]);
  $caption=$parts[0];
  array_shift($parts);
  $href=implode("=",$parts);
  if ($href=="")
  {
    $menu_item_html=$caption;
  }
  else
  {
    if ($_SERVER["REQUEST_URI"]==$href)
    {
      $menu_item_html=$active_menu_item_template;
    }
    else
    {
      $menu_item_html=$menu_item_template;
      $menu_item_html=str_replace("%%href%%",$href,$menu_item_html);
    }
    $menu_item_html=str_replace("%%caption%%",$caption,$menu_item_html);
  }
  $menu_items_html=$menu_items_html.$menu_item_html;
}
$menu=str_replace("%%menu_items%%",$menu_items_html,$menu);

$page=str_replace("%%title%%","news.my.to",$page);

$story_head_template=trim(file_get_contents(TEMPLATES_PATH."/template_story_head.txt"));
$story_summary_template=trim(file_get_contents(TEMPLATES_PATH."/template_story_summary.txt"));

$stories_html="";

$stories=array();

$test_story=array();
$test_story["sid"]=1678;
$test_story["date"]="16 Feb 2015";
$test_story["score"]=5;
$test_story["comments"]=54;
$test_story["title"]="Test story title";
$test_story["summary"]="Google is reportedly laying plans to enter the wireless phone service business, in competition with Verizon and AT&T and in co-opetition with T-Mobile and Sprint. In late January, The Information broke the news that the company had reached agreements with T-Mobile and Sprint (paywalled, but a secondary report is here) enabling Google to use their cellular networks. When placing a call, Google's handset would choose between the two carrier networks, or Wi-Fi, depending on the caller's location and relative signal strength of the alternatives.";

$stories[]=$test_story;
$stories[]=$test_story;
$stories[]=$test_story;
$stories[]=$test_story;
$stories[]=$test_story;
$stories[]=$test_story;

for ($i=0;$i<count($stories);$i++)
{
  $story_head=$story_head_template;
  $story_head=str_replace("%%sid%%",$stories[$i]["sid"],$story_head);
  $story_head=str_replace("%%date%%",$stories[$i]["date"],$story_head);
  $score_caption=$stories[$i]["score"];
  if ($stories[$i]["score"]>0)
  {
    $score_caption="+".$score_caption;
  }
  $story_head=str_replace("%%score%%",$score_caption,$story_head);
  $comments_caption="no comments";
  if ($stories[$i]["comments"]>1)
  {
    $comments_caption=$stories[$i]["comments"]." comments";
  }
  elseif ($stories[$i]["comments"]>0)
  {
    $comments_caption=$stories[$i]["comments"]." comment";
  }
  $story_head=str_replace("%%comments%%",$comments_caption,$story_head);
  $story_head=str_replace("%%title%%",$stories[$i]["title"],$story_head);
  $story_summary=$story_summary_template;
  $story_summary=str_replace("%%summary%%",$stories[$i]["summary"],$story_summary);
  $stories_html=$stories_html.$story_head.$story_summary;
}

$body=$menu.$stories_html;

$page=str_replace("%%body%%",$body,$page);

echo $page;

#####################################################################################################

?>
<?php

#####################################################################################################

/*
exec:~alias|timeout|repeat|0|1|account-list|cmd-list|dest-list|bucket-lock-list|php scripts/blah.php %%trailing%% %%dest%% %%nick%% %%start%% %%alias%% %%cmd%% %%data%% %%params%% %%timestamp%% %%items%% %%server%%

exec:add ~blah
exec:edit ~blah timeout 5
exec:edit ~blah repeat 0
exec:edit ~blah auto 0
exec:edit ~blah empty 1
exec:edit ~blah accounts account1,account2,account3
exec:edit ~blah accounts_wildcard *
exec:edit ~blah cmds PRIVMSG,INTERNAL,JOIN
exec:edit ~blah servers irc.sylnt.us
exec:edit ~blah dests #channel1,#channel2,#channel3
exec:edit ~blah bucket_locks bucket1,bucket2,bucket3
exec:edit ~blah cmd php scripts/blah.php %%trailing%% %%dest%% %%nick%% %%start%% %%alias%% %%cmd%% %%data%% %%params%% %%timestamp%% %%items%% %%server%%
exec:enable ~blah

help:~blah syntax: ~blah
help:~blah description

init:~meeting register-events
startup:~join #blah
*/

#####################################################################################################

require_once("lib.php");

$trailing=$argv[1];
$dest=$argv[2];
$nick=$argv[3];
$start=$argv[4];
$alias=$argv[5];
$cmd=$argv[6];
$data=$argv[7];
$params=$argv[8];
$timestamp=$argv[9];
$items=unserialize(base64_decode($argv[10]));
$server=$argv[11];

#####################################################################################################

?>

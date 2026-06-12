<?php

global $dbx;
            if($channels==20 || $channels==12){
              $spammer=strstr($cinput," ");
              $spammer=ucwords(trim($spammer));
              $query="Select last_ip from Players where name='$spammer'";
              if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                $chatresult="The IP for $spammer is: '$tgt_last_ip'";
              }else {
                $chatresult="Player $spammer does not exist";
              }
            }else {
              $chatresult="You do not have this power!";
            }
?>
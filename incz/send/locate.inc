<?php

global $dbx;
            if($channels==20 || $channels==12){
              $spammer=strstr($cinput," ");
              $spammer=ucwords(trim($spammer));
              $query="Select name,loc_x,loc_y,loc_zone from Players where name='$spammer'";
              if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                $query="Select Nname from Names where Nnum='$tgt_loc_zone'";
                $result=mysqli_query($dbx, $query);
                extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                $chatresult="$tgt_name is in $tgt_loc_x,$tgt_loc_y in $tgt_Nname";
              } else {
                $chatresult="Player $spammer does not exist";
              }
            }else {
              $chatresult="You do not have this power!";
            }
?>
<?php

global $dbx;
            if($channels==12 || $channels==20){
              $spammer=strstr($cinput," ");
              $spammer=ucwords(trim($spammer));
              $query="Select Id,channels, banned from Players where name='$spammer'";
              if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                if($tgt_channels !=10 && $tgt_channels !=11 && $tgt_channels !=12 && $tgt_channels !=20){
                  if ($tgt_channels == 66) {
                    $tgt_banned-=10;
                  }
                  $query="Update Players set channels=0,mhd=0,jhd=0,loc_x=10,loc_y=8,loc_zone=10,banned='$tgt_banned' where Id = $tgt_Id";
                  mysqli_query($dbx, $query);
                  $query="Insert into Modactions values('$pname','$spammer','voiced',now())";
                  mysqli_query($dbx, $query);
                  $chatresult="$spammer has been restored to normal talk";
                  $query="Insert into Messages values($tgt_Id,'$pname','voiced you',26,now())";
                  mysqli_query($dbx, $query);
                }else {
                  $chatresult="Cannot unmute/uncurse other Moderators";
                }
              }else {
                $chatresult="Player $spammer does not exist";
              }
            }else {
		$chatresult="You do not have this power!";
            }
?>
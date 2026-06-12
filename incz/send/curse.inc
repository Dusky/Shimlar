<?php

global $dbx;
            if($channels==10 || $channels==11 || $channels==12 || $channels==20){
              $spammer=strstr($cinput," ");
              $spammer=ucwords(trim($spammer));
              $query="Select Id,channels from Players where name='$spammer'";
              if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                if($tgt_channels<10 || $tgt_channels==19){
                  $query="Update Players set channels=8 where Id = $tgt_Id";
                  mysqli_query($dbx, $query);
                  $query="Insert into Modactions values('$pname','$spammer','cursed',now())";
                  mysqli_query($dbx, $query);
                  $chatresult="$spammer has been Cursed!";
                  $query="Insert into Messages values($tgt_Id,'$pname','cursed you for one hour.',26,now())";
                  mysqli_query($dbx, $query);
                }else if($tgt_channels==66) {
                  $chatresult="Player already Muted!";
                }else {
                  $chatresult="Cannot Curse other Moderators";
                }
              }else {
                $chatresult="Player $spammer does not exist";
              }
            }else {
              $chatresult="You do not have this power!";
            }
?>
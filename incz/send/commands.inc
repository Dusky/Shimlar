<?php

global $dbx;
              if(($channels > 9) && ($channels < 21)){
                 $spammer=strstr($cinput," ");
                 $spammer=ucwords(trim($spammer));
                 $query="Select Id, name from Players where name='$spammer'";
                 if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                   extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                   $query="Insert into Messages values($tgt_Id,'Commands','Please <a href=\'http://www.shimlar.org/commands.htm\' target=\'_blank\'>click here for a list of all commands</a>.',6,now())";
                   mysqli_query($dbx, $query);
                   $chatresult="Link to commands sent to $tgt_name";
                 }else {
                  $chatresult="Player $spammer does not exist";
                 }
               }else {
                 $chatresult="You do not have this power!";
               }
?>
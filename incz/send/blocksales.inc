<?php

global $dbx;
            if($channels==10 || $channels==11 || $channels==12 || $channels==20){
              $spammer=strstr($cinput," ");
              $spammer=ucwords(trim($spammer));
              $query="Select Id from Players where name='$spammer'";
              if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                $query="Update Stats set stopSales = 2 where Id = $tgt_Id";
                mysqli_query($dbx, $query);
                $query="Insert into Modactions values('$pname','$spammer','sales block',now())";
                mysqli_query($dbx, $query);
                $chatresult="$spammer has been stopped from talking in Sales!";
              }else {
                $chatresult="Player $spammer does not exist";
              }
            }else {
              $chatresult="You do not have this power!";
            }
?>
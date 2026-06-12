<?php

global $dbx;
            if($channels==10 || $channels==20 || $channels==12){
              $spammer=strstr($cinput," ");
              $spammer=ucwords(trim($spammer));
              $query="Select banned from Players where name='$spammer'";
              if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                $query="update Inventory set checked=1 where pname='$spammer'";
                mysqli_query($dbx, $query);
		$chatresult="$spammer is unable to trade, please continue.  /uncheck name too if needed!";	
              }else {
                $chatresult="Player $spammer does not exist";
              }
            }else {
              $chatresult="You do not have this power!";
            }
?>

<?php

global $dbx;
	if(($channels==10)||($channels==11)||($channels==12)||($channels==20)) {
            $telln=strstr($cinput," ");
            $telln=trim($telln);
            $telltxt=strstr($telln,":");
            $telltxt=substr($telltxt,2);
            $telln=substr($telln,0,(strlen($telln)-strlen($telltxt)-2));
            $telln=ucwords($telln);
            $query="Select Id as spId from Players where name='$telln'";
            if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
            	extract(mysqli_fetch_array($result));
                $query="Insert into Messages values($spId,'$pname','$telltxt',5,now())";
              	mysqli_query($dbx, $query);
              	$query="update Inventory set checked = 1 where Id = $spId";
              	mysqli_query($dbx, $query);
            	$chatresult="[player pm] You blocked trading and told $telln: $telltxt";
            }else {
              $chatresult="Player $telln does not exist";
            }
     	} else {
		$chatresult="Whateva!";
	}
?>


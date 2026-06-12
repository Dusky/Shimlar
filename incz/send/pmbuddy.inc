<?php

global $dbx;

		$cinput=strstr($cinput," ");
    $cinput=trim($cinput);
    
    $query="select BuddyId from Buddy where Id = $Id and Status = 1";
    if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)>0)
    {
    	$a1=mysqli_num_rows($result);
    	for	($i=0;$i<$a1;$i++) 
    	{
    		$row=mysqli_fetch_row($result);
    		$tgt_id=$row[0];
    		$query="Insert into Messages values($tgt_id,'$pname','$cinput',5,now())";
        mysqli_query($dbx, $query);                   
    	}
    	$chatresult="You say to your buddies: $cinput";
    } 
    else 
    {
    	$chatresult="You have no buddies in your list.";
    }
?>

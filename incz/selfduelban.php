<?php
include 'constvars.inc'; 
init_dbx();
$query	="select p.Id,p.name,s.opponent from Players p, Stats s where ((p.Id=s.Id) and (p.Id=s.opponent) and (p.banned!=100))";
$result	=mysqli_query($dbx, $query);
$c1	=mysqli_num_rows($result);
for($i=0;$i<$c1;$i++) {
	$row	=mysqli_fetch_row($result);
	$name	=$row[1];
	$id	=$row[0];
	$query	="update Players set banned=100, channels=0 where Id = $id";
	mysqli_query($dbx, $query);
	$query	="insert into Modactions values('Lord Morpheus', '$name', 'Self-Duel hack auto-ban', now())";
	mysqli_query($dbx, $query);
    $query  ="insert into chat1 values('','$name',' has been banned for bug exploitation','32')";
    mysqli_query($dbx, $query);
    $query  ="insert into chat2 values('','$name',' has been banned for bug exploitation','32')";
    mysqli_query($dbx, $query);
    $query  ="insert into chat3 values('','$name',' has been banned for bug exploitation','32')";
    mysqli_query($dbx, $query);
};
close_dbx();
?>
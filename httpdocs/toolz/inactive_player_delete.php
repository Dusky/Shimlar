<?php

include '/var/www/shimlar.com/httpdocs/incz/constvars.inc'; 
init_dbx();
print "Starting player wipe.<br>";
  $query="Select p.name,s.lvl,s.gold,s.banked,p.ax_time from Players as p, Stats as s where (p.name=s.name and p.banned=100) ||(p.name=s.name and s.lvl<50 and (s.gold+s.banked)<10000000 and now()-p.ax_time>72000000) order by p.name";
  $result=mysqli_query($dbx, $query);
  $a1=mysqli_num_rows($result);
  for($i=0; $i<$a1; $i++) {
    $row=mysqli_fetch_row($result);
    $pname=$row[0];
    $query="Delete from Players where name='$pname'";
    mysqli_query($dbx, $query);
    $query="Delete from Inventory where pname='$pname'";
    mysqli_query($dbx, $query);
    $query="Delete from Messages where pto='$pname' || pfrom='$pname'";
    mysqli_query($dbx, $query);
    $query="Delete from Modactions where mtarget='$pname'";
    mysqli_query($dbx, $query);
    $query="Delete from Login where name='$pname'";
    mysqli_query($dbx, $query);
    $query="Delete from Transfers where tto='$pname' || tfrom='$pname'";
    mysqli_query($dbx, $query);

  }
print "Player wipe completed. $a1 records deleted.";
close_dbx();

?>

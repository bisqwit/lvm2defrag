<?php

class Location
{
  public $pv, $offset, $size;
  function __construct($p,$o,$s)
  {
    $this->pv = $p;
    $this->offset = $o;
    $this->size   = $s;
  }
  
  function GetOverlapType(Location $b)
  { 
    /* Return values: 0 = no overlap,
                      1 = some overlap,
                      2 = this includes b,
                      3 = b includes this,
                      5 = both */
    if($b->pv != $this->pv) return 0;
    
    $abeg = $this->offset;
    $aend = $this->size + $abeg;
    $bbeg = $b->offset;
    $bend = $b->size + $bbeg;
    
    if($abeg >= $bend || $bbeg >= $aend) return 0; // no overlap
    if($abeg == $bbeg && $aend == $bend) return 5;
    if($abeg <= $bbeg && $aend >= $bend) return 2; // true overlap (a includes b)
    if($bbeg <= $abeg && $bend >= $aend) return 3; // true overlap (b includes a)
    return 1; // some overlap
  }
};

/* An obj is a stripe of a logical volume. */
class Obj /* the name of the obj is insignificant. */
{
  public $size;    // Number of extents
  public $name;
  
  public $cur_pv,  $cur_ofs;  // Where it is now
  public $goal_pv, $goal_ofs; // Where it should go
  
  public $move_pv, $move_ofs; // Where it is being moved at the call of M...()
  public $move_reason;

  function __construct($name, $size)
  {
    $this->name = $name;
    $this->size = $size;
    $this->cur_pv = false;  $this->cur_ofs = 0;
    $this->goal_pv = false; $this->goal_ofs = 0;
    $this->move_pv = false; $this->move_ofs = 0;
  }
  
  function AtHome() { return $this->cur_pv == $this->goal_pv
                          && $this->cur_ofs == $this->goal_ofs; }

  function GetGoal() { return new Location($this->goal_pv, $this->goal_ofs, $this->size); }
  function GetMove() { return new Location($this->move_pv, $this->move_ofs, $this->size); }
  function GetLoc() { return new Location($this->cur_pv, $this->cur_ofs, $this->size); }
};
class Pv
{
  public $name; // Name of the device
  public $size; // Number of extents
  
  public $contents; // What is where.
  // Array(Obj), sorted by cur_ofs.

  function __construct($name)
  {
    $this->name = $name;
    $this->size = 0;
    $this->contents = Array();
  }
};

function SmallestHoleCom($a,$b)
{
  return $a->size - $b->size;
}
function LargestHoleCom($a,$b)
{
  return -SmallestHoleCom($a,$b);
}
function SmallestPosCom($a,$b)
{
  if($a->offset == $b->offset) return $a->size - $b->size;
  return $a->offset - $b->offset;
}
function LvSizeCom($a,$b)
{
  return $a['size'] - $b['size'];
}

class VG
{
  public $name; // Name of the volume group
  public $objs;
  public $pvs;
  public $lvs;
  
  public $scheduled_moves;
  
  function __construct($name)
  {
    $this->name = $name;
    $this->objs = Array();
    $this->pvs  = Array();
    $this->lvs  = Array();
    $this->scheduled_moves = 0;
  }
  
  function ScheduleMove(&$lv, $loc, $why)
  {
    $lv->move_pv  = $loc->pv;
    $lv->move_ofs = $loc->offset;
    $lv->move_reason = $why;
    ++$this->scheduled_moves;
  }
  
  function ScheduleAllGoalable()
  {
    $res = Array();
    foreach($this->lvs as &$lv)
      if(!$lv->AtHome())
      {
        $goal = $lv->GetGoal();
        $occupiers = $this->GetOccupierList($goal);
        if(empty($occupiers))
          $this->ScheduleMove($lv, $goal, 'Final goal');
      }
    return $res;
  }
  
  function HasScheduled()
  {
    return $this->scheduled_moves;
  }
  
  function FlushSchedules()
  {
    $rightnow = Array();
    
    foreach($this->lvs as $lvname => &$lv)
      if($lv->move_pv !== false)
      {
        $rightnow[$lvname] = Array('size' => $lv->size,
                                   'goal' => $lv->GetMove(),
                                   'reason' => $lv->move_reason);
      }
    
    uasort($rightnow, 'LvsizeCom');
    
    if(count($rightnow) > 1) print "## Begin group of moves that could be done in parallel\n";
    
    if(false)
    {
      foreach($rightnow as $lvname => $info)
        $this->MoveLv($this->lvs[$lvname], $info['goal'], $info['reason']);
    }
    else
    {
      /* Group the moves by pairs of physical devices */
      $moves = Array();
      foreach($rightnow as $lvname => $info)
      {
        $lv = &$this->lvs[$lvname];
        $moves[$lv->cur_pv][$lv->move_pv][] = &$lv;
      }
      foreach($moves as $src_pv => $goal_pv_list)
        foreach($goal_pv_list as $goal_pv => $lv_list)
          $this->MoveMultipleLv($src_pv, $goal_pv, $lv_list);
    }

    if(count($rightnow) > 1) print "## End group\n";

    foreach($this->lvs as $lvname => &$lv)
    {
      $lv->move_pv = false;

      if($lv->AtHome()) unset($this->lvs[$lvname]);
    }

    $this->scheduled_moves = 0;
  }
  
  function Optimize()
  {
    for(;;)
    {
      $this->ScheduleAllGoalable();
      
      if($this->HasScheduled()) { $this->FlushSchedules(); continue; }
      
      $found_troubles = false;
      foreach($this->lvs as &$lv)
      {
        if($lv->AtHome()) continue;
        $found_troubles = true;
        
        #print_r($lv);
        
        // Because this lv is not at goal yet,
        // we can assume that there is an obstacle.
        // Figure out what that obstacle is.
        
        $this->TryMakeRoomTo($lv->GetGoal(), $lv->name, null);
        
        // Now there should be no occupiers anymore.
        break;
      } // end search for troubled seekers
      
      #print_r($this->lvs);
      
      if(!$found_troubles) break;
    } // end infinite loop
  }
  
  function TryMakeRoomTo(Location $from, $whosebidding, $exclude = null)
  {
    if($exclude === null) $exclude = Array($from);
   
    $desirability = 0; // Desirability of current best solution
    
    $occupiers = $this->GetOccupierList($from);
    foreach($occupiers as $lvname)
    {
      $occu = &$this->lvs[$lvname];
      $occu_goal = $occu->GetGoal();
      $occu_occupiers = $this->GetOccupierList($occu_goal);
      
      if(empty($occu_occupiers))
      {
        if($desirability > 2) return; $desirability = 2;
        
        // Note: This shouldn't happen, but it improves robustness to have it here.
        $this->ScheduleMove($occu, $occu_goal, 'Occupier goal');
        continue;
      }

      /* Find out where to move the occupier */
      $hole = $this->FindSmallestHoleLargerThan($occu->size, $exclude);
      if($hole !== false)
      {
        if($desirability > 1) return; $desirability = 1;
        
        $this->ScheduleMove($occu, $hole, 'Moving away');
        $exclude[] = $hole;
        continue;
      }
    
    #  $excludetmp = $exclude;
    #  $excludetmp[] = $occu_goal;
    #  $this->TryMakeRoomTo($occu_goal, $excludetmp);

      if($desirability > 0) return; $desirability = 0;

      $hole = $this->FindLargestHoleButNotAnyOf($exclude);
      if($hole === false)
      {
        if($occu->name == $whosebidding)
        {  
          /* Special case: Block is overlapping its own destined goal */
          $hole = new Location('', 0, abs($occu->cur_ofs - $occu->goal_ofs));
        }
        else
        {
          print "# UNABLE TO CONTINUE, NO HOLES FOR {$occu->name}, FOR $whosebidding?\n";
          $holes = $this->ListHoles(Array());
          print_r($holes);
          return;
        }
      }
      $this->SplitLv($occu, $hole->size, $from);
      break;
    }
  }
  
  function FindSmallestHoleLargerThan($size, $excludelist)
  {
    $holes = $this->ListHoles($excludelist);
    usort($holes, 'SmallestHoleCom');
    foreach($holes as $hole)
      if($hole->size >= $size)
      {
        /* Give a position from the end of the hole */
        $hole->offset += $hole->size - $size;
        return $hole;
      }
    return false;
  }
  function FindLargestHoleButNotAnyOf($excludelist)
  {
    $holes = $this->ListHoles($excludelist);
    if(empty($holes)) return false;
    usort($holes, 'LargestHoleCom');
    return $holes[0];
  }
  function GetOccupierList(Location $loc)
  {
    $res = Array();
    $begin = $loc->offset;
    $end   = $loc->offset + $loc->size;
    foreach($this->pvs[$loc->pv]->contents as $lv)
    {
      $lv_begin = $lv->cur_ofs;
      $lv_end   = $lv_begin + $lv->size;
      
      if($lv_begin < $end && $lv_end > $begin)
        $res[] = $lv->name;
    }
    return $res;
  }
  function ListHoles($excludelist = Array())
  {
    $res = Array();
    foreach($this->pvs as $pv)
    {
      $o = 0;
      $contents = Array();
      
      foreach($pv->contents as $lv)
        $contents[] = new Location($lv->cur_pv, $lv->cur_ofs, $lv->size);
      
      foreach($excludelist as $exclude)
        if($pv->name == $exclude->pv)
          $contents[] = $exclude;
      
      usort($contents, 'SmallestPosCom');

      foreach($contents as $loc)
      {
        if($loc->offset > $o) $res[] = new Location($pv->name, $o, $loc->offset - $o);
        $o = max($o, $loc->offset + $loc->size);
      }
      if($pv->size > $o) $res[] = new Location($pv->name, $o, $pv->size - $o);
    }
    return $res;
  }
  
  function MoveLv(&$lv, $goal, $reason = '')
  {
    if(!isset($this->pvs[$lv->cur_pv]->contents[$lv->cur_ofs]))
    {
      print "# HUH, GHOST LV?\n";
    }
    unset($this->pvs[$lv->cur_pv]->contents[$lv->cur_ofs]);
    
    $spv  = $lv->cur_pv;
    $sbeg = $lv->cur_ofs;
    $send = $lv->cur_ofs + $lv->size - 1;

    $lv->cur_pv  = $goal->pv;
    $lv->cur_ofs = $goal->offset;

    $dpv  = $lv->cur_pv;
    $dbeg = $lv->cur_ofs;
    $dend = $lv->cur_ofs + $lv->size - 1;
    
    print "# Moving {$lv->name} ($reason)\n";
    
    $interval = '';
    if($dend - $dbeg <= 200) $interval = '-i10';
    if($dend - $dbeg <= 50) $interval = '-i5';
    if($dend - $dbeg <= 10) $interval = '-i2';
    if($dend - $dbeg <= 1) $interval = '-i1';
    
    print "pvmove $interval --alloc anywhere $spv:$sbeg-$send $dpv:$dbeg-$dend\n";
    
    $this->pvs[$lv->cur_pv]->contents[$lv->cur_ofs] = &$lv;
  }
  function MoveMultipleLv($spv, $dpv, $lvlist)
  {
    $src_pv  = &$this->pvs[$spv];
    $dest_pv = &$this->pvs[$dpv];
    
    #print "## Begin cluster\n";

    foreach($lvlist as $l=>&$lv)
      if($lv->cur_pv != $spv || $lv->move_pv != $dpv)
      {
        print "## ERRONEOUS MOVEMULTIPLELV ($spv != {$lv->cur_pv} OR $dpv != {$lv->move_pv})\n";
      }

    while(!empty($lvlist))
    {
      /*
      TODO: Because pvmove works by creating a temporary mirror LV,
            We must split the moves so that we only ever move as
            large spans as there are temporary contiguous spaces
            available >= size of the combined move.
      */
      $move_size = 0;
      $excludelist = Array();
      foreach($lvlist as $l=>&$lv)
      {
        $move_size += $lv->size;
        $excludelist[] = $lv->GetMove();
      }
      $largest_temp_room = $this->FindLargestHoleButNotAnyOf($excludelist);
      #print_r($excludelist);
      #print_r($largest_temp_room);
      $largest_temp_size = $largest_temp_room ? $largest_temp_room->size : 0;
      
      print "# Largest temp size $largest_temp_size, desire $move_size\n";
      
      $group = $lvlist;
      
      if($largest_temp_size < $move_size)
      {
        print "# Not enough contiguous temporary room for a multipart pvmove\n";
        /* Not enough room to move all at once!
         * Try creating a sub group and moving them.
         */
        $move_size = 0;
        $group     = Array();
        $excludelist = Array();
        foreach($lvlist as $l=>&$lv)
        {
          $tmpe = $excludelist;
          $tmpe[] = $lv->GetMove();

          $tmp_room = $this->FindLargestHoleButNotAnyOf($tmpe);
          $tmp_size = $tmp_room ? $tmp_room->size : 0;
          
          if($move_size + $lv->size <= $tmp_size)
          {
            $group[$l] = $lv;
            $move_size += $lv->size;
            $excludelist = $tmpe;
          }
        }
      
        /* If all the pvs were larger than we can move...
         * Bummer! Then we try splitting.
         */
        if(empty($group))
        {
          print "# Not enough room for any of the pvmoves. Splitting a task.\n";
          foreach($lvlist as $l=>&$lv)
          {
            $partsize = $largest_temp_size;
            if(!$partsize) $partsize = (int)($lv->size / 2);
            
            $split = $this->SplitLv($lv, $partsize, new Location('',0,0));
            unset($lvlist[$l]);
            $lvlist[] = &$split[0];
            $lvlist[] = &$split[1];
            unset($split);
            break;
          }
          continue;
        }
      }
      
      $mlist = '';
      $slist = '';
      $dlist = '';
      $amount = 0;
      foreach($group as $l=>&$lv)
      {
        unset($src_pv->contents[$lv->cur_ofs]);
        $dest_pv->contents[$lv->move_ofs] = &$lv;
        
        $sbeg = $lv->cur_ofs;
        $send = $lv->cur_ofs + $lv->size - 1;

        $lv->cur_pv  = $dpv;
        $lv->cur_ofs = $lv->move_ofs;

        $dbeg = $lv->cur_ofs;
        $dend = $lv->cur_ofs + $lv->size - 1;
        $amount += $dend - $dbeg + 1;
        
        print "# Moving {$lv->name} ({$lv->size}) ({$lv->move_reason})\n";
        $slist .= ":$sbeg-$send";
        $dlist .= ":$dbeg-$dend";
        
        unset($lvlist[$l]);
      }
      
      $interval = '';
      if($amount <= 200) $interval = '-i10';
      if($amount <= 50) $interval = '-i5';
      if($amount <= 10) $interval = '-i2';
      if($amount <= 1) $interval = '-i1';

      print "pvmove $interval --alloc anywhere {$spv}$slist {$dpv}$dlist\n";
    }
    #print "## End cluster\n";
  }
  function SplitLv(&$lv, $split_size, $prefer_region)
  {
    /* Check out which way is better for splitting */
    $begin_type = $prefer_region->GetOverlapType(
      new Location($lv->cur_pv,
                   $lv->cur_ofs,
                   $split_size));
    $end_type = $prefer_region->GetOverlapType(
      new Location($lv->cur_pv,
                   $lv->cur_ofs + $lv->size - $split_size,
                   $split_size));
    
    if($end_type == 3 || $end_type == 5)
    {
      $part2size = $split_size;
      $part1size = $lv->size - $part2size;
    }
    else
    {
      $part1size = $split_size;
      $part2size = $lv->size - $part1size;
    }
    
    $newlv1 = clone $lv; $newlv1->name .= " p1"; $newlv1->size = $part1size;
    $newlv2 = clone $lv; $newlv2->name .= " p2"; $newlv2->size = $part2size;
    
    echo "# LV split {$lv->name} ($part1size + $part2size = {$lv->size})\n";
    
    $newlv2->cur_ofs  += $part1size;
    $newlv2->goal_ofs += $part1size;
    $newlv2->move_ofs += $part1size;
    
    unset($this->pvs[$lv->cur_pv]->contents[$lv->cur_ofs]);
    unset($this->lvs[$lv->name]);

    $this->pvs[$newlv1->cur_pv]->contents[$newlv1->cur_ofs] = &$newlv1;
    $this->pvs[$newlv2->cur_pv]->contents[$newlv2->cur_ofs] = &$newlv2;
    
    $this->lvs[$newlv1->name] = &$newlv1;
    $this->lvs[$newlv2->name] = &$newlv2;
    
    return Array(&$newlv1, &$newlv2);
  }
};

$vgs = Array();

unset($vg); unset($pv); unset($pv_ofs);
$vg = false; $pv = false; $pv_ofs = 0;
foreach(preg_split("/\r?\n/",file_get_contents('dump.txt')) as $line)
{
  if(preg_match('/^!! /', $line))
  {
    $vg = &$vgs[substr($line,3)];
    if(!$vg) $vg = new VG(substr($line,3));
  }
  elseif(preg_match('/^! /', $line))
  {
    $pvname = substr($line,2);
    $pv = &$vg->pvs[$pvname];
    if(!$pv) $pv = new PV($pvname);
    $pv_ofs = 0;
  }
  elseif(preg_match('/^[(0-9]/', $line))
  {
    $tokens = preg_split("/[\t ]+/", $line);
    if($tokens[0][0] == '(')
      $pv_ofs += (int)substr($tokens[0], 1);
    else
    {
      $lvname = $tokens[1];
      $lv = &$vg->lvs[$lvname];
      if(!$lv) $lv = new Obj($lvname, (int)$tokens[0]);
      $lv->cur_pv  = $pv->name;
      $lv->cur_ofs = $pv_ofs;
      $pv->contents[$pv_ofs] = &$lv;
      $pv_ofs += (int)$tokens[0];
    }
    $pv->size = $pv_ofs;
  }
}
unset($vg); unset($pv); unset($lv); unset($pv_ofs);

$vg = false; $pv = false; $pv_ofs = 0;
$pvsize2 = Array();
foreach(preg_split("/\r?\n/",file_get_contents('rearrange.txt')) as $line)
{
  if(preg_match('/^!! /', $line))
  {
    $vg = &$vgs[substr($line,3)];
    if(!$vg) $vg = new VG(substr($line,3));
  }
  elseif(preg_match('/^! /', $line))
  {
    $pvname = substr($line,2);
    $pv = &$vg->pvs[$pvname];
    if(!$pv)
    {
      $pv = new PV($pvname);
      print "ERROR: Volume {$pvname} was not mentioned in current, is in goal\n";
    }
    $pv_ofs = 0;
  }
  elseif(preg_match('/^[(0-9]/', $line))
  {
    $tokens = preg_split("/[\t ]+/", $line);
    if($tokens[0][0] == '(')
      $pv_ofs += (int)substr($tokens[0], 1);
    else
    {
      $lvname = $tokens[1];
      $lv = &$vg->lvs[$lvname];
      if(!$lv)
      {
        $lv = new Obj($lvname, (int)$tokens[0]);
        print "ERROR: Object {$lvname} was not mentioned in current, is in goal\n";
        print "       I won't create volumes, you do that yourself\n";
      }
      $lv->goal_pv  = $pv->name;
      $lv->goal_ofs = $pv_ofs;
      $pv_ofs += (int)$tokens[0];
    }
    $pvsize2[$pv->name] = $pv_ofs;
  }
}
unset($vg); unset($pv); unset($lv); unset($pv_ofs);

foreach($vgs as $vg)
{
  foreach($vg->pvs as $pvname => &$pv)
    if($pv->size != $pvsize2[$pvname])
    {
      print "Mismatch in PV $pvname size: original = {$pv->size}, new = {$pvsize2[$pvname]}\n";
    }
  foreach($vg->lvs as $lvname => &$lv)
    if(!$lv->goal_pv)
    {
      print "ERROR: Object {$lvname} was not mentioned in goal, was in current\n";
      print "       I won't destroy volumes, you do that yourself\n";
    }
}

#print_r($vgs);

foreach($vgs as &$vg)
{
  $vg->Optimize();
  foreach($vg->pvs as $pv)
    ksort($pv->contents);

  #$holes = $vg->ListHoles(Array());
  #print_r($holes);
}
#print_r($vgs);


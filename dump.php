<?php
require_once 'read_vsn1.php';

$data = parse_vsn1(file_get_contents('data.txt'));
#print_r($data);

$vg = Array();
$pv = Array();

foreach($data as $vgname => $value)
  if(is_array($value) && isset($value['seqno']))
  {
    $vgtmp = Array('pv' => Array(), 'lv' => Array());
    $vg[$vgname] = &$vgtmp;
    
    foreach($value['physical_volumes'] as $pvname => $pvdata)
    {
      $pvtmp = Array('device' => $pvdata['device'],
                     'npe'    => $pvdata['pe_count'],
                     'uses'   => Array());
      $vgtmp['pv'][$pvname] = &$pvtmp;
      $pv[$pvdata['device']] = &$pvtmp;
      
      unset($pvtmp);
    }
    foreach($value['logical_volumes'] as $lvname => $lvdata)
    {
      #$lvtmp = Array('name' => $lvname, 'parts' => Array());
      #$vgtmp['lv'][$lvname] = &$lvtmp;
      foreach($lvdata as $datakey => $datavalues)
      {
        if(preg_match('/^segment[0-9]/', $datakey))
        {
          $seg = (int)substr($datakey, 7);
        
          $nparts = 1;
          $partkey = 'parts';
          $nparts_div = 1;
          if($datavalues['type'] == 'mirror')
          {
            $nparts = $datavalues['mirror_count'];
            $partkey = 'mirrors';
          }
          if($datavalues['type'] == 'raid1')
          {
            $nparts = $datavalues['device_count'];
            $partkey = 'raids';
            $nparts = 0;
          }
          if($datavalues['type'] == 'striped')
          {
            $nparts = $datavalues['stripe_count'];
            $nparts_div = $nparts;
            $partkey = 'stripes';
          }
          if($datavalues['type'] == 'snapshot')
          {
            print "# SNAPSHOT VOLUME '{$datakey}' DETECTED AND IGNORED. PLEASE EXERCISE CAUTION.\n\n";
            continue;
          }

          $fpe    = $datavalues['start_extent'];
          $npe    = $datavalues['extent_count'] / $nparts_div;

          if($datavalues['type'] == 'thin-pool')
          {
            $nparts = 0;
          }
          if($datavalues['type'] == 'thin')
          {
            $nparts = 0;
          }
          if($datavalues['type'] == 'cache')
          {
            $nparts = 0;
          }
          if($datavalues['type'] == 'cache-pool')
          {
            $nparts = 0;
          }

          for($n=0; $n < $nparts; ++$n)
          {
            $partcode = strtoupper(base_convert(10+$n,10,36));
            $lvnamepart = "{$lvname}$partcode-$seg";
            $lvpart = &$vgtmp['lv'][$lvnamepart];
            
            $pvname = $datavalues[$partkey][$n*2+0];
            $pvoffs = $datavalues[$partkey][$n*2+1];
            
            $lvpart =
              Array(//'start'  => $fpe,
                    'count'  => $npe,
                    'pv'     => $pvname,
                    'pvoffs' => $pvoffs);
           
            #if($partkey != 'mirrors')
            {
              /*if(preg_match('/^pvmove/', $pvname))
              {
                print "# PVMOVE DETECTED IN PROGRESS; THIS FILE IS DEFUNCTIONAL.\n";
                print "# WAIT UNTIL PVMOVE IS COMPLETED, THEN DO A NEW DUMP.\n";
                print "\n";
              }
              else*/if($partkey == 'mirrors')
              {
                print "# MIRROR VOLUMES DETECTED. LVM2DEFRAG MAY NOT HANDLE\n";
                print "# MIRROR VOLUMES PROPERLY. PLEASE EXERCISE CAUTION.\n";
                print "\n";
              }
              
              $vgtmp['pv'][$pvname]['uses'][$pvoffs] =
                Array('count'  => $npe,
                      'lv'     => $lvnamepart,
                    // 'lvoffs' => $fpe
                    );
            }
            unset($lvpart);
          }
        }
      }
      #unset($lvtmp);
    }
    
    unset($vgtmp);
  }

#print_r($pv);
#print_r($vg);

foreach($pv as &$pvdata)
  ksort($pvdata['uses']);
unset($pvdata);

foreach($vg as $vgname => $vgdata)
{
  print "!! $vgname\n\n";
  foreach($vgdata['pv'] as $pvname => $pvdata)
  {
    if(isset($pvdata['device']))
    {
      print "! {$pvdata['device']}\n";
      $begin = 0;
      $end   = $pvdata['npe'];
    }
    else
    {
      print "! *** $pvname ***\n";
      $begin = 0;
      $end   = 0;#$vgdata['lv'][$pvname]pvdata['count'];
    }

    if(empty($pvdata['uses']))
      print "# Nothing is using it\n";

    foreach($pvdata['uses'] as $offset => $data)
    {
      if($offset > $begin)
        printf("(%d)\t%s\n", $offset-$begin, '-');
      printf("%d\t%s\n", $data['count'], $data['lv']);
      $begin = $offset + $data['count'];
    }
    if($end > $begin)
      printf("(%d)\t%s\n", $end-$begin, '-');
    print "\n";
  }
}

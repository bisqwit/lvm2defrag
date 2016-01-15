<?php

error_reporting(E_ALL & ~E_NOTICE);

/*** Bisqwit's document generator
 *   Version 2.0.7
 *   Copyright (C) 1992,2007 Bisqwit (https://iki.fi/bisqwit/)
 *
 */
$lev=0;

$ndx = array();
$k = 0;
$lines = array();
$titles = array();
reset($text);
while((list($title, $content) = each($text)))
{
  $aname = preg_replace('@^([A-Za-z_0-9]+):.*@', '\1', $title);
  $class = preg_replace('@\. .*@', '', $title);
  
  if($aname == $title)
    $aname = 'h'.$k++;
  else
    $title = preg_replace('@^[A-Za-z_0-9]+:@', '', $title);
  
  $class = preg_replace('@\. .*@', '.', $title);
    
  $p = count_chars($class, 0);
  $level = $p[ord('.')];
  
  $line = '';

  for($l=$lev; $l<$level; $l++)
    $ndx[$l] = 0;
  $lev = $level;    

  for($l=0; $l<$level; $l++)
    $line .= '&nbsp;&nbsp;&nbsp;';

  $ndx[$lev-1]++;

  for($l=0; $l<$level; $l++)
  {
    if($l)$line .= '.';
    $line .= $ndx[$l];
  }
  $line .= '. <a href="#'.$aname.'">';
  $title = preg_replace('@^[0-9.]* *@', '', $title);
  if(isset($title_replace[$aname]))
    $line .= $title_replace[$aname];
  else
    $line .= htmlspecialchars($title);
  $line .= $title_ext[$aname];
  $line .= '</a>';
  $lines[] = $line;
  $titles[] = $title;
}

print '<div class=toc><table cellspacing=0 cellpadding=0 class=toc><tr>';
$sarac = (int)(count($lines) / 10);
for($k=$c=0; $c<count($lines); $c++)$k += strlen($titles[$c]);
$k /= count($lines); $k = (int)(75 / ($k+7));
if($sarac<2)$sarac=2;else if($sarac>$k)$sarac=$k;

if(isset($ctfin_hack)) $sarac = 1;

$km = (int)(count($lines) / $sarac);
for($k=$c=0; $c<$sarac; $c++)
{
  $sofar = (int)($c*100/$sarac);
  $next  = (int)(($c+1)*100/$sarac);
  echo '<td width="',($next-$sofar),'%" valign=middle align=left nowrap class=toc>';
  while($k<$km)
    echo $lines[$k++], '<br>';
  $km = (int)(($c+2)*count($lines)/$sarac);
  print "</td>\n";
}
print '</tr></table></div>';

unset($titles);

$k = 0;
reset($text);
$lev = 0;
while((list($title, $content) = each($text)))
{
  $aname = preg_replace('@^([a-zA-Z_0-9]+):.*@', '\1', $title);
  $class = preg_replace('@\. .*@', '', $title);
  
  if($aname == $title)
    $aname = 'h'.$k++;
  else
    $title = preg_replace('@^[a-zA-Z_0-9]+:@', '', $title);
  
  $class = preg_replace('@\. .*@', '.', $title);
    
  $p = count_chars($class, 0);
  $level = $p[ord('.')];
  
  for($l=$lev; $l<$level; $l++)
    $ndx[$l] = 0;
  $lev = $level;
  $ndx[$lev-1]++;

  echo $ozi_pre[$aname];
  echo '<H', $level+1, ' id="', $aname, '" class="level', $level+1, '">';
  echo '<a name="', $aname, '">';
  echo '</a>';

  for($l=0; $l<$level; $l++)
  {
    if($l)print '.';
    print $ndx[$l];
  }  
  echo '. ';
  if(isset($title_replace[$aname]))
    echo $title_replace[$aname];
  else
    echo htmlspecialchars(preg_replace('@^[0-9.]* *@', '', $title));
  echo $ozi_ext[$aname];
  echo '</H', $level+1, '>';
  
  echo '<div class="level', $level+1, '" id="div', $aname, '">';
  
  print $content;
  
  echo '</div>';
}
unset($lines);
unset($k);
unset($lev);
unset($aname);
unset($class);
unset($p);
unset($ndx);
unset($content);
unset($level);

<?php
//TITLE=LVM2 defragmenter

$title = 'LVM2 defragmenter';
$progname = 'lvm2defrag';

function usagetext($prog)
{
  exec($prog.' --help', $kk);
  $k='';foreach($kk as $s)$k.="$s\n";
  return $k;
}

$text = array(
   '1. Purpose' => "

LVM2 Defrag defragments or rearranges a LVM2 volume group using <tt>pvmove</tt>.

", '1. Copying' => "

lvm2defrag has been written by
<a href=\"http://iki.fi/bisqwit/\">Joel Yliluoma</a>
and other contributors,<br>
and is distributed under the terms of the
<a href=\"http://www.gnu.org/licenses/gpl-3.0.html\">General Public License</a>
version 3 (GPL3).

", '1. Requirements' => "

This software is currently written in PHP. The PHP
commandline program is required to execute.

", '1. Howto' => "

Here's how.

<h4>Step 1. Dump existing layout</h4>

<pre># vgcfgbackup
# cp /etc/lvm/backup/YOURVOLUMEGROUPNAME data.txt
\$ php -q dump.php > dump.txt</pre>

<h4>Step 2. Plan your desired layout</h4>

<pre>\$ cp dump.txt rearrange.txt
\$ editor rearrange.txt</pre>

In this file, you will move around the partitions
between disks into the order you wish they to be in.
Be careful to maintain the right amount of disk space
on each partition (the sum of numbers must match what they were before).
If you fail to maintain those numbers, the next command
will warn you, so it is not fatal.

<h4>Step 3. Create the sequence of commands to move data around</h4>

<pre>\$ php -q rearrange.php > commands.sh</pre>

Now verify the produced file, <tt>commands.sh</tt>, and
edit if you like to. If there are error messages in the file,
you may need to resolve them.

<h4>Step 4. Execute the commands</h4>

<pre># chmod +x commands.sh
./commands.sh</pre>

And wait.
 <p>
Note that the operation may be interrupted (and resumed)
at any time. The LVM2 volume group will never be left in
a broken state (this is guaranteed by how pvmove works).

", '1. Limitations' => "

<ul>
 <li>This software is provided as-is, from an expert
  to experts. Do not use it if you think you cannot
  understand the instructions above.</li>
 <li>This software does not abide by many usability
  principles. Apologies. Such work however may be
  contributed by sending a patch to the author. :)</li>
 <li>This software cannot probably handle
  crypted volumes (no experience)
  or anything other beyond the basic
  capabilities of LVM2. Apologies.</li>
</ul>

");
include '/WWW/progdesc.php';

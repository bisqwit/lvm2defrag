LVM2 defragmenter
=================

0. Purpose
----------

LVM2 Defrag defragments or rearranges a LVM2 volume group using ``pvmove``.

1. Copying
----------

lvm2defrag has been written by `Joel Yliluoma <http://iki.fi/bisqwit/>`__ and other contributors, and is distributed under the terms of the `General Public License <http://www.gnu.org/licenses/gpl-3.0.html>`__ version 3 (GPL3).

2. Requirements
---------------

This software is currently written in PHP. The PHP commandline program is required to execute.

3. Howto
--------

Here's how.

Easy-mode
^^^^^^^^^

::
  
   # ./lvm2defrag.sh YOURVOLUMEGROUPNAME
   # ./commands.sh

Execute the following commands, Your favorite editor should open, move around your partitions between disks into the order you wish they to be in. Save and exit.
You should now have commands.sh which contain all commands you need to process.
If something goes wrong, you should probably prefer the manual methode.

Step 1. Dump existing layout
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

::

    # vgcfgbackup
    # cp /etc/lvm/backup/YOURVOLUMEGROUPNAME data.txt
    $ php -q dump.php > dump.txt

Step 2. Plan your desired layout
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

::

    $ cp dump.txt rearrange.txt
    $ editor rearrange.txt

In this file, you will move around the partitions between disks into the order you wish they to be in. Be careful to maintain the right amount of disk space on each partition (the sum of numbers must match what they were before). If you fail to maintain those numbers, the next command will warn you, so it is not fatal.

Step 3. Create the sequence of commands to move data around
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

::

    $ php -q rearrange.php > commands.sh

Now verify the produced file, ``commands.sh``, and edit if you like to. If there are error messages in the file, you may need to resolve them.

Step 4. Execute the commands
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

::

    # chmod +x commands.sh
    ./commands.sh

And wait.

Note that the operation may be interrupted (and resumed) at any time. The LVM2 volume group will never be left in a broken state (this is guaranteed by how pvmove works).

4. Limitations
--------------

-  This software is provided as-is, from an expert to experts. Do not use it if you think you cannot understand the instructions above.
-  This software does not abide by many usability principles. Apologies. Such work however may be contributed by sending a patch to the author. :)
-  This software cannot probably handle crypted volumes (no experience) or anything other beyond the basic capabilities of LVM2. Apologies.


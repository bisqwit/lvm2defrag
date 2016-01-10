#!/bin/bash

if [ $# -ne 1 ]
then
	echo "Usage: $0 vgname"
	exit
fi

if [ $UID -ne 0 ]
then
	echo "This program need to be run as root."
	exit
fi

type php &>/dev/null
if [ $? -ne 0 ]
then
	echo "This script need php."
	exit
fi

function fail ()
{
	echo "An error occurs, fix it and try again."
	exit
}
vgname=$1
vgcfgbackup $vgname || fail
cp -f /etc/lvm/backup/$vgname data.txt || fail
php -q dump.php > dump.txt || fail
cp -f dump.txt rearrange.txt || fail
editor rearrange.txt
php -q rearrange.php > commands.sh || fail
warnline=$(grep -cvE '^(echo|pvmove|#)' commands.sh)
if [ $warnline -ne 0 ] ; then
	echo "Warning or error present in commands.sh, fix them."
	grep -cvE '^(echo|pvmove|#)' commands.sh
	exit
fi
chmod +x commands.sh

echo "All good, now you just have to execute ./commands.sh"



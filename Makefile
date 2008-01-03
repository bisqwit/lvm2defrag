VERSION=0.9.0
ARCHDIR=archives/
ARCHNAME=lvm2defrag-$(VERSION)
ARCHFILES=\
	read_vsn1.php \
	dump.php \
	rearrange.php \
	doc/algorithm.txt \
	doc/data-example.txt \
	doc/dump-example.txt \
	doc/rearrange-example.txt

include depfun.mak

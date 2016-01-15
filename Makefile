VERSION=0.9.4
ARCHDIR=archives/
ARCHNAME=lvm2defrag-$(VERSION)
ARCHFILES=\
	read_vsn1.php \
	dump.php \
	rearrange.php \
	doc/algorithm.txt \
	doc/data-example.txt \
	doc/dump-example.txt \
	doc/rearrange-example.txt \
	doc/docmaker.php doc/document.php doc/README.html


include depfun.mak

doc/README.html: doc/docmaker.php progdesc.php Makefile
	php -q "$<" "$(ARCHNAME)" > "$@"


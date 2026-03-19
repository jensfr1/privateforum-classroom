#!/bin/bash
set -e

# Package Info lesen
VERSION=$(grep -oP '(?<=<version>)[^<]+' package.xml)
NAME=$(grep -oP '(?<=name=")[^"]+' package.xml | head -1)
OUTPUT="${NAME}_${VERSION}.tar"

echo "Building ${NAME} v${VERSION} ..."

# files.tar
[ -d files ] && (cd files && tar cf ../files.tar *)
# acptemplates.tar
[ -d acptemplates ] && (cd acptemplates && tar cf ../acptemplates.tar *)
# templates.tar
[ -d templates ] && (cd templates && tar cf ../templates.tar *)

# Sammle Items
ITEMS="package.xml"
for f in files.tar acptemplates.tar templates.tar; do
  [ -f "$f" ] && ITEMS="$ITEMS $f"
done

# XML Config Files
for xml in objectType.xml page.xml userGroupOption.xml eventListener.xml \
           option.xml cronjob.xml box.xml menuItem.xml templateListener.xml \
           acpMenu.xml aclOption.xml; do
  [ -f "$xml" ] && ITEMS="$ITEMS $xml"
done

# Script PIPs
for php in install_*.php; do
  [ -f "$php" ] && ITEMS="$ITEMS $php"
done

# Language
if [ -d language ]; then
  for lang in language/*.xml; do
    [ -f "$lang" ] && ITEMS="$ITEMS $lang"
  done
fi

# Final tar
tar cf "$OUTPUT" $ITEMS

# Cleanup
rm -f files.tar acptemplates.tar templates.tar

echo "Done: ${OUTPUT}"

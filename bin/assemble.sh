#!/usr/bin/env bash
# Assembles an installable extension tree (shared code, languages and the SDK copied in place).
#   bin/assemble.sh 3 <dest>   # OpenCart 3.0.x: <dest>/upload/…  (zip as *.ocmod.zip)
#   bin/assemble.sh 4 <dest>   # OpenCart 4.0.2+: <dest>/{install.json,admin,catalog,system} (zip as varizet.ocmod.zip)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SDK="$("$ROOT/bin/sdk.sh")"
MAJOR="${1:?usage: assemble.sh 3|4 <dest>}"
DEST="${2:?usage: assemble.sh 3|4 <dest>}"
rm -rf "$DEST"
mkdir -p "$DEST"
case "$MAJOR" in
  3)
    cp -R "$ROOT/oc3/upload" "$DEST/upload"
    BASE="$DEST/upload"
    LIB="$BASE/system/library/varizet"
    for lang in en-gb fa-ir; do
      mkdir -p "$BASE/admin/language/$lang/extension/payment" "$BASE/catalog/language/$lang/extension/payment"
      cp "$ROOT/common/language/$lang/admin.php" "$BASE/admin/language/$lang/extension/payment/varizet.php"
      cp "$ROOT/common/language/$lang/catalog.php" "$BASE/catalog/language/$lang/extension/payment/varizet.php"
    done
    ;;
  4)
    cp -R "$ROOT/oc4/." "$DEST/"
    LIB="$DEST/system/library/varizet"
    for lang in en-gb fa-ir; do
      mkdir -p "$DEST/admin/language/$lang/payment" "$DEST/catalog/language/$lang/payment"
      cp "$ROOT/common/language/$lang/admin.php" "$DEST/admin/language/$lang/payment/varizet.php"
      cp "$ROOT/common/language/$lang/catalog.php" "$DEST/catalog/language/$lang/payment/varizet.php"
    done
    ;;
  *) echo "unknown OpenCart major $MAJOR" >&2; exit 1 ;;
esac
mkdir -p "$LIB"
cp -R "$ROOT/common/src" "$ROOT/common/autoload.php" "$LIB/"
"$SDK/bin/bundle.sh" "$LIB/lib/varizet-php" >/dev/null
find "$DEST" \( -name '.DS_Store' -o -name '.gitkeep' \) -delete

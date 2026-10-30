#!/usr/bin/env bash
# Builds both packages with the SDK bundled:
#   dist/varizet-opencart3-<v>.ocmod.zip   OpenCart 3.0.x: Extensions → Installer
#   dist/varizet-opencart4-<v>.zip         contains varizet.ocmod.zip (the name OpenCart 4 needs:
#                                          it becomes extension/varizet) and INSTALL-fa.txt
# Each zip gets a .sha256.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(sed -n "s/.*public const VERSION = '\(.*\)';/\1/p" "$ROOT/common/src/Settings.php")"
JSON_VERSION="$(sed -n 's/.*"version": *"\(.*\)".*/\1/p' "$ROOT/oc4/install.json")"
[ -n "$VERSION" ] || { echo "no VERSION in Settings.php" >&2; exit 1; }
[ "$VERSION" = "$JSON_VERSION" ] || { echo "version mismatch: Settings=$VERSION install.json=$JSON_VERSION" >&2; exit 1; }
grep -q "^## \[$VERSION\]" "$ROOT/CHANGELOG.md" || { echo "CHANGELOG.md has no [$VERSION] entry" >&2; exit 1; }

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
"$ROOT/bin/assemble.sh" 3 "$STAGE/oc3"
"$ROOT/bin/assemble.sh" 4 "$STAGE/oc4"

if command -v php >/dev/null 2>&1; then
  find "$STAGE" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
else
  docker run --rm -v "$STAGE":/p -w /p php:8.1-cli sh -c 'find . -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'
fi

mkdir -p "$ROOT/dist"
OC3="$ROOT/dist/varizet-opencart3-$VERSION.ocmod.zip"
OC4="$ROOT/dist/varizet-opencart4-$VERSION.zip"
rm -f "$OC3" "$OC4"
(cd "$STAGE/oc3" && zip -qr -X "$OC3" upload)
mkdir -p "$STAGE/oc4-outer"
(cd "$STAGE/oc4" && zip -qr -X "$STAGE/oc4-outer/varizet.ocmod.zip" .)
cp "$ROOT/INSTALL-fa.txt" "$STAGE/oc4-outer/"
(cd "$STAGE/oc4-outer" && zip -qr -X "$OC4" .)
for z in "$OC3" "$OC4"; do
  (cd "$ROOT/dist" && shasum -a 256 "$(basename "$z")" > "$(basename "$z").sha256")
  echo "built $z ($(unzip -l "$z" | tail -1 | awk '{print $2}') files) sha256 $(cut -d' ' -f1 "$z.sha256")"
done

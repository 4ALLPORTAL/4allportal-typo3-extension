#!/usr/bin/env bash
#
# Builds a TER-ready release ZIP of the extension.
#
# The archive is named <extension-key>_<version>.zip (e.g.
# fourallportal_typo3_extension_2.0.0.zip) with ext_emconf.php at the root
# level, as the TYPO3 Extension Repository requires. Extension key and version
# are read from composer.json; the version is checked against ext_emconf.php.
#
# Files are taken from a committed git ref (default HEAD) via `git archive`,
# which honours the export-ignore rules in .gitattributes - so dev-only paths
# (Build, Tests, .github, composer.lock, ...) never end up in the release.
#
# Usage:
#   Build/Scripts/create-ter-archive.sh [git-ref]
#
# Examples:
#   Build/Scripts/create-ter-archive.sh          # from current HEAD
#   Build/Scripts/create-ter-archive.sh 2.0.0    # from tag 2.0.0

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

REF="${1:-HEAD}"
DIST_DIR="Build/dist"

if ! git rev-parse --verify --quiet "$REF^{commit}" >/dev/null; then
  echo "error: '$REF' is not a valid git ref (tags in this repo use a 'v' prefix, e.g. v1.0.2)" >&2
  exit 1
fi

# read metadata from the archived ref, not the working tree, so name and
# contents always match the version being released
key=$(git show "$REF:composer.json" | php -r '$c = json_decode(stream_get_contents(STDIN), true); echo $c["extra"]["typo3/cms"]["extension-key"] ?? "";')
version=$(git show "$REF:composer.json" | php -r '$c = json_decode(stream_get_contents(STDIN), true); echo $c["version"] ?? "";')
emconfVersion=$(git show "$REF:ext_emconf.php" | php -r '$s = stream_get_contents(STDIN); echo preg_match("/[\x27\"]version[\x27\"]\s*=>\s*[\x27\"]([^\x27\"]+)/", $s, $m) ? $m[1] : "";')

if [ -z "$key" ]; then
  echo "error: extension-key not found in composer.json (extra.typo3/cms.extension-key)" >&2
  exit 1
fi

if [ -z "$version" ]; then
  echo "error: version not found in composer.json" >&2
  exit 1
fi

# TER requires the ext_emconf.php version to match the archive/version name
if [ "$version" != "$emconfVersion" ]; then
  echo "error: version mismatch - composer.json is $version, ext_emconf.php is $emconfVersion" >&2
  exit 1
fi

output="$DIST_DIR/${key}_${version}.zip"
mkdir -p "$DIST_DIR"
rm -f "$output"

git archive --format=zip --output="$output" "$REF"

echo "created $output ($(du -h "$output" | cut -f1)) from ref '$REF'"
echo "root entries:"
unzip -Z1 "$output" | sed 's#/.*##' | sort -u | sed 's/^/  /'

#!/usr/bin/env bash
#
# End-to-end smoke test for the 4ALLPORTAL file API against a running TYPO3.
#
# Exercises the full contract the connector relies on - authentication,
# bearer-token guarding, rate limiting, and the six file operations - which
# depend on FAL, the DI container and the middleware stack and therefore
# cannot be covered by plain unit tests.
#
# Usage:
#   Build/Scripts/api-smoke-test.sh <base-url> <fe-user> <fe-password> [storage-uid]
#
# Example:
#   Build/Scripts/api-smoke-test.sh http://localhost:8890 api 'ApiSecret123' 1
#
# Requires: curl, a fe_users record with the given credentials, and a
# writable file storage (default uid 1 = fileadmin).

set -uo pipefail

BASE="${1:?base url required, e.g. http://localhost:8890}"
USER="${2:?fe_user username required}"
PASS="${3:?fe_user password required}"
STORAGE="${4:-1}"

pass=0
fail=0

# check <label> <expected-status> <actual-status> [body-substring] [body]
check() {
  local label="$1" expected="$2" actual="$3" needle="${4:-}" body="${5:-}"
  if [ "$actual" != "$expected" ]; then
    printf '  FAIL  %-38s expected HTTP %s, got %s\n' "$label" "$expected" "$actual"
    fail=$((fail + 1))
    return
  fi
  if [ -n "$needle" ] && [[ "$body" != *"$needle"* ]]; then
    printf '  FAIL  %-38s missing %q in body\n' "$label" "$needle"
    fail=$((fail + 1))
    return
  fi
  printf '  ok    %-38s HTTP %s\n' "$label" "$actual"
  pass=$((pass + 1))
}

# req <method> <path> <auth-header> [curl-args...]  -> sets $STATUS and $BODY
req() {
  local method="$1" path="$2" auth="$3"; shift 3
  local out
  out=$(curl -s -w $'\n%{http_code}' -X "$method" "$BASE$path" \
    ${auth:+-H "Authorization: Bearer $auth"} "$@")
  STATUS="${out##*$'\n'}"
  BODY="${out%$'\n'*}"
}

echo "== auth =="
req POST /api/auth "" -H 'Content-Type: application/json' -d "{\"username\":\"$USER\",\"password\":\"$PASS\"}"
check "auth valid credentials" 200 "$STATUS" '"token"' "$BODY"
TOKEN=$(printf '%s' "$BODY" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')

req POST /api/auth "" -H 'Content-Type: application/json' -d "{\"username\":\"$USER\",\"password\":\"wrong\"}"
check "auth wrong password" 403 "$STATUS" 'Invalid credentials' "$BODY"

echo "== auth guard =="
req GET /api/files/1 ""
check "missing token rejected" 403 "$STATUS" 'Unauthorized' "$BODY"
req GET /api/files/1 "${TOKEN}x"
check "tampered token rejected" 403 "$STATUS" 'Unauthorized' "$BODY"

echo "== upload + file operations =="
TMP=$(mktemp)
echo "4ALLPORTAL smoke test" > "$TMP"
req POST /api/files "$TOKEN" -F "file=@$TMP" -F "targetPath=4ap/smoke" -F "fileName=smoke.txt" -F "storageUid=$STORAGE"
check "upload" 200 "$STATUS" '"identifier"' "$BODY"
FILE_UID=$(printf '%s' "$BODY" | sed -n 's/.*"uid":\([0-9]*\).*/\1/p')

req POST /api/files "$TOKEN" -F "file=@$TMP" -F "storageUid=$STORAGE"
check "upload without targetPath" 400 "$STATUS" 'targetPath is required' "$BODY"

req POST /api/files "$TOKEN" -F "targetPath=4ap/smoke"
check "upload without file" 400 "$STATUS" 'No file uploaded' "$BODY"

req POST /api/files "$TOKEN" -F "file=@$TMP" -F "targetPath=4ap/smoke" -F "storageUid=0"
check "upload storageUid=0 rejected" 400 "$STATUS" 'Invalid storageUid' "$BODY"

req GET "/api/files/$FILE_UID" "$TOKEN"
check "get file" 200 "$STATUS" '"identifier"' "$BODY"

req GET /api/files/99999999 "$TOKEN"
check "get missing file" 404 "$STATUS" 'File not found' "$BODY"

req POST "/api/files/$FILE_UID/rename" "$TOKEN" -H 'Content-Type: application/json' -d '{"newFileName":"renamed.txt"}'
check "rename" 200 "$STATUS" '"previousName"' "$BODY"

req POST "/api/files/$FILE_UID/rename" "$TOKEN" -H 'Content-Type: application/json' -d '{}'
check "rename without newFileName" 400 "$STATUS" 'newFileName is required' "$BODY"

req POST "/api/files/$FILE_UID/move" "$TOKEN" -H 'Content-Type: application/json' -d '{"targetPath":"4ap/moved"}'
check "move" 200 "$STATUS" '"previousPath"' "$BODY"

req PUT "/api/files/$FILE_UID" "$TOKEN" -H 'Content-Type: application/json' -d '{"title":"Smoke","copyright":"4AP"}'
check "update metadata" 200 "$STATUS" 'success' "$BODY"

req PUT "/api/files/$FILE_UID" "$TOKEN" -H 'Content-Type: application/json' -d '{}'
check "update metadata empty body" 400 "$STATUS" 'No metadata provided' "$BODY"

req DELETE "/api/files/$FILE_UID" "$TOKEN"
check "delete" 200 "$STATUS" 'success' "$BODY"

req DELETE "/api/files/$FILE_UID" "$TOKEN"
check "delete already deleted" 404 "$STATUS" 'File not found' "$BODY"

rm -f "$TMP"

echo "== rate limiting (20 attempts / 5 min per IP) =="
# a fresh auth reset the counter above; 20 failures are allowed, the 21st is blocked
last=""
for i in $(seq 1 21); do
  last=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/api/auth" \
    -H 'Content-Type: application/json' -d "{\"username\":\"$USER\",\"password\":\"wrong\"}")
done
check "auth blocked after limit" 429 "$last"

echo
echo "== $pass passed, $fail failed =="
[ "$fail" -eq 0 ]

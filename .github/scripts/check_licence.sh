#!/usr/bin/env bash
# Fail if the shipped licence text or any file's @license tag does not match the
# licence this plugin declares (GNU GPL v3 or later).
set -euo pipefail

expected='@license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later'
status=0

# The GPL text names the Affero licence in section 13, so check the title lines only.
if [ "$(sed -n '1p' LICENSE | xargs)" != 'GNU GENERAL PUBLIC LICENSE' ] \
    || [ "$(sed -n '2p' LICENSE | xargs)" != 'Version 3, 29 June 2007' ]; then
    echo "::error file=LICENSE::LICENSE must be the GNU GPL v3 text (title lines did not match)."
    status=1
fi

while IFS= read -r file; do
    if ! grep -qF " * $expected" "$file"; then
        echo "::error file=$file::Missing or different @license tag; expected '$expected'."
        status=1
    fi
done < <(git ls-files '*.php')

exit $status

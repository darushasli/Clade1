#!/bin/bash
set -uo pipefail

# Only needed in Claude Code on the web: the container is ephemeral, so any
# pip-installed CLI (like uxskill, which is not a vendorable .claude/skills/
# folder) must be reinstalled at the start of every fresh session.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

pip install --user --quiet uxskill 2>/dev/null \
  || echo "Warning: could not install uxskill (network or pip issue) - skipping" >&2

exit 0

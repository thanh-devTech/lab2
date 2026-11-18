#!/usr/bin/env bash
# Destroy the E2E stack. Volumes included -- the database is disposable by
# design and a surviving volume is how D153's warm-volume PII-strip hole opened.
#
# Called by the Makefile's `trap ... EXIT INT TERM`, because Playwright's
# `teardown:` project does NOT run on Ctrl-C (B-R11).
set -uo pipefail
cd "$(dirname "$0")"
docker compose down -v --remove-orphans

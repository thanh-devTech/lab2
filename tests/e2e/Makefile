# One-command entry for the LIS E2E suite (design §7.1, R11).
#
#   make e2e      bring the stack up in the D139 order, run the suite, tear down
#   make up       bring the stack up and leave it running (for --ui / --headed)
#   make test     run the suite against an already-running stack
#   make gate     run the safety gate on its own
#   make lint     copy-family counts + selector policy + tsc (no stack needed)
#   make down     destroy the stack and its volumes
#
# `e2e` installs `trap down.sh EXIT INT TERM` because Playwright's `teardown:`
# project does NOT run on Ctrl-C (B-R11), and a leaked stack keeps port 80.

SHELL := /usr/bin/env bash
ENV   := env
# R12/R18: genuinely outside /Users/andrei/work/lab, which is the webroot.
export E2E_STATE_DIR ?= $(shell echo "$${TMPDIR:-/tmp}")/lis-e2e-state
# One lead persona per POOL LAB (D262). The pool widened 8 -> 16 for persona
# wave 2; this list did not, so e2e_lead8..15 -- the tenant persona of every
# wave-2 spec -- had no storageState, and a hand-minted one dies at the next
# reset.sh because it is not in baseline.sql. Keep this list in step with
# sql/25-lab-pool.sql and SPEC_LAB in fixtures/tenant.ts.
PERSONAS := Administrat0r e2e_sec e2e_tech e2e_dir e2e_patient \
            e2e_lead0 e2e_lead1 e2e_lead2 e2e_lead3 \
            e2e_lead4 e2e_lead5 e2e_lead6 e2e_lead7 \
            e2e_lead8 e2e_lead9 e2e_lead10 e2e_lead11 \
            e2e_lead12 e2e_lead13 e2e_lead14 e2e_lead15 \
            e2e_billing e2e_billclin e2e_doctor e2e_collector \
            e2e_officemgr e2e_multisite e2e_pcrverify e2e_scientist

.PHONY: e2e up seed mint snapshot gate lint test down clean

## The whole thing, with a teardown that survives Ctrl-C.
e2e:
	@trap '$(ENV)/down.sh' EXIT INT TERM; \
	 $(MAKE) --no-print-directory lint up seed mint snapshot gate test

up:
	@t=$$SECONDS; $(ENV)/up.sh; echo "STAGE up.sh $$(($$SECONDS-t))s"

seed:
	@t=$$SECONDS; $(ENV)/seed.sh; echo "STAGE seed.sh $$(($$SECONDS-t))s"

# D139/D158: mint BEFORE the snapshot. reset.sh restores whatever
# wp_usermeta.session_tokens the baseline holds, so a snapshot taken before the
# mint logs every persona out on the first reset.
mint:
	@t=$$SECONDS; mkdir -p "$$E2E_STATE_DIR"; \
	 for u in $(PERSONAS); do $(ENV)/mint-session.sh "$$u" "$$E2E_STATE_DIR/$$u.json" >/dev/null; done; \
	 echo "STAGE mint $(words $(PERSONAS)) personas $$(($$SECONDS-t))s"

snapshot:
	@t=$$SECONDS; $(ENV)/reset.sh --snapshot; echo "STAGE snapshot $$(($$SECONDS-t))s"

gate:
	@t=$$SECONDS; $(ENV)/safety-gate.sh; echo "STAGE safety-gate $$(($$SECONDS-t))s"

# The STATIC gate. Lives here as well as in CI because CI is not always
# available (Actions has been down account-wide), and until now `npx tsc
# --noEmit` and the selector policy ran in NO local path at all.
lint:
	@t=$$SECONDS; $(ENV)/lint.sh; echo "STAGE lint $$(($$SECONDS-t))s"

test:
	@t=$$SECONDS; npx playwright test $(ARGS); echo "STAGE playwright $$(($$SECONDS-t))s"

down:
	@$(ENV)/down.sh

clean: down
	@rm -rf "$$E2E_STATE_DIR" test-results playwright-report

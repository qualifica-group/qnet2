---
description: Esegue una feature APPROVATA — teammate a ownership disgiunta contro il contratto congelato, ogni microtask chiuso dal verifier sui test reali, commit + HANDOFF a ogni stato verde.
argument-hint: docs/specs/001-<feature>.xml
allowed-tools: Read, Grep, Glob, Edit, Write, Bash, Task, Skill
---

Esegui la spec **$ARGUMENTS** (se vuoto, chiedi quale spec). Presuppone che la spec sia **già stata approvata** via `/plan-feature`. Se non esiste o non è approvata, fermati e dillo.

## 1. Carica il piano
- Leggi la spec `$ARGUMENTS`: `data_contract` (congelato), `acceptance_criteria`, `constraints`, e il piano a microtask (owner, dipendenze, lane, write surface).
- Conferma che il contratto è congelato. Se manca o è ambiguo, **fermati**: torna a `/plan-feature`, non improvvisare.

## 2. Crea l'agent team (peer-mesh) — non subagent a stella
- Se `CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS` è attivo: **crea un agent team** referenziando per nome **solo i ruoli che i microtask richiedono** (non un organico fisso). Core: `backend`, `frontend`, `ui-design`, `verifier`. Specialisti on-demand se il piano li prevede: `database` (data-heavy), `security` (feature sensibili), `tester-debug` (bug/E2E/flaky). I teammate condividono la **task list** (i microtask del piano) e si **messaggiano peer-to-peer** (`SendMessage`).
- Assegna a ogni teammate la sua **write surface disgiunta** (ownership di file). Due teammate non toccano mai lo stesso file.
- Se il team NON è disponibile (modello/piano non idoneo) oppure i task sono indipendenti col contratto già congelato: ripiega sui **subagent via Task** (stella, più economici) — dillo esplicitamente all'utente.

## 3. Esecuzione coordinata (lane + dipendenze)
- I teammate eseguono i microtask rispettando le **lane**: `parallelo` solo se NON condividono write surface; `sequenziale`/`gated` per il resto. Il contratto congelato è il punto di sincronizzazione: backend e frontend procedono in parallelo contro la stessa shape, messaggiandosi **solo i delta** (es. "campo rinominato, spec aggiornata").
- Ogni teammate: implementa + scrive i test (TDD), applica `rules/` del suo dominio + `engineering.md`. Gli hook girano in automatico (Pint/ESLint/typecheck/secret/config-protection/block-no-verify/code-guard): se un hook blocca, il teammate **corregge**, non aggira.

## 4. Verifica indipendente per ogni microtask
- Il teammate **`verifier`** **esegue davvero** la suite completa (`composer test` in parallelo, `npx vitest run`), `npx tsc -b --force --pretty false` (mai `tsc --noEmit`), e mappa gli `acceptance_criteria` 1:1 → PASS/FAIL con evidenza. Non scrive codice di produzione.
- Se **ROSSO**: **messaggia (`SendMessage`) il teammate owner** con file:riga e cosa fallisce; l'owner corregge. Non si procede al microtask dipendente finché non è verde.

## 5. Checkpoint a ogni stato verde
- Quando un microtask è verde (test eseguiti + AC soddisfatti + hook puliti): **git checkpoint** e **aggiorna `docs/HANDOFF.md`** (cosa fatto, contratto/endpoint, naming da rispettare, prossimo passo). L'hook `handoff-reminder.sh` te lo ricorda.

## 6. Chiusura
- Stop quando **tutti** gli `acceptance_criteria` sono verdi (verifier conferma), oppure quando sei bloccato: in tal caso riporta cosa manca, perché, e cosa serve.
- Riepilogo finale: AC → stato, file toccati per teammate, checkpoint fatti, eventuali thread aperti per `HANDOFF.md`.

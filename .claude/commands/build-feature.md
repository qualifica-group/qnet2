---
description: Esegue una feature APPROVATA — teammate a ownership disgiunta contro il contratto congelato, ogni microtask chiuso dal verifier sui test reali, commit + HANDOFF a ogni stato verde.
argument-hint: docs/specs/001-<feature>.xml
allowed-tools: Read, Grep, Glob, Edit, Write, Bash, Task, Skill
---

Esegui la spec **$ARGUMENTS** (se vuoto, chiedi quale spec). Presuppone che la spec sia **già stata approvata** via `/plan-feature`. Se non esiste o non è approvata, fermati e dillo.

## 1. Carica il piano
- Leggi la spec `$ARGUMENTS`: `data_contract` (congelato), `acceptance_criteria`, `constraints`, e il piano a microtask (owner, dipendenze, lane, write surface).
- Conferma che il contratto è congelato. Se manca o è ambiguo, **fermati**: torna a `/plan-feature`, non improvvisare.

## 2. Esecutori: subagent a stella per default, agent team solo se serve
- **Default: subagent via Task** (stella) referenziando per nome **solo i ruoli che i microtask richiedono** (non un organico fisso). Core: `backend`, `frontend`, `ui-design`, `verifier`. Specialisti on-demand se il piano li prevede: `database` (data-heavy), `security` (feature sensibili), `tester-debug` (bug/E2E/flaky). Ogni sessione in piu' fa girare i propri hook e test sulla stessa macchina: meno sessioni contemporanee = PC usabile.
- **Agent team (peer-mesh)** solo se `CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS` è attivo **e** il piano ha vero parallelismo con coordinamento continuo tra owner (delta di contratto frequenti): i teammate condividono la **task list** e si **messaggiano peer-to-peer** (`SendMessage`). Dichiara all'utente quale modalità usi e perché.
- Assegna a ogni esecutore la sua **write surface disgiunta** (ownership di file). Due esecutori non toccano mai lo stesso file.

## 3. Esecuzione coordinata (lane + dipendenze)
- I teammate eseguono i microtask rispettando le **lane**: `parallelo` solo se NON condividono write surface; `sequenziale`/`gated` per il resto. Il contratto congelato è il punto di sincronizzazione: backend e frontend procedono in parallelo contro la stessa shape, messaggiandosi **solo i delta** (es. "campo rinominato, spec aggiornata").
- Ogni teammate: implementa + scrive i test (TDD), applica `rules/` del suo dominio + `engineering.md`. Gli hook girano in automatico (Pint/ESLint/typecheck/secret/config-protection/block-no-verify/code-guard): se un hook blocca, il teammate **corregge**, non aggira.

## 4. Verifica indipendente per ogni microtask
- Il **`verifier`** **esegue davvero i test mirati** del microtask (Pest sulle cartelle toccate, `vitest run src/features/<area> --maxWorkers=4`, `npx tsc -b --force --pretty false` se tocca il frontend; mai `tsc --noEmit`) e mappa gli `acceptance_criteria` del microtask 1:1 → PASS/FAIL con evidenza. Non scrive codice di produzione. Comandi esatti in `.claude/agents/verifier.md`.
- **Mai la suite completa per microtask** e mai due verifiche pesanti in contemporanea: la suite completa si esegue una volta sola al §6.
- Se **ROSSO**: **messaggia (`SendMessage`) il teammate owner** con file:riga e cosa fallisce; l'owner corregge. Non si procede al microtask dipendente finché non è verde.

## 5. Checkpoint a ogni stato verde
- Quando un microtask è verde (test eseguiti + AC soddisfatti + hook puliti): **git checkpoint** e **aggiorna `docs/HANDOFF.md`** (cosa fatto, contratto/endpoint, naming da rispettare, prossimo passo). L'hook `handoff-reminder.sh` te lo ricorda.

## 6. Chiusura
- **Gate finale:** il `verifier` esegue **una volta** la suite completa, backend poi frontend in sequenza (`nice -n 10 composer test -- --processes=4`, poi `nice -n 10 npx vitest run --maxWorkers=4` + `npx tsc -b --force --pretty false`). È il cancello prima del checkpoint finale: un rosso qui riapre il microtask dell'owner.
- Stop quando **tutti** gli `acceptance_criteria` sono verdi (verifier conferma), oppure quando sei bloccato: in tal caso riporta cosa manca, perché, e cosa serve.
- Riepilogo finale: AC → stato, file toccati per teammate, checkpoint fatti, eventuali thread aperti per `HANDOFF.md`.

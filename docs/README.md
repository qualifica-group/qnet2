# Docs

Output di documentazione del progetto. Confine documentale tra backend e frontend.

| Cartella | Contenuto | Template di origine | Owner |
|----------|-----------|---------------------|-------|
| [`adr/`](adr/) | Architecture Decision Records (`NNNN-titolo-decisione.md`) | [`templates/architecture-decision.md`](../templates/architecture-decision.md) | Architect |
| [`specs/`](specs/) | Feature specification (`NNNN-nome-feature.md`) | [`templates/feature-spec.md`](../templates/feature-spec.md) | Product Manager |
| [`qa/`](qa/) | QA report (`NNNN-nome-feature.md`) | [`templates/qa-report.md`](../templates/qa-report.md) | QA |
| [`releases/`](releases/) | Launch checklist (`NNNN-versione.md`) | [`templates/launch-checklist.md`](../templates/launch-checklist.md) | DevOps |
| [`api/`](api/) | Documentazione del contratto API (confine backend/frontend) | — | Backend |

## Contratti API recenti

- [`api/0006-commission-configurator-and-quote-integration.md`](api/0006-commission-configurator-and-quote-integration.md)
  — Configuratore Commissioni, risoluzione delle regole, snapshot persistenti
  sui Preventivi, permessi e rollout.

## Regola

I template in `templates/` sono i master vuoti. Per ogni nuovo documento si **copia** il
template nella cartella corrispondente con un ID progressivo e si compila ogni sezione.

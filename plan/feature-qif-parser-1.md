---
goal: Implement a SOLID-compliant QIF Parser for FrontAccounting integration
version: 1.0
date_created: 2026-03-19
last_updated: 2026-03-19
owner: GitHub Copilot
status: 'In progress'
tags: [feature, architecture, php, finance]
---

# Introduction

![Status: In progress](https://img.shields.io/badge/status-In%20progress-yellow)

This plan outlines the systematic implementation of the `ksf_qifparser` module. The goal is to produce a parser that converts legacy QIF files into unified `bi_statements` and `bi_transactions` entities for the `ksf_bank_import` system, handling complex splits and date ambiguities.

## 1. Requirements & Constraints

- **REQ-001**: Must support Intuit 1997/2006 QIF specifications for Bank, CCard, and Investment types.
- **REQ-002**: Must generate deterministic `FITID` via `SHA256(Date|Bank|Account|Payee|Amount|Seq)`.
- **REQ-003**: Must flatten multi-line Split transactions into individual `bi_transactions` rows.
- **REQ-004**: Must handle `MM/DD'YY` vs `DD/MM'YY` date formats via injected configuration.
- **REQ-005**: 100% test coverage using PHPUnit.
- **CON-001**: PHP 7.3+ compatibility.
- **PAT-001**: Use SRP Parsers for each QIF code (Polymorphism over Conditionals).
- **SEC-001**: All raw QIF data must be scrubbed using the `sanitize-qif.php` utility before inclusion in the repository.

## 2. Implementation Steps

### Phase 1: Foundation & Entities (Completed)
- **TASK-101**: Setup PSR-4 structure and `composer.json`.
- **TASK-102**: Implement `QifTransaction`, `QifSplit`, and `Payee` entities.
- **TASK-103**: Create `AGENTS.md` and `PRD.md` documentation.

### Phase 2: Sanitization & Fixtures
- **TASK-201**: Run `php scripts/sanitize-qif.php QIFs/ tests/fixtures/` to generate safe test data.
- **TASK-202**: Verify anonymization quality (no real account numbers or PII in `tests/fixtures/`).

### Phase 3: SRP Item Parsers (Completed)
- **TASK-301**: Implement `ParserInterface`.
- **TASK-302**: Implement `DateParser` with 'MDY'/'DMY' toggle.
- **TASK-303**: Implement `AmountParser` with comma stripping.
- **TASK-304**: Implement `PayeeParser` for multiline addresses.
- **TASK-305**: Implement `SplitParser` for `S`, `E`, `$` tags.

### Phase 4: Core Orchestration
- **TASK-401**: Create `src/Ksfraser/QifParser/QifParser.php` to manage the file reading loop.
- **TASK-402**: Implement `!Account` and `!Type` header detection in `QifParser`.
- **TASK-403**: Integrate `FitidService` into the parsing loop.
- **TASK-404**: Integrate `StatementFlattener` to produce the final payload.

### Phase 5: Testing & Integration
- **TASK-501**: Develop `tests/ParserTest.php` covering basic Bank/CCard imports.
- **TASK-502**: Develop `tests/SplitTest.php` verifying flattened row counts for split transactions.
- **TASK-503**: Generate `Project Docs/qa-plan.md` using the Breakdown-Test skill.
- **TASK-504**: Verify parity with `ksf_ofxparser` output structure.

### Phase 6: Investment Support (Future)
- **TASK-601**: Coordinate mapping of QIF "Actions" (Buy, Sell, Div) with `bank_import` DB schema.
- **TASK-602**: Implement `InvestmentParser` for `Y`, `I`, `Q` tags.

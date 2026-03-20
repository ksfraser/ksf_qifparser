# Product Requirements Document (PRD) - ksf_qifparser

## 1. Executive Summary

- **Problem Statement**: Financial institutions often provide data in the legacy Quicken Interchange Format (QIF), which is inherently ambiguous (dates), lacks consistent metadata (Bank IDs, Account Numbers), and uses non-standardized transaction "Actions" for investments.
- **Proposed Solution**: A standardized, PHP 7.3+ compliant QIF Parser built on SOLID and SRP principles. It will de-flatten complex QIF records (splits, addresses) and produce `bi_statements` and `bi_transactions` entities compatible with the `ksf_bank_import` module for FrontAccounting (FA).
- **Success Criteria**:
    - 100% test coverage for Intuit 1997/2006 spec item codes (`D`, `T`, `P`, `N`, `L`, `A`, `S`, `E`, `$`, `^`).
    - Zero duplicate imports via deterministic, sequence-aware `FITID` generation.
    - Verified mapping of QIF Splits to multiple "split-debit/credit" rows in FA staging.
    - Automated sanitization of raw QIF files into test fixtures.

## 2. User Experience & Functionality

- **User Personas**:
    - **FA Administrator**: Needs to import historical or non-OFX bank data into FrontAccounting.
    - **Developer**: Needs an extensible, PSR-compliant parser that handles edge cases like date ambiguity and investment actions.

- **User Stories**:
    - **Story 1**: As an admin, I want to import a QIF file so that my bank reconciliation in FrontAccounting reflects my actual statement.
    - **Story 2**: As an admin, I want to map "Buy" or "Div" actions to specific FA transaction types so that my investment data is categorized correctly.
    - **Story 3**: As a developer, I want to provide external Bank/Account info to the parser since QIF files do not contain this metadata.

- **Acceptance Criteria**:
    - **AC 1**: Parser must handle `MM/DD'YY` and `DD/MM'YY` date formats via configuration.
    - **AC 2**: Every QIF transaction with splits must generate a Summary row AND child Split rows in the output.
    - **AC 3**: Addresses in QIF (`A` tag) must be captured as structured data (matching `ksf_ofxparser`'s Payee entity).
    - **AC 4**: Duplicate records in the same file must be uniquely identified using a sequence-based `FITID`.

- **Non-Goals**:
    - Direct database insertion (The parser only produces entities; `ksf_bank_import` handles the DB).
    - Direct UI for mapping (The parser provides the data structure; `ksf_bank_import` or a separate UI module provides the screens).

## 3. Technical Specifications

- **Architecture Overview**:
    - **Layered Design**: Parser implements a shared `ParserInterface`.
    - **SRP Elements**: Individual parser classes for each QIF code (`D`, `T`, `A`, etc.) following the "Polymorphism over Conditionals" pattern.
    - **Entity Mapping**: Maps raw QIF tags into `QifStatement` and `QifTransaction` DTOs.

- **Integration Points**:
    - **ksf_bank_import**: Consumes the produced `bi_*` compliant entities.
    - **0_bi_config**: Reads Action-to-Type mappings from the existing configuration table.

- **Security & Privacy**:
    - **Anonymization Engine**: A dedicated utility (and `.gitignore` for `QIFs/`) to scrub real names, account numbers, and addresses from raw samples and store them as `tests/fixtures/`.
    - **Sanitization**: All inputs are sanitized before entity population.

## 4. Risks & Roadmap

- **Phased Rollout**:
    - **Phase 1 (MVP)**: Basic Bank/CCard QIF parsing with flat transaction support and FITID generation.
    - **Phase 2**: Full Split transaction support (Summary + child rows) and Address parsing.
    - **Phase 3**: Investment Account Mapping (separate staging architecture coordinated with `bank_import`).

- **Technical Risks**:
    - **Date Ambiguity**: Incorrectly guessing `MM/DD` vs `DD/MM` could corrupt historical data. (Mitigation: Force explicit configuration if detection fails).
    - **Investment Complexity**: QIF investment "Actions" are complex; a "separate" architecture is required to avoid breaking the flat bank model. (Mitigation: Phase 3 coordinated development).

## 5. Evaluation Strategy
- **Functional Testing**: 50+ real-world QIF samples anonymized and verified for output parity with `ksf_ofxparser`.
- **Integrity Benchmarking**: Ensure `FITID` remains stable across identical file imports.


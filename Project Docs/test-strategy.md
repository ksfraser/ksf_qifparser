# Test Strategy - ksf_qifparser

## 1. Test Strategy Overview

- **Testing Scope**: QIF Parser core (`QifParser.php`), SRP item parsers (`DateParser`, `AmountParser`, etc.), and conversion services (`FitidService`, `StatementFlattener`).
- **Quality Objectives**:
    - **Functional**: 100% parity with Intuit 1997/2006 spec.
    - **Stability**: `FITID` remains stable across identical file imports.
    - **Maintainability**: Unified DTO structure matching `ksf_ofxparser`.
- **Risk Assessment**:
    - **Risk 1**: Date ambiguity (Mitigation: Configuration-driven MDY/DMY).
    - **Risk 2**: Floating point precision (Mitigation: BCMath or precision-aware comparison logic).
- **Test Approach**: TDD (Test-Driven Development) using PHPUnit 9.

## 2. ISTQB Framework Implementation

### Test Design Techniques Selection

- **Equivalence Partitioning**:
    - Partition dates into `MM/DD'YY`, `DD/MM'YY`, and `YYYY-MM-DD`.
    - Partition amounts into positive, negative, and zero.
- **Boundary Value Analysis**:
    - Test year transitions (99 to 00).
    - Test split transactions where sum(splits) exactly equals total.
    - Test split transactions where sum(splits) does NOT equal total (Validation Error).
- **Decision Table Testing**:
    - Create a map for QIF "Actions" (Buy, Sell, Div, etc.) and verify they map to correct FrontAccounting transaction codes.

### Test Types Coverage Matrix

| Test Type | Objective | Tools |
| :--- | :--- | :--- |
| **Functional** | Parse QIF tags (D, T, P, etc.) into Entities | PHPUnit |
| **Structural** | 100% Code Coverage | Xdebug/PHPUnit |
| **Change-Related** | Regression testing with 50+ anonymized fixtures | PHPUnit |
| **Security** | Verify `sanitize-qif.php` scrubs all PII | Manual Review |

## 3. ISO 25010 Quality Characteristics Assessment

- **Functional Suitability**: Verify every line of a standard QIF file is accounted for.
- **Maintainability**: Ensure no `switch` statements exist for QIF tag parsing (use SRP classes).
- **Compatibility**: Ensure output matches the `bi_transactions` schema required by `ksf_bank_import`.

## 4. Test Environment and Data Strategy

- **Test Environment**: PHP 7.3 - 8.x, PHPUnit 9.
- **Test Data Management**:
    - **Fixtures**: Real-world QIF samples scrubbed via `scripts/sanitize-qif.php`.
    - **Builders**: Programmatic creation of `QifTransaction` to test edge cases without files.
- **CI/CD Integration**: Tests run on every commit; coverage reports generated.

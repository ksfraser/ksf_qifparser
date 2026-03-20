# Requirements Traceability Matrix (RTM) - ksf_qifparser

## 1. Introduction
The Requirements Traceability Matrix (RTM) maps Business Requirements (BR), Functional Requirements (FR), and Non-Functional Requirements (NFR) to their corresponding technical artifacts (classes, methods, files) and test cases.

## 2. Requirements Index

### 2.1 Business Requirements (BR)
| ID | Requirement Name | Description | Source |
| :--- | :--- | :--- | :--- |
| BR-001 | QIF Import Support | Enable importing financial data from QIF files into FrontAccounting. | PRD |
| BR-002 | ksf_bank_import Parity | Ensure the parser structure identifies with ksf_ofxparser for seamless integration. | PRD |
| BR-003 | Investment Support | Support brokerage/investment transactions (stock, bond, mutual fund buys/sells). | FRD |
| BR-004 | External Meta-Data injection | Allow passing Account#, Routing, and Currency externally as QIF lacks these. | PRD |

### 2.2 Functional Requirements (FR)
| ID | Requirement Name | Description | Source | Implementation Artifacts | Test Cases |
| :--- | :--- | :--- | :--- | :--- | :--- |
| FR-2.1.1 | Parser Interface | Implement standardized parser interface for `ksf_bank_import`. | FRD [2.1] | | |
| FR-2.1.2 | bi_statements Mapping | Produce `bi_statements` class structure for FA. | FRD [2.1] | | |
| FR-2.1.3 | bi_transactions Mapping | Produce `bi_transactions` class structure for FA. | FRD [2.1] | | |
| FR-2.1.4 | Split Transaction Parsing | Correctly parse `S`, `E`, and `$` tags into child transactions. | FRD [2.1] | | |
| FR-2.1.5 | Investment Header Support | Handle `!Type:Invst` and associated tags (`N`, `Y`, `I`, `Q`). | FRD [2.1] | | |
| FR-3.1 | Date Format Ambiguity | Handle `MM/DD'YY` and `DD/MM'YY` variations via config/param. | FRD [3] | | |
| FR-3.2 | Record Separator | Correctly identify the `^` tag as the end of a transaction record. | FRD [3] | | |

### 2.3 Non-Functional Requirements (NFR)
| ID | Requirement Name | Description | Source | Implementation Artifacts | Test Cases |
| :--- | :--- | :--- | :--- | :--- | :--- |
| NFR-1.1 | PHP 7.3+ Compatibility | Code must run on PHP 7.3 or higher. | AGENTS | | |
| NFR-2.1 | 100% Code Coverage | All classes and methods must have unit tests. | AGENTS | | |
| NFR-3.1 | Custom Exception Hierarchy | Use specific exception classes for all error states. | AGENTS | | |
| NFR-4.1 | PSR-4/12 Compliance | Follow standard PHP autoloading and styling. | AGENTS | | |

## 3. Implementation Status Summary
(To be updated as development progresses)
- **Total Requirements**: 14
- **Implemented**: 0
- **Verified (Tested)**: 0

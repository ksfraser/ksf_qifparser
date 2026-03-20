# Functional Requirements Document (FRD) - ksf_qifparser

## 1. Scope
This document defines the functional requirements for the `ksf_qifparser` module, specific to its implementation and architectural goals.

## 2. Architectural & Implementation Requirements

### 2.1 Interface & API Design
- **Parser Interface**: Implement a standardized parser interface consistent with `ksf_ofxparser` to ensure compatibility with `ksf_bank_import`.
- **ksf_bank_import Compatibility**: The parser must specifically produce `bi_statements` and `bi_transactions` structures required for FrontAccounting (FA) integration.
- **External Bank Info Handling**: The Parser class must support receiving Bank ID, Account Number, Routing, and Currency as method parameters, as these are omitted in the QIF spec.
- **Split Transaction Support**: Correctly parse the `S`, `E`, and `$` tags to build child transaction objects for the `bi_transactions` structure.
- **Investment Accounting**: Handle the `!Type:Invst` header and its associated tags (`N`, `Y`, `I`, `Q`) to allow importing brokerage transactions.
- **Entity Loading**: Parser must return a `Statement` entity containing `Transaction` entities that map directly to the `bi_*` expected format.
- **REST/SOAP Ready**: The internal API must be designed to be wrapable by a service layer for future remote access.

## 3. QIF Specification Reference (Intuit 1997/2006)
- **Header Codes** (`!Type`): `Bank`, `Cash`, `CCard`, `Invst`, `Oth A`, `Oth L`.
- **Item Codes**:
    - `D`: Date (Handles `MM/DD'YY` and `DD/MM'YY` variations).
    - `T`: Amount (Positive for deposits, negative for payments).
    - `P`: Payee (Limited to 40-200 chars depending on source).
    - `N`: Number (Check number or Action for Investment).
    - `L`: Category (Format: `Category:Subcategory/Class` or `[Account]`).
    - `A`: Address (Optional, multiple lines permitted).
    - `S`: Split Category (Child transaction category).
    - `E`: Split Memo (Child transaction description).
    - `$`: Split Amount (Child transaction value).
    - `^`: Record separator (Mandatory at end of each transaction).
- **Encoding**: Must support ASCII/ANSI format (UTF-8 should be downgraded if possible).

## 4. Implementation Strategies (Refined)

### 4.1 Split Transaction Handling (Parent-Child)
The QIF parser will detect splits (`S`, `E`, `$`) and implement the following mapping strategy to support the updated `ksf_bank_import` requirements:
- **Summary Lines**: Create a primary debit/credit line for the full transaction amount (`T`).
- **Split Lines**: Create additional "split-debit" and "split-credit" lines for each individual split component (`$`).
- **Flattening with Relationship**: Each line (summary and splits) will be a separate row in `bi_transactions`.
- **Reference Integrity**: The `fitid` for split lines will be suffixed with the sequence (e.g., `-seq1`, `-seq2`) to maintain a relationship with the summary line in the database.
- **Total Verification**: The parser must verify that the sum of split amounts (`$`) equals the total transaction amount (`T`) before finalizing.

### 4.2 Action to Type Mapping (Configurable)
To handle QIF Investment "Actions" (Buy, Sell, Div) vs FA "Types" (DEB, CRD):
- **Config Storage**: Utilize the `0_bi_config` key-value pattern from `ksf_bank_import`.
- **Mapping Logic**: Provide a UI-driven mapping screen (to be implemented in `ksf_bank_import` or the parser's config) where users can map specific QIF Actions to FA transaction types.
- **Default Fallback**: If no mapping exists, default to `DEB` for negative amounts and `CRD` for positive amounts.

### 4.3 Unique FITID Generation (Sequence-Aware)
To handle identical transactions on the same day (e.g., multiple identical purchases):
- **Strategy**: `SHA256( Date + AccountNumber + Payee + Amount + FileSequence )`.
- **Sequence**: A counter within the same import file for identical records to ensure uniqueness.
- **Splits**: Split lines will append `-seqN` to the generated FITID.
- **Constraint**: This ensures idempotency while allowing legitimate identical transactions.

## 5. Database & Schema (Updated)
- **Migration Scripts**: Provide a migration to add `fitid` (varchar 64) and potentially `parent_id` (int 11) to the staging tables if not already present.

## 6. Documentation Standards
- **UML Mapping**: PHPDoc blocks must include UML snippets specifically mapping the internal message flow of this parser.
- **Dual Doc Maintenance**: Architectural diagrams in `Project Docs/` must be updated alongside class modifications.

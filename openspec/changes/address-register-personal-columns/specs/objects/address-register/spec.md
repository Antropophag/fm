## ADDED Requirements

### Requirement: Personal address register
The system SHALL provide the authenticated read-only address register and durable actor-bound column configuration defined by ADDRESS-REGISTER-001 A1–A8.

#### Scenario: Settings follow the account
- **WHEN** a permitted user saves their column order and widths and opens another browser
- **THEN** the saved view SHALL load for that user without changing another user or object facts.

#### Scenario: Concurrent edit
- **WHEN** a stale revision is submitted
- **THEN** the system SHALL reject it with409 and preserve the current settings.

# Maintenance policy

## Stable names over roadmap phase names

Historical phase-prefixed function names are not renamed in a big-bang migration. When a module is changed for a functional, security, performance or architecture reason, new internal functions should use stable domain-oriented names. Existing phase-prefixed APIs may remain until their module is already being changed; compatibility wrappers are only added when an actual caller requires them.

## Shared validation only for shared invariants

Upload code is not centralized merely to reduce duplication. Shared helpers are introduced when branding and sponsor uploads require the same security invariant. Caller-specific transaction boundaries, naming rules and functional limits remain local to the caller.

This policy turns the findings from #228 and #230 into an ongoing engineering rule without creating high-risk repository-wide renames or abstraction-only rewrites.

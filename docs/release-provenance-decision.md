# Release provenance decision

Issue #229 was reviewed as defense-in-depth, not as a demonstrated vulnerability.

The current release trust chain already requires protected-main governance, merged-PR lineage, successful required checks, a root-owned host wrapper that accepts only the current main SHA, immutable release verification, health validation, and controlled rollback/recovery.

An additional signed tag or attestation would only add meaningful independence if the signing identity and key lifecycle were separated from the GitHub/deploy trust domain and independently verified on the host. Introducing that infrastructure without a concrete threat model would add key-management and recovery complexity while preserving most of the same upstream trust assumptions.

Decision:

- do not introduce an additional signing system solely for defense-in-depth at this stage;
- keep the existing PR-lineage, immutable-manifest and host-side validation chain mandatory;
- keep rollback restricted to the existing validated `previous` release contract; never expose arbitrary historical SHA activation;
- add a dedicated rollback workflow only when there is a concrete operator need and it can call the existing constrained rollback path without expanding privilege.

Reopen the topic if the trust model changes, releases are distributed outside the current controlled VPS path, or an independent signing authority is introduced.

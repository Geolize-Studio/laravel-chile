# Release readiness

This checklist defines the publication procedure for the `0.1` release line. Executed checks are recorded in [verification evidence](verification.md). Each release must complete the applicable checks below.

## Runtime and contracts

- Implement only the scope advertised for the release; label experimental and unavailable capabilities explicitly.
- Finalize parsing grammars, error semantics, null handling, serialization, money ranges, and rounding policies.
- Execute targeted domain tests, negative fixtures, relevant mutation checks, static analysis, and formatting.
- Test each advertised PHP/Laravel combination with minimum and current supported dependencies.
- Verify a clean production installation without development dependencies hiding missing extensions or runtime requirements.
- Execute every quickstart example from the packaged artifact in a disposable consumer application.

## Territorial data

- Review the governing sources, effective dates, accepted snapshot, parser version, and reuse terms.
- Validate codes, hierarchy, official names, and record-level changes.
- Confirm observed-source metadata is not accidentally treated as the accepted runtime dataset.
- Run source-monitor tests and an end-to-end draft PR exercise in the intended repository before relying on scheduled maintenance.
- Check schedule health and document which sources are actually monitored.

## Documentation and agents

- Keep implemented/released status accurate throughout the README, domain docs, guideline, and skill.
- Add actual API signatures, error types, installation commands, and links to executable examples.
- Keep English prose and preserve correct Chilean terminology and original source values.
- Validate the complete bundled skill, then test Boost install/update and the chosen direct Codex installation method in fresh consumers.
- Test an agent's ability to explain limitations and handle invalid input, not only produce a successful example.
- Verify all documentation links and keep package metadata aligned with shipped capabilities.
- Keep the header's broad description free of vendor branding and module lists.

## Public release

- Choose and record the code license; handle upstream data and reference-image terms separately.
- Include security reporting instructions, contribution guidance, changelog, supported versions, and upgrade notes.
- Verify the contents of the distribution artifact, including docs, skill resources, and necessary data.
- Publish immutable version tags and verify the resulting Composer metadata and installation.
- Configure public repository topics, actual support/documentation links, and source-monitor permissions.
- Measure discovery and integration separately using the [agent-adoption checks](discoverability.md#release-checklist-and-evaluation).

The local 12-case runtime matrix, six-case consumer matrix, and final CUT/hierarchy review are complete. The public repository and private security reporting are configured, and all 18 GitHub-hosted compatibility jobs passed after the DX additions. A synthetic draft-PR permission exercise passed and cleaned itself up; unchanged official-source observations also passed. Before publishing a tag, verify checks on its exact commit. After Packagist indexes it, verify a clean installation of the published artifact and record the result in the GitHub release notes. A real changed-source proposal remains subject to human review.

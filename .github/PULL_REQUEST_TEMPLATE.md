## What does this change?

<!-- One or two sentences. Link the issue it closes. -->

Closes #

## How was it tested?

<!-- Steps you actually performed, and the WordPress / PHP / Divi versions used. -->

## Checklist

- [ ] PHPCS, PHPStan, PHPUnit and Plugin Check pass locally
- [ ] All new output is escaped and all new input is sanitised
- [ ] State-changing requests check a capability and verify a nonce
- [ ] All new user-facing strings use the `course-schedule-connector` text domain
- [ ] `CHANGELOG.md` updated under `## [Unreleased]`
- [ ] Documentation in `docs/` updated if behaviour changed
- [ ] No new outbound request to any host other than the configured iSport System installation

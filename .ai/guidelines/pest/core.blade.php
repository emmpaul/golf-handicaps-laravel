@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
@scoped(['tests/**'])
# Pest

- This project uses Pest. Create tests with `{{ $assist->artisanCommand('make:test --pest {name}') }}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run tests through the Makefile only. Run the narrowest set that covers the change: `make test-backend FILES=tests/Feature/SomeTest.php` or `make test-backend FILTER=testName`.
- Rerun a test after each change to it.
- Before declaring work done, run `make check` yourself. When a step fails, read `reports/check/<step>.log`.
@endscoped

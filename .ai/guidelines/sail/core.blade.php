@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. The Makefile is the command interface: run `make help` and use its target when one exists (`make up`, `make down`, `make test`, `make lint`, `make check`, `make build`, `make dev`).
- For a command without a target, go through Sail: prefix PHP, Artisan, Composer, and Node commands with `{{ $assist->sailBinaryPath() }}`. Examples:
    - Run Artisan Commands: `{{ $assist->artisanCommand('migrate') }}`
    - Install Composer packages: `{{ $assist->composerCommand('install') }}`
    - Execute PHP scripts: `{{ $assist->sailBinaryPath() }} php [script]`
- View all available Sail commands by running `{{ $assist->sailBinaryPath() }}` without arguments.

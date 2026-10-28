.DEFAULT_GOAL := help
SHELL := /bin/bash
SAIL := ./vendor/bin/sail

ifeq ($(CI),true)
RUN :=
else
RUN := $(SAIL)
endif

PHP := $(if $(RUN),$(RUN) php,php)
ARTISAN := $(if $(RUN),$(RUN) artisan,php artisan)
COMPOSER := $(if $(RUN),$(RUN) composer,composer)
PNPM := $(if $(RUN),$(RUN) pnpm,pnpm)
# Pas d'image laravelsail/php85-composer : l'image 8.4 installe vendor avec --ignore-platform-reqs.
COMPOSER_BOOTSTRAP_IMAGE := laravelsail/php84-composer:latest

.PHONY: help setup passcode-check up down dev dev-stop browser build migrate openspec \
	lint format analyse duplicates arch-test test-backend test-frontend test update-shards check \
	commitlint changed-code wayfinder

help: ## Affiche cette aide
	@echo "Usage : make <cible>"
	@awk 'BEGIN {FS = ":.*## "} \
		/^##@ / {printf "\n%s\n", substr($$0, 5)} \
		/^[a-z-]+:.*## / {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

##@ Environnement

setup: ## Installe un clone neuf : .env, dépendances, clé, assets, consignes d'agent, migrations
	@if [ ! -f .env ]; then cp .env.example .env; echo ".env créé depuis .env.example"; fi
	@if [ ! -d vendor ]; then \
		docker run --rm -u "$$(id -u):$$(id -g)" -v "$$(pwd):/var/www/html" -w /var/www/html \
			$(COMPOSER_BOOTSTRAP_IMAGE) composer install --ignore-platform-reqs --no-interaction; fi
	$(MAKE) --no-print-directory up
	$(COMPOSER) install --no-interaction
	@if grep -qE '^APP_KEY=$$' .env; then $(ARTISAN) key:generate --ansi; fi
	$(PNPM) install --frozen-lockfile
	$(MAKE) --no-print-directory build
	$(ARTISAN) boost:update --ansi
	$(ARTISAN) migrate --force --ansi
	@$(MAKE) --no-print-directory passcode-check

passcode-check: ## Interne : signale un APP_PASSCODE vide dans .env, sans écrire de valeur
	@if ! grep -qsE "^APP_PASSCODE=['\"]?[^'\"[:space:]]" .env; then \
		echo "Attention : APP_PASSCODE est vide dans .env ; toute requête HTTP répondra 500 tant qu'il n'a pas de valeur." >&2; fi

up: ## Démarre les conteneurs et attend qu'ils soient sains
	$(SAIL) up -d --wait

down: ## Arrête les conteneurs (données conservées)
	$(SAIL) down

migrate: ## Applique les migrations en attente, sans rien effacer
	$(ARTISAN) migrate --ansi

openspec: ## Lance la CLI OpenSpec épinglée ; ARGS="list", ARGS="validate --all"…
	$(PNPM) exec openspec $(ARGS)

##@ Développement

VITE_PROCESS := node_modules/.*(vp|vite\.js) dev
CDP_PORT ?= 9333
CHROME ?=
CHROME_CANDIDATES := "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" google-chrome chromium chromium-browser

dev: ## Lance Vite avec rechargement à chaud dans le conteneur
	$(PNPM) run dev

dev-stop: ## Arrête Vite, supprime public/hot et ferme le navigateur de make browser
	@$(SAIL) exec laravel.test pkill -f '$(VITE_PROCESS)' || [ $$? -eq 1 ]
	@rm -f public/hot
	@if [ -f reports/browser/chrome.pid ]; then \
		pid="$$(cat reports/browser/chrome.pid)"; \
		if [ -n "$$pid" ] && ps -p "$$pid" -o comm= 2>/dev/null | grep -qiE 'chrom'; then \
			kill "$$pid" && echo "Navigateur $$pid arrêté"; fi; \
		rm -f reports/browser/chrome.pid; fi

browser: ## Lance un Chrome headless jetable sur l'hôte ; affiche BU_CDP_URL (CDP_PORT=9333, CHROME=binaire)
	@url="http://127.0.0.1:$(CDP_PORT)"; \
	if curl -fs --max-time 2 -o /dev/null "$$url/json/version"; then echo "BU_CDP_URL=$$url"; exit 0; fi; \
	bin=""; \
	for candidate in $(if $(CHROME),"$(CHROME)") $(CHROME_CANDIDATES); do \
		if [ -x "$$candidate" ]; then bin="$$candidate"; break; fi; \
		if found="$$(command -v "$$candidate" 2>/dev/null)"; then bin="$$found"; break; fi; \
	done; \
	if [ -z "$$bin" ]; then echo "Chrome ou Chromium introuvable : régler CHROME=<chemin du binaire>" >&2; exit 1; fi; \
	mkdir -p reports/browser; \
	nohup "$$bin" --headless=new --remote-debugging-port=$(CDP_PORT) --user-data-dir=reports/browser/profile \
		--no-first-run --window-size=1280,900 about:blank > reports/browser/chrome.log 2>&1 & \
	echo $$! > reports/browser/chrome.pid; \
	for _ in $$(seq 1 30); do \
		if curl -fs --max-time 1 -o /dev/null "$$url/json/version"; then echo "BU_CDP_URL=$$url"; exit 0; fi; \
		sleep 0.5; \
	done; \
	echo "Chrome ne répond pas sur $$url après 15 s ; voir reports/browser/chrome.log" >&2; exit 1

build: ## Compile les assets ; supprime un public/hot périmé
	@if [ -f public/hot ]; then \
		if curl -fs --max-time 2 -o /dev/null "$$(cat public/hot)/@vite/client"; then \
			echo "Vite tourne : la page charge les assets du serveur de développement"; \
		else rm -f public/hot; echo "public/hot supprimé"; fi; fi
	$(PNPM) run build

wayfinder: ## Interne : génère les routes et actions Wayfinder
	$(ARTISAN) wayfinder:generate --with-form

##@ Qualité

lint: wayfinder ## Vérifie Pint et vp check (formatage, lint typé, types), sans rien modifier
	@status=0; \
	$(PHP) vendor/bin/pint --test || status=1; \
	$(PNPM) exec vp check || status=1; \
	exit $$status

format: wayfinder ## Reformate le PHP (Pint) et le TypeScript (vp check --fix)
	$(PHP) vendor/bin/pint
	$(PNPM) exec vp check --fix

analyse: ## Lance Larastan (PHPStan niveau 8)
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

duplicates: ## Cherche le code dupliqué avec jscpd (seuil 0)
	$(PNPM) exec jscpd

CHECK_STEPS := lint analyse duplicates test-backend test-frontend

check: ## Lance tous les contrôles en une passe ; logs dans reports/check/
	@mkdir -p reports/check; failed=""; \
	for step in $(CHECK_STEPS); do \
		if $(MAKE) --no-print-directory $$step > reports/check/$$step.log 2>&1; \
		then echo "✓ $$step"; else echo "✗ $$step → reports/check/$$step.log"; failed="$$failed $$step"; fi; \
	done; \
	for step in $$failed; do echo; echo "── $$step (fin du log)"; tail -n 40 reports/check/$$step.log; done; \
	test -z "$$failed"

##@ Tests

test-backend: ## Lance les tests Pest en parallèle ; FILES=chemins ou FILTER=motif : un seul processus ; SHARD=i/n ; JUNIT=chemin
	@if [ -n "$(SHARD)" ] && [ -n "$(FILES)$(FILTER)" ]; then \
		echo "SHARD ne se combine pas avec FILES ou FILTER" >&2; exit 2; fi
	$(PHP) vendor/bin/pest $(if $(FILES)$(FILTER),,--parallel) \
		$(FILES) $(if $(FILTER),--filter='$(FILTER)') \
		$(if $(SHARD),--shard=$(SHARD)) $(if $(JUNIT),--log-junit=$(JUNIT))

test-frontend: ## Lance les tests Vitest sous le fuseau America/Montreal ; FILES=chemins ; JUNIT=chemin
	$(PNPM) exec env LARAVEL_BYPASS_ENV_CHECK=1 vp test run $(FILES) \
		$(if $(JUNIT),--reporter=default --reporter=junit --outputFile.junit=$(JUNIT))

test: test-backend test-frontend ## Lance les tests backend puis frontend

arch-test: ## Lance seulement les tests d'architecture
	$(PHP) vendor/bin/pest --testsuite=Arch

update-shards: ## Recalcule la répartition des partitions (tests/.pest/shards.json)
	$(PHP) vendor/bin/pest --parallel --update-shards

##@ Git et CI

FROM ?= origin/main
TO ?= HEAD
DOCS_PATHS := ^([^/]+\.md|version\.txt|docs/.+|openspec/.+|\.ai/guidelines/.+)$$

commitlint: ## Vérifie les messages de commit de FROM...TO (défauts : origin/main, HEAD)
	$(PNPM) exec commitlint --from $(FROM) --to $(TO)

changed-code: ## Affiche true si FROM...TO touche autre chose que la documentation
	@files="$$(git -c core.quotePath=false diff --name-only --no-renames $(FROM)...$(TO))" || exit 1; \
	if [ -z "$$files" ] || printf '%s' "$$files" | grep -qvE '$(DOCS_PATHS)'; then echo true; else echo false; fi

# =============================================================================
# CorePHP — Makefile
# Primary interface for all development tasks
# =============================================================================

IMAGE_NAME  := corephp-vm
IMAGE_TAG   := latest
CONTAINER   := corephp-vm-app
COMPOSE     := docker compose

.PHONY: help build up down restart shell check test lint lint-fix rr-start logs clean

# Default target
help: ## Show available targets
	@echo ""
	@echo "  CorePHP — Available Makefile Targets"
	@echo "  ================================================"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| sort \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'
	@echo ""

# ---------------------------------------------------------------------------
# Docker lifecycle
# ---------------------------------------------------------------------------

build: ## Build the corephp-vm Docker image
	@echo "→ Building $(IMAGE_NAME):$(IMAGE_TAG)..."
	docker build --no-cache -t $(IMAGE_NAME):$(IMAGE_TAG) .

up: ## Start Docker Compose services (detached)
	@echo "→ Starting services..."
	$(COMPOSE) up -d

down: ## Stop and remove Docker Compose services
	@echo "→ Stopping services..."
	$(COMPOSE) down

restart: down up ## Restart all services

logs: ## Follow container logs
	$(COMPOSE) logs -f

shell: ## Open a bash shell in the running container
	$(COMPOSE) exec app sh

# ---------------------------------------------------------------------------
# Quality gates (run inside container)
# ---------------------------------------------------------------------------

check: ## Run tests + lint in sequence (full quality gate before committing)
	@echo "→ Running full quality gate..."
	$(COMPOSE) exec app sh /app/ci/test.sh
	$(COMPOSE) exec app sh /app/ci/lint.sh
	@echo "✓ All checks passed"

lint: ## Run PHP-CS-Fixer (dry-run) + PHPStan Level 9
	$(COMPOSE) exec app sh /app/ci/lint.sh

lint-fix: ## Auto-fix PHP-CS-Fixer violations
	$(COMPOSE) exec app sh -c "cd /app && vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php"

test: ## Run PHPUnit test suite
	$(COMPOSE) exec app sh /app/ci/test.sh

# ---------------------------------------------------------------------------
# RoadRunner
# ---------------------------------------------------------------------------

rr-start: ## Start the RoadRunner worker (foreground)
	$(COMPOSE) exec app rr serve -c /app/.rr.yaml

# ---------------------------------------------------------------------------
# Release — semantic versioning via git tags
# ---------------------------------------------------------------------------

tag: ## Create and push a semver git tag  →  make tag VERSION=1.0.0
ifndef VERSION
	$(error VERSION is required. Usage: make tag VERSION=1.2.3)
endif
	@echo "→ Tagging v$(VERSION)..."
	git tag -a v$(VERSION) -m "Release v$(VERSION)"
	git push origin v$(VERSION)
	@echo "✓ Pushed v$(VERSION) — GitHub Actions will build & push to Docker Hub"

release: ## Alias for: make tag VERSION=x.y.z
	$(MAKE) tag VERSION=$(VERSION)

# ---------------------------------------------------------------------------
# Maintenance
# ---------------------------------------------------------------------------

clean: ## Remove stopped containers and dangling images
	docker system prune -f
	docker image rm $(IMAGE_NAME):$(IMAGE_TAG) 2>/dev/null || true

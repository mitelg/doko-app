.DEFAULT_GOAL := help

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
.PHONY: help

fix-cs: ## Run easy coding style in fix mode
	- vendor/bin/ecs check --fix
.PHONY: fix-cs

phpstan: ## Run PHPStan
	- vendor/bin/phpstan analyse
.PHONY: phpstan

psalm: ## Run Psalm
	- vendor/bin/psalm --threads=max --diff --diff-methods --show-info=false
.PHONY: psalm

phpunit: ## Run unit tests
	- vendor/bin/phpunit
.PHONY: phpunit

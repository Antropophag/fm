ENV_FILE ?= .env
MANAGE := bash deploy/production/manage

.PHONY: env-init deploy status logs stop start backup update restore
env-init:
	@$(MANAGE) env-init --env-file "$(ENV_FILE)"
	@bash deploy/production/release-env-init "$(ENV_FILE)" "$(CURDIR)/release.defaults" "$(CURDIR)/images.tar"

deploy status logs stop start update:
	@$(MANAGE) $@ --env-file "$(ENV_FILE)"

backup:
	@$(MANAGE) backup --env-file "$(ENV_FILE)" $(if $(BACKUP),--backup "$(BACKUP)")

restore:
	@$(MANAGE) restore --env-file "$(ENV_FILE)" $(if $(BACKUP),--backup "$(BACKUP)")

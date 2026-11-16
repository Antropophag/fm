ENV_FILE ?= .env
MANAGE := bash deploy/production/manage

.PHONY: env-init deploy status logs stop start backup update restore
env-init:
	@$(MANAGE) env-init --env-file "$(ENV_FILE)"

deploy status logs stop start update:
	@$(MANAGE) $@ --env-file "$(ENV_FILE)"

backup:
	@$(MANAGE) backup --env-file "$(ENV_FILE)" $(if $(BACKUP),--backup "$(BACKUP)")

restore:
	@$(MANAGE) restore --env-file "$(ENV_FILE)" $(if $(BACKUP),--backup "$(BACKUP)")

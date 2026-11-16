#!/usr/bin/env bash
# Sourced by deploy/production/manage after configuration validation and export.

recovery_running_writers(){
  local service
  for service in jobs-scheduler jobs-worker web php; do
    dc ps --status running --services | grep -qx "$service" && printf '%s\n' "$service"
  done
}

recovery_stop_writers(){
  local running_name=$1 service current=()
  while IFS= read -r service; do current+=("$service"); done < <(recovery_running_writers)
  eval "$running_name=(\"\${current[@]}\")"
  for service in jobs-scheduler jobs-worker web php; do
    [[ " ${current[*]} " == *" $service "* ]] && dc stop "$service"
  done
  for service in "${current[@]}"; do
    ! dc ps --status running --services | grep -qx "$service" || { note "Writer failed to stop: $service" >&2; return 1; }
  done
}

recovery_resume_writers(){
  (($# == 0)) || dc up -d --wait "$@"
}

recovery_private_manifest(){
  local runtime=$1 config=$2 output=$3 config_sha runtime_sha
  config_sha=$(sha256sum "$config" | awk '{print $1}')
  runtime_sha=$(sha256sum "$runtime/manifest.json" | awk '{print $1}')
  printf '{"formatVersion":"fmonitor-production-private-v1","sourceProject":"%s","configSha256":"%s","runtimeManifestSha256":"%s"}\n' \
    "$COMPOSE_PROJECT_NAME" "$config_sha" "$runtime_sha" >"$output"
  chmod 600 "$output"
}

recovery_host_ownership(){
  local path=$1 image_ref=$2
  docker run --rm --network none --user 0:0 --entrypoint chown \
    --mount "type=bind,source=$path,target=/private-output" "$image_ref" -R "$(id -u):$(id -g)" /private-output
}

backup_core(){
  local destination=$1 keep_stopped=${2:-false}
  local backup_image=${3:-$FMONITOR_RUNTIME_IMAGE} backup_source=${4:-$FMONITOR_SOURCE_REVISION} backup_image_id=${5:-$IMAGE_ID}
  local config_source=${6:-"$(installed_env_path)"} stage canonical config service resumed=false expected_config_hash actual_config_hash
  local running=()
  mkdir -p -m 700 "$FMONITOR_BACKUP_ROOT"
  [[ $destination == /* && $destination != "$ROOT"/* && ! -e $destination && ! -L $destination ]] || fail BACKUP
  [[ -f $config_source && ! -L $config_source ]] || fail installation
  expected_config_hash=$(sed -n 's/^config_hash=//p' "${FMONITOR_DEPLOY_STATE_DIR}/installed")
  actual_config_hash=$(sha256sum "$config_source" | awk '{print $1}')
  [[ $expected_config_hash =~ ^[0-9a-f]{64}$ && $actual_config_hash == "$expected_config_hash" ]] || fail installation
  if ! recovery_stop_writers running; then
    note 'Backup failed while quiescing; writers remain stopped or partially stopped.' >&2
    return 1
  fi
  stage="$destination.stage.$$.$RANDOM"
  if ! (umask 077; set -o noclobber; mkdir -m 700 "$stage"); then
    [[ $keep_stopped == false ]] && recovery_resume_writers "${running[@]}" || true
    fail BACKUP
  fi
  canonical="$stage/runtime"; config="$stage/restore.env"
  if ! cp "$config_source" "$config" || ! chmod 600 "$config"; then
    rm -rf "$stage"
    [[ $keep_stopped == false ]] && recovery_resume_writers "${running[@]}" || true
    return 1
  fi
  if ! docker run --rm --network none --user 0:0 --entrypoint chown \
      --mount "type=bind,source=$stage,target=/backup" "$backup_image" 10001:10001 /backup; then
    recovery_host_ownership "$stage" "$backup_image" || true; rm -rf "$stage"
    [[ $keep_stopped == false ]] && recovery_resume_writers "${running[@]}" || true; return 1
  fi
  if ! ( export FMONITOR_DB_HOST=db FMONITOR_DB_PORT=3306; \
    docker run --rm --network "${COMPOSE_PROJECT_NAME}_default" --user 10001:10001 \
      -e FMONITOR_DB_HOST -e FMONITOR_DB_PORT -e FMONITOR_DB_NAME -e FMONITOR_DB_USER \
      -e FMONITOR_DB_PASSWORD -e FMONITOR_PROCESS_TABLE_PREFIX -e FMONITOR_LEGACY_TABLE_PREFIX \
      -e FMONITOR_SESSION_INSTANCE -e FMONITOR_YII_COOKIE_VALIDATION_KEY -e FMONITOR_YII_IDENTITY_KEY \
      -e FMONITOR_TRUSTED_REQUEST_HOST -e FMONITOR_TRUSTED_REQUEST_SCHEME \
      -e FMONITOR_SESSION_STATE_ROOT=/home/fmonitor/.local/state/fmonitor2 \
      -e FMONITOR_YII_SESSION_PATH=/home/fmonitor/.local/state/fmonitor2/yii-sessions \
      -e FMONITOR_ARTIFACT_STORAGE_ROOT=/home/fmonitor/.local/state/fmonitor2/artifacts \
      -e FMONITOR_ORIGINAL_DB_PASSWORD_FILE=/run/fmonitor-secrets/database-password \
      -e FMONITOR_ORIGINAL_SAFE_LOG_FILE=/home/fmonitor/.local/state/fmonitor2/log/original-safe.jsonl \
      --mount "type=volume,source=${COMPOSE_PROJECT_NAME}_state,target=/home/fmonitor/.local/state" \
      --mount "type=volume,source=${COMPOSE_PROJECT_NAME}_secrets,target=/run/fmonitor-secrets" \
      --mount "type=bind,source=$stage,target=/backup" "$backup_image" \
      php bin/fmonitor2-runtime-recovery.php backup --destination /backup/runtime \
      --source-commit "$backup_source" --image-reference "$backup_image" --image-id "$backup_image_id" --writers-stopped ); then
    recovery_host_ownership "$stage" "$backup_image" || true; rm -rf "$stage"
    if [[ $keep_stopped == false ]] && recovery_resume_writers "${running[@]}"; then resumed=true; fi
    note "Backup failed; writers $([[ $resumed == true ]] && printf resumed || printf stopped)." >&2
    return 1
  fi
  if ! recovery_host_ownership "$stage" "$backup_image" \
      || ! recovery_private_manifest "$canonical" "$config" "$stage/private-manifest.json" \
      || ! find "$stage" -type d -exec chmod 700 {} + \
      || ! find "$stage" -type f -exec chmod 600 {} + \
      || ! mv -n "$stage" "$destination" || [[ -e $stage ]]; then
    rm -rf "$stage"
    if [[ $keep_stopped == false ]] && recovery_resume_writers "${running[@]}"; then resumed=true; fi
    note "Backup publication failed; writers $([[ $resumed == true ]] && printf resumed || printf stopped)." >&2
    return 1
  fi
  if [[ $keep_stopped == false ]]; then recovery_resume_writers "${running[@]}"; fi
  note "Backup created: $destination"
}

do_backup(){
  lock; prereq; installed; installed_image
  IMAGE_ID=$(sed -n 's/^image_id=//p' "${FMONITOR_DEPLOY_STATE_DIR}/installed")
  local destination=${ARG_PATH:-"${FMONITOR_BACKUP_ROOT}/backup-$(date -u +%Y%m%dT%H%M%SZ)-$$"}
  backup_core "$destination" false
}

update_cmd(){
  lock; prereq; installed any
  local receipt_file="${FMONITOR_DEPLOY_STATE_DIR}/installed" old_source old_image old_image_id old_config_hash current_config_hash
  old_source=$(sed -n 's/^source=//p' "$receipt_file"); old_image=$(sed -n 's/^image=//p' "$receipt_file"); old_image_id=$(sed -n 's/^image_id=//p' "$receipt_file")
  old_config_hash=$(sed -n 's/^config_hash=//p' "$receipt_file"); current_config_hash=$(sha256sum "$ENV_FILE" | awk '{print $1}')
  [[ $old_source != "$FMONITOR_SOURCE_REVISION" || $old_image != "$FMONITOR_RUNTIME_IMAGE" || $old_config_hash != "$current_config_hash" ]] || { installed_image; start_services; return; }
  image
  local new_image_id=$IMAGE_ID backup_path="${FMONITOR_BACKUP_ROOT}/update-$old_source-$(date -u +%Y%m%dT%H%M%SZ)-$$"
  if ! backup_core "$backup_path" true "$old_image" "$old_source" "$old_image_id"; then return 1; fi
  IMAGE_ID=$new_image_id; receipt updating
  if ! provision || ! start_services; then
    local services=() service; while IFS= read -r service; do services+=("$service"); done < <(writers)
    dc stop "${services[@]}" || true; receipt failed
    note "Update failed; writers stopped. Recovery backup: $backup_path" >&2
    return 1
  fi
  receipt installed
}

recovery_verify_bundle(){
  local canonical=$1 config=$2 source=$3 image_ref=$4 image_id=$5 source_project=$6
  docker run --rm --network none --user 0:0 \
    --mount "type=bind,source=$canonical,target=/bundle,readonly" \
    --mount "type=bind,source=$(dirname "$config"),target=/private,readonly" \
    --mount "type=bind,source=$ENV_FILE,target=/private/target.env,readonly" \
    "$image_ref" php deploy/production/recovery-manifest.php verify /bundle "$source" "$image_ref" "$image_id" /private/restore.env /private/target.env "$source_project"
}

restore_cmd(){
  [[ -n $ARG_PATH && $ARG_PATH == /* && -d $ARG_PATH && ! -L $ARG_PATH ]] || fail BACKUP
  local canonical="$ARG_PATH/runtime" config="$ARG_PATH/restore.env" private="$ARG_PATH/private-manifest.json"
  [[ -d $canonical && -f $config && ! -L $config && -f $private && ! -L $private ]] || fail BACKUP
  local source source_image source_image_id source_project
  source=$(sed -n 's/.*"sourceCommit":"\([0-9a-f]*\)".*/\1/p' "$canonical/manifest.json")
  source_image=$(sed -n 's/.*"imageReference":"\([^"]*\)".*/\1/p' "$canonical/manifest.json")
  source_image_id=$(sed -n 's/.*"imageId":"\([^"]*\)".*/\1/p' "$canonical/manifest.json")
  source_project=$(sed -n 's/.*"sourceProject":"\([^"]*\)".*/\1/p' "$private")
  [[ $source == "$FMONITOR_SOURCE_REVISION" && $source_image == "$FMONITOR_RUNTIME_IMAGE" && $source_project != "$COMPOSE_PROJECT_NAME" ]] || fail BACKUP
  lock; prereq; image; [[ $IMAGE_ID == "$source_image_id" ]] || fail BACKUP
  ensure_unowned_or_owned; [[ ! -e "${FMONITOR_DEPLOY_STATE_DIR}/installed" ]] || fail installation
  ! docker network inspect "${COMPOSE_PROJECT_NAME}_default" >/dev/null 2>&1 || fail installation
  recovery_verify_bundle "$canonical" "$config" "$source" "$source_image" "$source_image_id" "$source_project" || fail BACKUP
  local stage="${FMONITOR_DEPLOY_STATE_DIR}/restore-bundle.$$.$RANDOM" password_file="${FMONITOR_DEPLOY_STATE_DIR}/restore-database-password.$$.$RANDOM"
  (umask 077; mkdir -m 700 "$stage"; cp "$canonical"/* "$stage"/; chmod 600 "$stage"/*)
  docker run --rm --network none --user 0:0 --entrypoint chown --mount "type=bind,source=$stage,target=/bundle" "$FMONITOR_RUNTIME_IMAGE" -R 10001:10001 /bundle
  if ! ( export FMONITOR_DB_HOST=db FMONITOR_DB_PORT=3306; docker run --rm --network none --user 10001:10001 \
      -e FMONITOR_DB_HOST -e FMONITOR_DB_PORT -e FMONITOR_DB_NAME -e FMONITOR_DB_USER -e FMONITOR_DB_PASSWORD \
      -e FMONITOR_PROCESS_TABLE_PREFIX -e FMONITOR_LEGACY_TABLE_PREFIX -e FMONITOR_SESSION_INSTANCE \
      -e FMONITOR_YII_COOKIE_VALIDATION_KEY -e FMONITOR_YII_IDENTITY_KEY -e FMONITOR_TRUSTED_REQUEST_HOST -e FMONITOR_TRUSTED_REQUEST_SCHEME \
      -e FMONITOR_SESSION_STATE_ROOT=/home/fmonitor/.local/state/fmonitor2 \
      -e FMONITOR_YII_SESSION_PATH=/home/fmonitor/.local/state/fmonitor2/yii-sessions \
      -e FMONITOR_ARTIFACT_STORAGE_ROOT=/home/fmonitor/.local/state/fmonitor2/artifacts \
      -e FMONITOR_ORIGINAL_DB_PASSWORD_FILE=/private/database-password \
      -e FMONITOR_ORIGINAL_SAFE_LOG_FILE=/home/fmonitor/.local/state/fmonitor2/log/original-safe.jsonl \
      --mount "type=bind,source=$stage,target=/bundle,readonly" "$FMONITOR_RUNTIME_IMAGE" \
      php bin/fmonitor2-runtime-recovery.php verify --bundle /bundle --source-commit "$source" --image-reference "$source_image" --image-id "$source_image_id" ); then
    recovery_host_ownership "$stage" "$FMONITOR_RUNTIME_IMAGE" || true; rm -rf "$stage"; fail BACKUP
  fi
  dc up -d --wait db
  dc --profile deployment run --rm -e FMONITOR_MIGRATION_DB_USER -e FMONITOR_MIGRATION_DB_PASSWORD --entrypoint php prepare bin/yii local-runtime/provision-database --interactive=0
  docker run --rm --network none --user 0:0 --entrypoint sh \
    --mount "type=volume,source=${COMPOSE_PROJECT_NAME}_state,target=/home/fmonitor/.local/state,volume-nocopy" \
    "$FMONITOR_RUNTIME_IMAGE" -c 'test ! -e /home/fmonitor/.local/state/fmonitor2 && chown 10001:10001 /home/fmonitor/.local/state && chmod 0700 /home/fmonitor/.local/state'
  (umask 077; printf '%s' "$FMONITOR_MIGRATION_DB_PASSWORD" >"$password_file"; chmod 600 "$password_file")
  docker run --rm --network none --user 0:0 --entrypoint chown --mount "type=bind,source=$password_file,target=/password" "$FMONITOR_RUNTIME_IMAGE" 10001:10001 /password
  if ! ( export FMONITOR_DB_HOST=db FMONITOR_DB_PORT=3306 FMONITOR_DB_USER="$FMONITOR_MIGRATION_DB_USER" FMONITOR_DB_PASSWORD="$FMONITOR_MIGRATION_DB_PASSWORD"; \
  docker run --rm --network "${COMPOSE_PROJECT_NAME}_default" --user 10001:10001 \
    -e FMONITOR_DB_HOST -e FMONITOR_DB_PORT -e FMONITOR_DB_NAME -e FMONITOR_DB_USER \
    -e FMONITOR_DB_PASSWORD -e FMONITOR_PROCESS_TABLE_PREFIX -e FMONITOR_LEGACY_TABLE_PREFIX \
    -e FMONITOR_SESSION_INSTANCE -e FMONITOR_YII_COOKIE_VALIDATION_KEY -e FMONITOR_YII_IDENTITY_KEY \
    -e FMONITOR_TRUSTED_REQUEST_HOST -e FMONITOR_TRUSTED_REQUEST_SCHEME \
    -e FMONITOR_SESSION_STATE_ROOT=/home/fmonitor/.local/state/fmonitor2 \
    -e FMONITOR_YII_SESSION_PATH=/home/fmonitor/.local/state/fmonitor2/yii-sessions \
    -e FMONITOR_ARTIFACT_STORAGE_ROOT=/home/fmonitor/.local/state/fmonitor2/artifacts \
    -e FMONITOR_ORIGINAL_DB_PASSWORD_FILE=/private/database-password \
    -e FMONITOR_ORIGINAL_SAFE_LOG_FILE=/home/fmonitor/.local/state/fmonitor2/log/original-safe.jsonl \
    --mount "type=volume,source=${COMPOSE_PROJECT_NAME}_state,target=/home/fmonitor/.local/state,volume-nocopy" \
    --tmpfs /private:uid=10001,gid=10001,mode=0700 \
    --mount "type=bind,source=$password_file,target=/private/database-password,readonly" \
    --mount "type=bind,source=$stage,target=/bundle,readonly" "$FMONITOR_RUNTIME_IMAGE" \
    php bin/fmonitor2-runtime-recovery.php restore --bundle /bundle --target-state-root /home/fmonitor/.local/state/fmonitor2 \
    --source-commit "$source" --image-reference "$source_image" --image-id "$source_image_id" ); then
    recovery_host_ownership "$stage" "$FMONITOR_RUNTIME_IMAGE" || true
    recovery_host_ownership "$password_file" "$FMONITOR_RUNTIME_IMAGE" || true
    rm -rf "$stage" "$password_file"
    note 'Restore failed; target writers remain stopped.' >&2
    return 1
  fi
  recovery_host_ownership "$stage" "$FMONITOR_RUNTIME_IMAGE"
  recovery_host_ownership "$password_file" "$FMONITOR_RUNTIME_IMAGE"
  rm -rf "$stage" "$password_file"
  dc --profile deployment run --rm prepare
  stage_integrations
  dc --profile deployment run --rm --entrypoint php prepare bin/fmonitor2-runtime-check.php
  start_services; receipt installed
}

release_cmd(){
  [[ -n $ARG_PATH && $ARG_PATH == /* && ! -e $ARG_PATH && ! -L $ARG_PATH ]] || fail OUTPUT
  lock; prereq; image
  local stage="$ARG_PATH.stage.$$.$RANDOM" db_image=mariadb:11.4.7-noble
  mkdir -m 700 "$stage"; docker image inspect "$db_image" >/dev/null 2>&1 || docker pull "$db_image" >/dev/null
  docker save -o "$stage/images.tar" "$FMONITOR_RUNTIME_IMAGE" "$db_image"
  mkdir -m 700 "$stage/deploy"; cp -R "$ROOT/deploy/production" "$stage/deploy/production"
  cp "$ROOT/deploy/production/release.Makefile" "$stage/Makefile"
  printf '{"formatVersion":"fmonitor-production-release-v1","source":"%s","applicationImage":"%s","applicationImageId":"%s","databaseImage":"%s"}\n' \
    "$FMONITOR_SOURCE_REVISION" "$FMONITOR_RUNTIME_IMAGE" "$IMAGE_ID" "$db_image" >"$stage/release.json"
  printf 'FMONITOR_RUNTIME_IMAGE=%s\nFMONITOR_SOURCE_REVISION=%s\nFMONITOR_IMAGE_ARCHIVE_SHA256=%s\n' \
    "$FMONITOR_RUNTIME_IMAGE" "$FMONITOR_SOURCE_REVISION" "$(sha256sum "$stage/images.tar" | awk '{print $1}')" >"$stage/release.defaults"
  (cd "$stage"; find deploy -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum; sha256sum Makefile images.tar release.json release.defaults) >"$stage/SHA256SUMS"
  find "$stage" -type d -exec chmod 700 {} +; find "$stage" -type f -exec chmod 600 {} +
  mv -n "$stage" "$ARG_PATH"
  [[ ! -e $stage ]] || { rm -rf "$stage"; fail OUTPUT; }
  note "Release created: $ARG_PATH"
}

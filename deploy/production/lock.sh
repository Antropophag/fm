#!/usr/bin/env bash
# Sourced by deploy/production/manage after configuration validation and export.

production_lock_error(){ note "$1" >&2; }

production_lock_root_identity(){
  stat -c '%d:%i:%u:%g:%a' -- "$1" 2>/dev/null \
    || stat -f '%d:%i:%u:%g:%Lp' "$1" 2>/dev/null
}

production_lock_helper_script(){
  cat <<'SH'
set -eu
action=$1
expected_device=$2
expected_inode=$3
expected_uid=$4
expected_gid=$5
expected_mode=$6
project=$7
token=$8
expected_lock_inode=${9:-}
root=/locks
identity=$(stat -c '%d:%i:%u:%g:%a' -- "$root") || exit 70
[ "$identity" = "$expected_device:$expected_inode:$expected_uid:$expected_gid:$expected_mode" ] || exit 71
lock="$root/$project.lock"
holder="$lock/.fmonitor2-holder"
case "$action" in
  acquire)
    umask 077
    mkdir -- "$lock" 2>/dev/null || exit 75
    lock_inode=$(stat -c '%i' -- "$lock") || exit 72
    if ! (set -C; printf '%s\n' "$token" >"$holder"); then
      rmdir -- "$lock" 2>/dev/null || true
      exit 72
    fi
    printf '%s\n' "$lock_inode"
    ;;
  release)
    [ -d "$lock" ] && [ ! -L "$lock" ] || exit 73
    [ "$(stat -c '%i' -- "$lock")" = "$expected_lock_inode" ] || exit 73
    [ -f "$holder" ] && [ ! -L "$holder" ] || exit 73
    [ "$(cat -- "$holder")" = "$token" ] || exit 73
    rm -- "$holder" || exit 74
    rmdir -- "$lock" || exit 74
    ;;
  *) exit 69;;
esac
SH
}

production_lock_verified_helper_image(){
  local receipt="${FMONITOR_DEPLOY_STATE_DIR}/installed" reference expected actual
  [[ -f $receipt && ! -L $receipt ]] || return 1
  grep -qx "project=${COMPOSE_PROJECT_NAME}" "$receipt" || return 1
  grep -qx 'state=installed' "$receipt" || return 1
  reference=$(sed -n 's/^database_image=//p' "$receipt")
  expected=$(sed -n 's/^database_image_id=//p' "$receipt")
  [[ -n $reference && $reference == "$FMONITOR_DATABASE_IMAGE" ]] || return 1
  [[ $expected =~ ^sha256:[0-9a-f]{64}$ ]] || return 1
  actual=$(docker image inspect "$reference" --format '{{.Id}}' 2>/dev/null) || return 1
  [[ $actual == "$expected" ]] || return 1
  printf '%s\n' "$expected"
}

production_lock_helper(){
  local action=$1 image=$2 device=$3 inode=$4 uid=$5 gid=$6 mode=$7 token=$8 lock_inode=${9:-}
  local script
  script=$(production_lock_helper_script) || return 1
  docker run --rm --pull=never --network none --read-only --cap-drop ALL \
    --security-opt no-new-privileges --user "$uid:$gid" --entrypoint sh \
    --mount "type=bind,source=$PRODUCTION_LOCK_ROOT,target=/locks" \
    "$image" -c "$script" -- "$action" "$device" "$inode" "$uid" "$gid" "$mode" \
    "$COMPOSE_PROJECT_NAME" "$token" "$lock_inode"
}

production_lock_cleanup(){
  local operation_status=$? cleanup_status=0
  trap - EXIT
  if [[ ${PRODUCTION_LOCK_KIND:-} == helper ]]; then
    production_lock_helper release "$PRODUCTION_LOCK_IMAGE" "$PRODUCTION_LOCK_ROOT_DEVICE" \
      "$PRODUCTION_LOCK_ROOT_INODE" "$PRODUCTION_LOCK_ROOT_UID" "$PRODUCTION_LOCK_ROOT_GID" "$PRODUCTION_LOCK_ROOT_MODE" \
      "$PRODUCTION_LOCK_TOKEN" "$PRODUCTION_LOCK_INODE" >/dev/null || cleanup_status=$?
  elif [[ ${PRODUCTION_LOCK_KIND:-} == native ]]; then
    rmdir -- "$LOCK" 2>/dev/null || cleanup_status=$?
  fi
  if ((cleanup_status != 0)); then
    production_lock_error 'PRODUCTION_LOCK_CLEANUP_FAILED'
    ((operation_status != 0)) || operation_status=1
  fi
  exit "$operation_status"
}

production_lock_acquire(){
  local identity device inode uid gid mode image token lock_inode
  PRODUCTION_LOCK_ROOT=/tmp/fmonitor2-production-locks
  if [[ ! -e $PRODUCTION_LOCK_ROOT && ! -L $PRODUCTION_LOCK_ROOT ]]; then
    (umask 077; mkdir "$PRODUCTION_LOCK_ROOT") || { production_lock_error 'PRODUCTION_LOCK_INVALID'; exit 64; }
  fi
  [[ -d $PRODUCTION_LOCK_ROOT && ! -L $PRODUCTION_LOCK_ROOT ]] \
    || { production_lock_error 'PRODUCTION_LOCK_INVALID'; exit 64; }
  identity=$(production_lock_root_identity "$PRODUCTION_LOCK_ROOT") \
    || { production_lock_error 'PRODUCTION_LOCK_INVALID'; exit 64; }
  IFS=: read -r device inode uid gid mode <<<"$identity"
  [[ $device =~ ^[0-9]+$ && $inode =~ ^[0-9]+$ && $uid =~ ^[0-9]+$ \
    && $gid =~ ^[0-9]+$ && $mode == 700 ]] \
    || { production_lock_error 'PRODUCTION_LOCK_INVALID'; exit 64; }
  LOCK="$PRODUCTION_LOCK_ROOT/${COMPOSE_PROJECT_NAME}.lock"
  if [[ $uid == "$(id -u)" ]]; then
    mkdir "$LOCK" 2>/dev/null || { production_lock_error 'Production operation already running.'; exit 75; }
    PRODUCTION_LOCK_KIND=native
    trap production_lock_cleanup EXIT
    return 0
  fi
  image=$(production_lock_verified_helper_image) \
    || { production_lock_error 'PRODUCTION_LOCK_HELPER_UNAVAILABLE'; exit 64; }
  token=$(random_hex) || { production_lock_error 'PRODUCTION_LOCK_HELPER_UNAVAILABLE'; exit 64; }
  if lock_inode=$(production_lock_helper acquire "$image" "$device" "$inode" "$uid" "$gid" "$mode" "$token"); then
    :
  else
    local status=$?
    if ((status == 75)); then production_lock_error 'Production operation already running.'; exit 75; fi
    if ((status == 69 || status == 125 || status == 126 || status == 127)); then
      production_lock_error 'PRODUCTION_LOCK_HELPER_UNAVAILABLE'
      exit 64
    fi
    production_lock_error 'PRODUCTION_LOCK_ACQUIRE_FAILED'
    exit 64
  fi
  [[ $lock_inode =~ ^[0-9]+$ ]] || { production_lock_error 'PRODUCTION_LOCK_ACQUIRE_FAILED'; exit 64; }
  PRODUCTION_LOCK_KIND=helper
  PRODUCTION_LOCK_IMAGE=$image
  PRODUCTION_LOCK_ROOT_DEVICE=$device
  PRODUCTION_LOCK_ROOT_INODE=$inode
  PRODUCTION_LOCK_ROOT_UID=$uid
  PRODUCTION_LOCK_ROOT_GID=$gid
  PRODUCTION_LOCK_ROOT_MODE=$mode
  PRODUCTION_LOCK_TOKEN=$token
  PRODUCTION_LOCK_INODE=$lock_inode
  trap production_lock_cleanup EXIT
}

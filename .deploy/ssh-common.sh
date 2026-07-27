# Shared deploy helpers — source after ssh.env
# Prod: SSH_ALIAS + REMOTE_ROOT (Key-Auth, openssh auf Unraid)
# Legacy: SSH_HOST + SSH_USER + SSH_PASS (curl SFTP)

deploy_ssh_target() {
  if [[ -n "${SSH_ALIAS:-}" ]]; then
    printf '%s' "$SSH_ALIAS"
  else
    printf '%s@%s' "$SSH_USER" "$SSH_HOST"
  fi
}

deploy_remote_root() {
  local root="${REMOTE_ROOT:-/}"
  root="${root%/}"
  [[ -n "$root" ]] || root="/"
  printf '%s' "$root"
}

deploy_remote_path() {
  local rel="${1#/}"
  local root
  root="$(deploy_remote_root)"
  if [[ "$root" == "/" ]]; then
    printf '/%s' "$rel"
  else
    printf '%s/%s' "$root" "$rel"
  fi
}

deploy_ssh_opts() {
  local -a opts=(-o BatchMode=yes -o StrictHostKeyChecking=accept-new)
  if [[ -z "${SSH_ALIAS:-}" ]]; then
    [[ -n "${SSH_PORT:-}" ]] && opts+=(-p "$SSH_PORT")
    [[ -n "${SSH_KEY_FILE:-}" ]] && opts+=(-i "$SSH_KEY_FILE")
  fi
  printf '%s\n' "${opts[@]}"
}

deploy_ssh() {
  if [[ -n "${SSH_PASS:-}" && -z "${SSH_ALIAS:-}" && -z "${SSH_KEY_FILE:-}" ]]; then
    SSHPASS="$SSH_PASS" sshpass -e ssh "$(deploy_ssh_opts | head -1)" \
      -o StrictHostKeyChecking=accept-new \
      ${SSH_PORT:+-p "$SSH_PORT"} \
      "$(deploy_ssh_target)" "$@"
    return
  fi
  # shellcheck disable=SC2046
  ssh $(deploy_ssh_opts) "$(deploy_ssh_target)" "$@"
}

deploy_scp() {
  local src="$1" dest="$2"
  if [[ -n "${SSH_PASS:-}" && -z "${SSH_ALIAS:-}" && -z "${SSH_KEY_FILE:-}" ]]; then
    SSHPASS="$SSH_PASS" sshpass -e scp -o StrictHostKeyChecking=accept-new \
      ${SSH_PORT:+-P "$SSH_PORT"} "$src" "$(deploy_ssh_target):$dest"
    return
  fi
  # shellcheck disable=SC2046
  scp $(deploy_ssh_opts) "$src" "$(deploy_ssh_target):$dest"
}

deploy_scp_pull() {
  local remote="$1" local_path="$2"
  if [[ -n "${SSH_PASS:-}" && -z "${SSH_ALIAS:-}" && -z "${SSH_KEY_FILE:-}" ]]; then
    SSHPASS="$SSH_PASS" sshpass -e scp -o StrictHostKeyChecking=accept-new \
      ${SSH_PORT:+-P "$SSH_PORT"} "$(deploy_ssh_target):$remote" "$local_path"
    return
  fi
  # shellcheck disable=SC2046
  scp $(deploy_ssh_opts) "$(deploy_ssh_target):$remote" "$local_path"
}

deploy_rsync_ssh_e() {
  if [[ -n "${SSH_ALIAS:-}" ]]; then
    printf '%s' "ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new"
    return
  fi
  local e="ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new"
  [[ -n "${SSH_PORT:-}" ]] && e+=" -p ${SSH_PORT}"
  [[ -n "${SSH_KEY_FILE:-}" ]] && e+=" -i ${SSH_KEY_FILE}"
  printf '%s' "$e"
}

deploy_use_legacy_sftp() {
  [[ -n "${SSH_PASS:-}" && -z "${SSH_ALIAS:-}" ]]
}

deploy_curl_sftp() {
  local remote="${SFTP_REMOTE:-sftp://${SSH_HOST}}"
  if [[ -n "${SSH_PORT:-}" && "$remote" == "sftp://${SSH_HOST}" ]]; then
    remote="sftp://${SSH_HOST}:${SSH_PORT}"
  fi
  SSH_AUTH_SOCK= curl -sS --ftp-method nocwd --ftp-create-dirs --user "${SSH_USER}:${SSH_PASS}" "$@"
}

deploy_label() {
  if [[ -n "${SSH_ALIAS:-}" ]]; then
    printf '%s:%s' "$SSH_ALIAS" "$(deploy_remote_root)"
  else
    printf '%s%s' "${SSH_HOST}" "${SSH_PORT:+:$SSH_PORT}"
  fi
}

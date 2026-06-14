#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVER_DIR="$ROOT_DIR/server"
FRONTEND_DIR="$ROOT_DIR/saiadmin-artd"
UNIAPP_DIR="$ROOT_DIR/uniapp"
RELEASE_ROOT="$ROOT_DIR/build/docker-release"

########################################
# 用户配置区
########################################

# SSH 服务器别名，使用 ~/.ssh/config 中的 Host 名称，例如 shanghai。
REMOTE_ALIAS="${REMOTE_ALIAS:-shanghai}"

# 服务器上接收镜像 tar 包的目录。
REMOTE_UPLOAD_DIR="${REMOTE_UPLOAD_DIR:-/www/wwwroot/b8aiadmin/docker-release}"

# Docker 镜像名称和标签。标签默认使用当前时间，避免覆盖旧镜像。
IMAGE_NAME="${IMAGE_NAME:-b8aiadmin}"
IMAGE_TAG="${IMAGE_TAG:-$(date +%Y%m%d%H%M%S)}"

# build:bin 输出文件名。脚本会把这个文件复制成镜像内的 /app/server。
BIN_NAME="${BIN_NAME:-b8aiadmin.bin}"

# 本地生产环境配置。打包时默认复制为镜像内 /app/.env，供 .bin 读取。
# 注意：这样会把数据库、Redis、Token 等生产配置写入镜像 tar 包，请只在可信发布链路使用。
LOCAL_PRODUCTION_ENV_FILE="${LOCAL_PRODUCTION_ENV_FILE:-$SERVER_DIR/.env.production}"
INCLUDE_PRODUCTION_ENV="${INCLUDE_PRODUCTION_ENV:-1}"

# Docker 目标平台。linux/amd64 即常见 Linux x86_64。
DOCKER_PLATFORM="${DOCKER_PLATFORM:-linux/amd64}"

# Docker 基础镜像。默认 Debian slim，便于运行 Webman 二进制和基础 shell 工具。
DOCKER_BASE_IMAGE="${DOCKER_BASE_IMAGE:-debian:bookworm-slim}"

# 是否在镜像内安装 ca-certificates 和 tzdata。需要访问 HTTPS API 时建议保留开启。
INSTALL_CA_CERTIFICATES="${INSTALL_CA_CERTIFICATES:-1}"

# 容器内 Webman 监听端口，需与项目 config/server.php 保持一致。
APP_PORT="${APP_PORT:-8787}"

# 远程容器名称和宿主机端口映射。
CONTAINER_NAME="${CONTAINER_NAME:-b8aiadmin}"
HOST_PORT="${HOST_PORT:-8787}"

# 远程容器重启策略。
DOCKER_RESTART_POLICY="${DOCKER_RESTART_POLICY:-unless-stopped}"

# 远程 .env 文件路径。默认留空，因为镜像内会包含 /app/.env；如需运行时覆盖可填远程文件路径。
REMOTE_ENV_FILE="${REMOTE_ENV_FILE:-}"

# 远程运行时目录和上传存储目录，分别挂载到 /app/runtime 与 /app/public/storage。
REMOTE_RUNTIME_DIR="${REMOTE_RUNTIME_DIR:-/www/wwwroot/b8aiadmin/runtime}"
REMOTE_STORAGE_DIR="${REMOTE_STORAGE_DIR:-/www/wwwroot/b8aiadmin/public/storage}"

# 远程 Docker 网络名，留空则使用 Docker 默认网络。
REMOTE_DOCKER_NETWORK="${REMOTE_DOCKER_NETWORK:-}"

# 额外 docker run 参数，例如：--add-host host.docker.internal:host-gateway。
# 注意：这里会按 shell 原样拼入远程命令，请不要放不可信输入。
REMOTE_DOCKER_RUN_ARGS="${REMOTE_DOCKER_RUN_ARGS:-}"

# 容器启动命令，留空使用镜像默认 CMD ["start"]。
REMOTE_CONTAINER_COMMAND="${REMOTE_CONTAINER_COMMAND:-}"

# 是否重建远程容器。0 表示只上传并 docker load，不停止当前容器。
REMOTE_RELOAD_CONTAINER="${REMOTE_RELOAD_CONTAINER:-1}"

# admin 和 H5 的 public base，构建前端时会注入对应环境变量。
ADMIN_PUBLIC_BASE="${ADMIN_PUBLIC_BASE:-/admin/}"
H5_PUBLIC_BASE="${H5_PUBLIC_BASE:-/h5/}"

# 是否编译 admin / H5 / 执行迁移 / 自动更新线上镜像。
# 留空表示交互询问；非交互运行时空值默认按 0 处理。
BUILD_ADMIN="${BUILD_ADMIN:-}"
BUILD_H5="${BUILD_H5:-}"
RUN_MIGRATE="${RUN_MIGRATE:-}"
AUTO_REMOTE_UPDATE="${AUTO_REMOTE_UPDATE:-}"

# 线上迁移默认走服务器已有源码目录执行，避免把 Phinx 迁移绑定到容器启动。
# 如果你的线上只保留 Docker 镜像，请关闭 RUN_MIGRATE，或改写下面三个命令为自己的迁移方式。
REMOTE_MIGRATE_WORKDIR="${REMOTE_MIGRATE_WORKDIR:-/www/wwwroot/b8aiadmin/server}"
REMOTE_MIGRATE_STATUS_COMMAND="${REMOTE_MIGRATE_STATUS_COMMAND:-}"
REMOTE_MIGRATE_DRY_RUN_COMMAND="${REMOTE_MIGRATE_DRY_RUN_COMMAND:-}"
REMOTE_MIGRATE_COMMAND="${REMOTE_MIGRATE_COMMAND:-}"

# 远程重载后显示的日志行数。
REMOTE_LOG_TAIL="${REMOTE_LOG_TAIL:-80}"

########################################
# 工具函数
########################################

log() {
  printf '\n==> %s\n' "$1"
}

warn() {
  printf '警告：%s\n' "$1" >&2
}

fail() {
  printf '错误：%s\n' "$1" >&2
  exit 1
}

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    fail "缺少命令：$1"
  fi
}

prompt_yes_no() {
  local question="$1"
  local default_answer="$2"
  local answer=""
  local suffix="[y/N]"

  if [[ "$default_answer" == "y" ]]; then
    suffix="[Y/n]"
  fi

  read -r -p "$question $suffix " answer
  answer="${answer:-$default_answer}"

  case "$answer" in
    y|Y|yes|YES) return 0 ;;
    *) return 1 ;;
  esac
}

resolve_bool() {
  local value="$1"
  local fallback="$2"

  value="${value:-$fallback}"
  case "$value" in
    1|y|Y|yes|YES|true|TRUE) printf '1' ;;
    *) printf '0' ;;
  esac
}

shell_quote() {
  local value="$1"
  printf "'%s'" "$(printf '%s' "$value" | sed "s/'/'\\\\''/g")"
}

remote_exec() {
  ssh "$REMOTE_ALIAS" "$1"
}

validate_bin_name() {
  case "$BIN_NAME" in
    ''|'.'|'..'|*/*)
      fail "BIN_NAME 不能为空，也不能包含目录分隔符"
      ;;
  esac
}

safe_name() {
  printf '%s' "$1" | tr '/:@' '___'
}

########################################
# 交互配置
########################################

if [[ -t 0 ]]; then
  INTERACTIVE="${INTERACTIVE:-1}"
else
  INTERACTIVE="${INTERACTIVE:-0}"
fi

if [[ "$INTERACTIVE" == "1" ]]; then
  if [[ -z "$BUILD_ADMIN" ]]; then
    if prompt_yes_no "是否编译 admin 管理端？" "n"; then
      BUILD_ADMIN=1
    else
      BUILD_ADMIN=0
    fi
  fi

  if [[ -z "$BUILD_H5" ]]; then
    if prompt_yes_no "是否编译 uniapp H5？" "n"; then
      BUILD_H5=1
    else
      BUILD_H5=0
    fi
  fi

  if [[ -z "$RUN_MIGRATE" ]]; then
    if prompt_yes_no "是否在自动更新线上镜像时执行数据迁移？默认会先 status 和 dry-run" "n"; then
      RUN_MIGRATE=1
    else
      RUN_MIGRATE=0
    fi
  fi
else
  BUILD_ADMIN="${BUILD_ADMIN:-0}"
  BUILD_H5="${BUILD_H5:-0}"
  RUN_MIGRATE="${RUN_MIGRATE:-0}"
fi

BUILD_ADMIN="$(resolve_bool "$BUILD_ADMIN" 0)"
BUILD_H5="$(resolve_bool "$BUILD_H5" 0)"
RUN_MIGRATE="$(resolve_bool "$RUN_MIGRATE" 0)"
REMOTE_RELOAD_CONTAINER="$(resolve_bool "$REMOTE_RELOAD_CONTAINER" 1)"
INSTALL_CA_CERTIFICATES="$(resolve_bool "$INSTALL_CA_CERTIFICATES" 1)"
INCLUDE_PRODUCTION_ENV="$(resolve_bool "$INCLUDE_PRODUCTION_ENV" 1)"

validate_bin_name

IMAGE_REF="${IMAGE_NAME}:${IMAGE_TAG}"
SAFE_IMAGE="$(safe_name "$IMAGE_NAME")"
SAFE_TAG="$(safe_name "$IMAGE_TAG")"
RELEASE_DIR="$RELEASE_ROOT/${SAFE_IMAGE}_${SAFE_TAG}"
CONTEXT_DIR="$RELEASE_DIR/context"
IMAGE_TAR_PATH="$RELEASE_DIR/${SAFE_IMAGE}_${SAFE_TAG}.tar"
REMOTE_IMAGE_TAR="$REMOTE_UPLOAD_DIR/$(basename "$IMAGE_TAR_PATH")"

log "Docker 发布配置"
echo "镜像：$IMAGE_REF"
echo "目标平台：$DOCKER_PLATFORM"
echo "二进制文件名：$BIN_NAME"
echo "构建 admin：$BUILD_ADMIN"
echo "构建 H5：$BUILD_H5"
echo "线上迁移：$RUN_MIGRATE"
echo "打包生产 .env：$INCLUDE_PRODUCTION_ENV"
echo "本地生产 .env：$LOCAL_PRODUCTION_ENV_FILE"
echo "服务器别名：$REMOTE_ALIAS"
echo "上传目录：$REMOTE_UPLOAD_DIR"
echo "容器名称：$CONTAINER_NAME"
echo "端口映射：$HOST_PORT:$APP_PORT"
echo "远程 .env：${REMOTE_ENV_FILE:-未配置}"
echo "远程 runtime：$REMOTE_RUNTIME_DIR"
echo "远程 storage：$REMOTE_STORAGE_DIR"

if [[ "$INTERACTIVE" == "1" ]]; then
  if ! prompt_yes_no "确认开始构建镜像 tar 包？" "y"; then
    echo "已取消"
    exit 0
  fi
fi

########################################
# 本地构建
########################################

require_command php
require_command tar
require_command docker

if [[ "$BUILD_ADMIN" == "1" || "$BUILD_H5" == "1" ]]; then
  require_command pnpm
fi

if ! docker buildx version >/dev/null 2>&1; then
  fail "当前 Docker 不支持 buildx，无法稳定构建 $DOCKER_PLATFORM 镜像"
fi

if [[ "$BUILD_ADMIN" == "1" ]]; then
  log "编译 admin 管理端"
  (
    cd "$FRONTEND_DIR"
    VITE_BASE_URL="$ADMIN_PUBLIC_BASE" pnpm build
  )
else
  log "跳过 admin 编译"
fi

if [[ "$BUILD_H5" == "1" ]]; then
  log "编译 uniapp H5"
  (
    cd "$UNIAPP_DIR"
    VITE_APP_PUBLIC_BASE="$H5_PUBLIC_BASE" pnpm build:h5
  )
else
  log "跳过 uniapp H5 编译"
fi

log "构建 Webman 二进制"
(
  cd "$SERVER_DIR"
  php -d phar.readonly=0 -d memory_limit=-1 webman build:bin --name="$BIN_NAME"
)

BIN_PATH="$SERVER_DIR/build/$BIN_NAME"
[[ -f "$BIN_PATH" ]] || fail "二进制产物不存在：$BIN_PATH"

log "准备 Docker 构建上下文"
rm -rf "$RELEASE_DIR"
mkdir -p "$CONTEXT_DIR/public/.release"
cp "$BIN_PATH" "$CONTEXT_DIR/server"
chmod +x "$CONTEXT_DIR/server"

if [[ "$INCLUDE_PRODUCTION_ENV" == "1" ]]; then
  [[ -f "$LOCAL_PRODUCTION_ENV_FILE" ]] || fail "生产环境配置不存在：$LOCAL_PRODUCTION_ENV_FILE"
  log "复制生产环境配置为镜像内 /app/.env"
  cp "$LOCAL_PRODUCTION_ENV_FILE" "$CONTEXT_DIR/.env"
else
  warn "未打包生产 .env，容器运行时必须通过挂载 /app/.env 或环境变量提供配置"
fi

if [[ -d "$FRONTEND_DIR/dist" ]]; then
  log "压缩 admin 静态资源"
  tar -czf "$CONTEXT_DIR/public/.release/admin.tar.gz" -C "$FRONTEND_DIR/dist" .
else
  warn "admin dist 不存在，镜像不会包含 admin 静态资源：$FRONTEND_DIR/dist"
fi

if [[ -d "$UNIAPP_DIR/dist/build/h5" ]]; then
  log "压缩 uniapp H5 静态资源"
  tar -czf "$CONTEXT_DIR/public/.release/h5.tar.gz" -C "$UNIAPP_DIR/dist/build/h5" .
else
  warn "uniapp H5 dist 不存在，镜像不会包含 H5 静态资源：$UNIAPP_DIR/dist/build/h5"
fi

cat > "$CONTEXT_DIR/docker-entrypoint.sh" <<'ENTRYPOINT'
#!/usr/bin/env sh

set -eu

APP_DIR="/app"
PUBLIC_DIR="$APP_DIR/public"
RELEASE_DIR="$PUBLIC_DIR/.release"

log() {
  printf '\n==> %s\n' "$1"
}

archive_checksum() {
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$1" | awk '{print $1}'
  else
    cksum "$1" | awk '{print $1}'
  fi
}

extract_static() {
  name="$1"
  archive="$2"
  target="$3"
  enabled="$4"

  if [ "$enabled" != "1" ]; then
    return 0
  fi

  if [ ! -f "$archive" ]; then
    return 0
  fi

  checksum="$(archive_checksum "$archive")"
  marker="$target/.release.checksum"
  if [ -f "$marker" ] && [ "$(cat "$marker")" = "$checksum" ]; then
    return 0
  fi

  log "释放 ${name} 静态资源"
  tmp="${target}.tmp.$$"
  rm -rf "$tmp"
  mkdir -p "$tmp"
  tar -xzf "$archive" -C "$tmp"
  rm -rf "$target"
  mv "$tmp" "$target"
  printf '%s' "$checksum" > "$marker"
}

mkdir -p "$PUBLIC_DIR" "$APP_DIR/runtime"

extract_static "admin" "$RELEASE_DIR/admin.tar.gz" "$PUBLIC_DIR/admin" "${B8_EXTRACT_ADMIN:-1}"
extract_static "h5" "$RELEASE_DIR/h5.tar.gz" "$PUBLIC_DIR/h5" "${B8_EXTRACT_H5:-1}"

if [ "${B8_KEEP_STATIC_ARCHIVES:-1}" != "1" ]; then
  rm -f "$RELEASE_DIR/admin.tar.gz" "$RELEASE_DIR/h5.tar.gz"
fi

if [ "$#" -eq 0 ]; then
  set -- start
fi

exec "$APP_DIR/server" "$@"
ENTRYPOINT

chmod +x "$CONTEXT_DIR/docker-entrypoint.sh"

cat > "$CONTEXT_DIR/Dockerfile" <<EOF
FROM $DOCKER_BASE_IMAGE

WORKDIR /app

ENV TZ=Asia/Shanghai \\
    B8_EXTRACT_ADMIN=1 \\
    B8_EXTRACT_H5=1 \\
    B8_KEEP_STATIC_ARCHIVES=1
EOF

if [[ "$INSTALL_CA_CERTIFICATES" == "1" ]]; then
  cat >> "$CONTEXT_DIR/Dockerfile" <<'EOF'

RUN if command -v apt-get >/dev/null 2>&1; then \
      apt-get update \
      && apt-get install -y --no-install-recommends ca-certificates tzdata \
      && rm -rf /var/lib/apt/lists/*; \
    fi
EOF
fi

cat >> "$CONTEXT_DIR/Dockerfile" <<EOF

COPY server /app/server
$(if [[ "$INCLUDE_PRODUCTION_ENV" == "1" ]]; then printf 'COPY .env /app/.env'; fi)
COPY public /app/public
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /app/server /usr/local/bin/docker-entrypoint \\
    && mkdir -p /app/runtime /app/public/storage \\
    && chmod -R 0775 /app/runtime /app/public

EXPOSE $APP_PORT

ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]
CMD ["start"]
EOF

log "构建 Docker 镜像"
docker buildx build \
  --platform "$DOCKER_PLATFORM" \
  --load \
  -t "$IMAGE_REF" \
  "$CONTEXT_DIR"

log "导出 Docker 镜像 tar 包"
docker save -o "$IMAGE_TAR_PATH" "$IMAGE_REF"

echo "镜像 tar 包：$IMAGE_TAR_PATH"

########################################
# 远程更新
########################################

if [[ -z "$AUTO_REMOTE_UPDATE" && "$INTERACTIVE" == "1" ]]; then
  if prompt_yes_no "是否自动上传并更新线上镜像？" "n"; then
    AUTO_REMOTE_UPDATE=1
  else
    AUTO_REMOTE_UPDATE=0
  fi
else
  AUTO_REMOTE_UPDATE="${AUTO_REMOTE_UPDATE:-0}"
fi

AUTO_REMOTE_UPDATE="$(resolve_bool "$AUTO_REMOTE_UPDATE" 0)"

default_migrate_status_command() {
  printf 'cd %s && php webman b8:migrate:status' "$(shell_quote "$REMOTE_MIGRATE_WORKDIR")"
}

default_migrate_dry_run_command() {
  printf 'cd %s && php webman b8:migrate --dry-run' "$(shell_quote "$REMOTE_MIGRATE_WORKDIR")"
}

default_migrate_command() {
  printf 'cd %s && php webman b8:migrate' "$(shell_quote "$REMOTE_MIGRATE_WORKDIR")"
}

run_remote_migrations() {
  [[ "$RUN_MIGRATE" == "1" ]] || return 0

  local status_command="${REMOTE_MIGRATE_STATUS_COMMAND:-$(default_migrate_status_command)}"
  local dry_run_command="${REMOTE_MIGRATE_DRY_RUN_COMMAND:-$(default_migrate_dry_run_command)}"
  local migrate_command="${REMOTE_MIGRATE_COMMAND:-$(default_migrate_command)}"

  log "执行线上迁移 status"
  remote_exec "$status_command"

  log "执行线上迁移 dry-run"
  remote_exec "$dry_run_command"

  if [[ "$INTERACTIVE" == "1" ]]; then
    if ! prompt_yes_no "确认执行线上真实迁移？请确认数据库已备份" "n"; then
      fail "已取消线上迁移，当前容器未重载"
    fi
  fi

  log "执行线上真实迁移"
  remote_exec "$migrate_command"
}

reload_remote_container() {
  if [[ "$REMOTE_RELOAD_CONTAINER" != "1" ]]; then
    log "跳过远程容器重建"
    echo "镜像已 load 到服务器，但未停止或重建容器。"
    return 0
  fi

  local env_arg=""
  local network_arg=""
  local command_arg=""

  if [[ -n "$REMOTE_ENV_FILE" ]]; then
    env_arg="--env-file $(shell_quote "$REMOTE_ENV_FILE")"
  fi

  if [[ -n "$REMOTE_DOCKER_NETWORK" ]]; then
    network_arg="--network $(shell_quote "$REMOTE_DOCKER_NETWORK")"
  fi

  if [[ -n "$REMOTE_CONTAINER_COMMAND" ]]; then
    command_arg="$REMOTE_CONTAINER_COMMAND"
  fi

  log "重建远程容器"
  remote_exec "$(cat <<EOF
set -Eeuo pipefail
mkdir -p $(shell_quote "$REMOTE_RUNTIME_DIR") $(shell_quote "$REMOTE_STORAGE_DIR")
if [ -n $(shell_quote "$REMOTE_ENV_FILE") ]; then
  test -f $(shell_quote "$REMOTE_ENV_FILE")
fi
docker stop $(shell_quote "$CONTAINER_NAME") >/dev/null 2>&1 || true
docker rm $(shell_quote "$CONTAINER_NAME") >/dev/null 2>&1 || true
docker run -d \\
  --name $(shell_quote "$CONTAINER_NAME") \\
  --restart $(shell_quote "$DOCKER_RESTART_POLICY") \\
  $env_arg \\
  -p $(shell_quote "$HOST_PORT:$APP_PORT") \\
  -v $(shell_quote "$REMOTE_RUNTIME_DIR:/app/runtime") \\
  -v $(shell_quote "$REMOTE_STORAGE_DIR:/app/public/storage") \\
  $network_arg \\
  $REMOTE_DOCKER_RUN_ARGS \\
  $(shell_quote "$IMAGE_REF") $command_arg
docker ps --filter name=^/$(shell_quote "$CONTAINER_NAME")\$
docker logs --tail=$(shell_quote "$REMOTE_LOG_TAIL") $(shell_quote "$CONTAINER_NAME")
EOF
)"
}

if [[ "$AUTO_REMOTE_UPDATE" == "1" ]]; then
  require_command ssh
  require_command scp

  log "创建远程上传目录"
  remote_exec "mkdir -p $(shell_quote "$REMOTE_UPLOAD_DIR")"

  log "上传镜像 tar 包"
  scp "$IMAGE_TAR_PATH" "$REMOTE_ALIAS:$REMOTE_UPLOAD_DIR/"

  log "加载远程 Docker 镜像"
  remote_exec "docker load -i $(shell_quote "$REMOTE_IMAGE_TAR")"

  run_remote_migrations
  reload_remote_container

  log "远程镜像更新完成"
else
  log "跳过自动更新线上镜像"
  echo "如需手动更新，可上传 tar 包后在服务器执行：docker load -i $(basename "$IMAGE_TAR_PATH")"
fi

log "完成"

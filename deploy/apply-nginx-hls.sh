#!/usr/bin/env bash
# Helper script to install the HLS nginx site config.
# Run as root (or with sudo): sudo bash apply-nginx-hls.sh

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_SRC="$SCRIPT_DIR/nginx/ezway-hls.conf"
DEST_DIR="/etc/nginx/sites-available"
ENABLED_DIR="/etc/nginx/sites-enabled"
SITE_NAME="ezway-hls.conf"

if [[ ! -f "$CONF_SRC" ]]; then
  echo "Config source not found: $CONF_SRC"
  exit 1
fi

if [[ $(id -u) -ne 0 ]]; then
  echo "This script must be run as root. Use sudo." >&2
  exit 2
fi

mkdir -p "$DEST_DIR"
cp "$CONF_SRC" "$DEST_DIR/$SITE_NAME"
ln -sf "$DEST_DIR/$SITE_NAME" "$ENABLED_DIR/$SITE_NAME"

nginx -t
if [[ $? -eq 0 ]]; then
  systemctl reload nginx
  echo "Deployed $SITE_NAME and reloaded nginx."
else
  echo "nginx config test failed; check $DEST_DIR/$SITE_NAME" >&2
  exit 3
fi

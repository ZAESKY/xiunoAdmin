#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="$ROOT_DIR/vendor/topthink/framework/src/tpl/think_exception.tpl"
PATCH_FILE="$ROOT_DIR/deploy/patches/thinkphp-6.0.16-xss.patch"

EXPECTED_VERSION="v6.0.16"
INSTALLED_VERSION="$(php -r '$lock=json_decode(file_get_contents($argv[1]), true); foreach (($lock["packages"] ?? []) as $package) { if (($package["name"] ?? "") === "topthink/framework") { echo $package["version"] ?? ""; break; }}' "$ROOT_DIR/composer.lock")"

if [ "$INSTALLED_VERSION" != "$EXPECTED_VERSION" ]; then
    echo "拒绝应用补丁：期望 topthink/framework $EXPECTED_VERSION，实际为 ${INSTALLED_VERSION:-未知}" >&2
    exit 1
fi

if grep -q "sprintf('\\\\'%s\\\\' => %s', htmlentities(\$key), \$value)" "$TARGET"; then
    echo "ThinkPHP XSS 补丁已存在"
    exit 0
fi

patch --batch --forward -d "$ROOT_DIR/vendor/topthink/framework" -p1 < "$PATCH_FILE"

grep -q "htmlentities(\$key)" "$TARGET" || {
    echo "ThinkPHP XSS 补丁校验失败" >&2
    exit 1
}

echo "ThinkPHP XSS 补丁应用并校验完成"

#!/usr/bin/env bash
# Drives this plugin through a speedyspec binary, with the WordPress app plugin, against a fake wordpress.org served
# on loopback by PHP's built-in server (tests/serve-api.php). Guzzle comes from this repository's vendor/, which
# `composer install` made; speedyspec's own maps for it are built with `speedyspec composer dump-autoload`.
#
#   SPEEDYSPEC_BIN=/path/to/speedyspec [SPEEDYSPEC_WORDPRESS_PLUGIN=../speedyspec-cli-wordpress] tests/cli.sh
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
bin="${SPEEDYSPEC_BIN:?set SPEEDYSPEC_BIN to a speedyspec binary}"
wordpress="${SPEEDYSPEC_WORDPRESS_PLUGIN:-$here/../speedyspec-cli-wordpress}"
work="$(mktemp -d)"
server=""
cleanup() {
    [ -n "$server" ] && kill "$server" 2>/dev/null || true
    rm -rf "$work"
}
trap cleanup EXIT
fail() { echo "FAIL: $*" >&2; exit 1; }
ok() { echo "ok: $*"; }

zip_dir() {
    php -r '
    $zip = new ZipArchive();
    $zip->open($argv[2], ZipArchive::CREATE);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1], FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $file) {
        $name = substr($file->getPathname(), strlen($argv[1]) + 1);
        $file->isDir() ? $zip->addEmptyDir($name) : $zip->addFile($file->getPathname(), $name);
    }
    $zip->close();' "$1" "$2"
}

mkdir -p "$work/serve/zips" "$work/release/wordpress/wp-content/plugins" "$work/release/wordpress/wp-content/themes"
cp "$wordpress/tests/fixtures/wp-config-sample.php" "$work/release/wordpress/"
zip_dir "$work/release" "$work/serve/latest.zip"
for version in 5.3.4 5.3.5; do
    mkdir -p "$work/ext/akismet-$version/akismet"
    printf '<?php\n/*\nPlugin Name: Akismet Anti-spam\nVersion: %s\n*/\n' "$version" > "$work/ext/akismet-$version/akismet/akismet.php"
    zip_dir "$work/ext/akismet-$version" "$work/serve/zips/akismet.$version.zip"
done
mkdir -p "$work/ext/theme/twentytwentyfive"
printf '/*\nTheme Name: Twenty Twenty-Five\nVersion: 1.2\n*/\n' > "$work/ext/theme/twentytwentyfive/style.css"
zip_dir "$work/ext/theme" "$work/serve/zips/twentytwentyfive.1.2.zip"

port="$(php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); echo explode(":", stream_socket_get_name($s, false))[1];')"
php -S "127.0.0.1:$port" -t "$work/serve" "$here/tests/serve-api.php" >/dev/null 2>&1 &
server=$!
for _ in $(seq 50); do
    curl -fs -o /dev/null "http://127.0.0.1:$port/latest.zip" && break
    sleep 0.1
done

export SPEEDYSPEC_HOME="$work/home"
export SPEEDYSPEC_WORDPRESS_DOWNLOADS="http://127.0.0.1:$port"
export SPEEDYSPEC_WORDPRESS_ORG_API="http://127.0.0.1:$port"
"$bin" --no-plugins composer dump-autoload --working-dir "$here" >/dev/null || fail "composer dump-autoload"
cli() { "$bin" --plugin-dir "$wordpress" --plugin-dir "$here" --no-interaction "$@"; }

cli app install wordpress --driver zip --base "$work/site" --name wp \
    --db-name blog --db-user blogger --db-password pw >"$work/install.log" 2>&1 || fail "app install: $(cat "$work/install.log")"

out="$(cli plugin install akismet --install wp)" || fail "plugin install: $out"
[ "$out" = "installed plugin akismet 5.3.5" ] || fail "plugin install said: $out"
[ "$(cli plugin list --install wp)" = "$(printf 'akismet\tAkismet Anti-spam\t5.3.5')" ] || fail "plugin list"
ok "plugin install and list"

out="$(cli plugin update akismet --install wp)" || fail "plugin update: $out"
[ "$out" = "akismet is already at 5.3.5" ] || fail "plugin update said: $out"
ok "plugin update at the latest version"

cli plugin delete akismet --yes --install wp >/dev/null || fail "plugin delete"
cli plugin install akismet@5.3.4 --install wp >/dev/null || fail "plugin install @5.3.4"
out="$(cli plugin update akismet --install wp)" || fail "plugin update: $out"
[ "$out" = "updated plugin akismet 5.3.4 -> 5.3.5" ] || fail "plugin update said: $out"
ok "an older version installed and updated"

if out="$(cli plugin install not-a-plugin --install wp 2>&1)"; then fail "an unknown plugin installed"; fi
case "$out" in *"no source has the plugin not-a-plugin"*) ;; *) fail "unknown plugin said: $out" ;; esac
ok "an unknown plugin is named"

out="$(cli theme install twentytwentyfive --install wp)" || fail "theme install: $out"
[ -f "$work/site/wp-content/themes/twentytwentyfive/style.css" ] || fail "no theme files"
cli theme delete twentytwentyfive --yes --install wp >/dev/null || fail "theme delete"
[ ! -e "$work/site/wp-content/themes/twentytwentyfive" ] || fail "theme still there"
ok "theme install and delete"
echo "all passed"

#!/usr/bin/env bash

# SPDX-License-Identifier: GPL-3.0-or-later

set -euo pipefail

base_ref=${1:-}
if [[ -z "$base_ref" ]]; then
    echo 'Version bump check skipped outside a pull request.'
    exit 0
fi

git cat-file -e "${base_ref}^{commit}"

functional_paths=(
    ajax
    css
    composer.json
    front
    hook.php
    js
    locales
    plugin.xml
    setup.php
    src
    templates
)

if git diff --quiet "${base_ref}...HEAD" -- "${functional_paths[@]}"; then
    echo 'No functional change requires a version bump.'
    exit 0
fi

base_version=$(git show "${base_ref}:setup.php" | sed -n "s/^define('PLUGIN_CLARUS_VERSION', '\([^']*\)');$/\1/p")
head_version=$(sed -n "s/^define('PLUGIN_CLARUS_VERSION', '\([^']*\)');$/\1/p" setup.php)
plugin_version=$(sed -n 's#^[[:space:]]*<num>\([^<]*\)</num>[[:space:]]*$#\1#p' plugin.xml)

if [[ -z "$base_version" || -z "$head_version" || -z "$plugin_version" ]]; then
    echo 'Unable to read Clarus version metadata.' >&2
    exit 1
fi

if [[ "$head_version" != "$plugin_version" ]]; then
    echo "setup.php (${head_version}) and plugin.xml (${plugin_version}) must match." >&2
    exit 1
fi

if ! php -r 'exit(version_compare($argv[1], $argv[2], ">") ? 0 : 1);' "$head_version" "$base_version"; then
    echo "Functional changes require a version newer than ${base_version}; found ${head_version}." >&2
    exit 1
fi

echo "Functional changes bump Clarus from ${base_version} to ${head_version}."

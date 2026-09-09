#!/usr/bin/env bash

# SPDX-License-Identifier: GPL-3.0-or-later

set -euo pipefail

root_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd -P)
bash "$root_dir/vendor/glpi-project/tools/bin/extract-locales"
sed -i 's/English translations for PACKAGE package\./English translations for Clarus package./' "$root_dir/locales/en_GB.po"
sed -i 's/Project-Id-Version: PACKAGE VERSION/Project-Id-Version: Clarus/' "$root_dir/locales/en_GB.po"

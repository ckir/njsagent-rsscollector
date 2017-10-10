#!/bin/bash
# fail fast
set -e
set -o pipefail

exit 0

cd php
composer install
cd ..



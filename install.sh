#!/bin/bash
# fail fast
set -e
set -o pipefail

npm install
cd php
composer install
cd ..


#!/bin/bash
# fail fast
set -e
set -o pipefail

cd php
echo "Running composer"
composer -q install
cd ..



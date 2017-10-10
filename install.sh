#!/bin/bash
# fail fast
set -e
set -o pipefail

cd php
composer -q install
cd ..



#!/bin/bash

# fail fast
set -e
set -o pipefail

git add .
git commit -m "Initial commit"
git push


#!/bin/bash

# fail fast
set -e
set -o pipefail

sudo rm -Rf /var/www/GITHUB/njsagent-rsscollector/php/Rss/Feed/Reader/cache/zfcache*
git add .
git commit -m "Initial commit"
git push


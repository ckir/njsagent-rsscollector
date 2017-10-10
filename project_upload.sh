#!/bin/bash

# fail fast
set -e
set -o pipefail

sudo rm -Rf /var/www/GITHUB/njsagent-rsscollector/php/Rss/Feed/Reader/cache/zfcache*
git add --all .
git commit -am "Initial commit"
git push


#!/bin/bash
# fail fast
set -e
set -o pipefail

npm install
cd php
composer install
cd ..

export PATH=$PATH:$NJSAGENT_APPROOT/workers/njsagent-rsscollector/node_modules/phantomjs/bin:$PATH:$NJSAGENT_APPROOT/workers/njsagent-rsscollector/node_modules/casperjs/bin


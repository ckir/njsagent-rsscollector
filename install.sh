#!/bin/bash
# fail fast
set -e
set -o pipefail

npm install
cd php
composer install
cd ..

#Put binaries to a path accesible folder
ln -s $NJSAGENT_APPROOT/workers/njsagent-rsscollector/node_modules/phantomjs/bin/phantomjs /app/.heroku/node/bin
ln -s $NJSAGENT_APPROOT/workers/njsagent-rsscollector/node_modules/casperjs/bin/casperjs /app/.heroku/node/bin

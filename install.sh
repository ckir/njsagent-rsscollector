#!/bin/bash
# fail fast
set -e
set -o pipefail

cd php
composer install
cd ..

#Put binaries to a path accesible folder
if [ -d "/app/.heroku/node/bin" ]; then
  ln -s $NJSAGENT_APPROOT/node_apps/node_modules/phantomjs-prebuilt/bin/phantomjs /app/.heroku/node/bin
  ln -s $NJSAGENT_APPROOT/node_apps/node_modules/casperjs/bin/casperjs /app/.heroku/node/bin
fi

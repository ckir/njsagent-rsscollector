#!/bin/bash
# fail fast
set -e
set -o pipefail

exit 0
cd php
composer install
cd ..

#Put binaries to a path accesible folder
# if [ -d "/app/.heroku/node/bin" ]; then
#   ln -s $NJSAGENT_APPROOT/workers/njsagent-rsscollector/node_modules/phantomjs/bin/phantomjs /app/.heroku/node/bin
#   ln -s $NJSAGENT_APPROOT/workers/njsagent-rsscollector/node_modules/casperjs/bin/casperjs /app/.heroku/node/bin
# fi

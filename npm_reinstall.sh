#!/bin/bash

php npmreinstall.php
npm remove socket.io --save
npm install socket.io@0.9.17 --save


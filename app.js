'use strict';
var Promise = require('bluebird');
var childProcess = require('child_process');
var path = require('path');
global.appName = path.basename(__dirname);

var moment = require('moment');

var Logger = require('node-bunyan-gcalendar');
var calendar_level = 'fatal';

var bunyanoptions = {
    name: process.env.HEROKU_APP_NAME + ':' + appName,
    streams: [{
        level: 'debug',
        stream: process.stdout
    }, {
        level: 'debug',
        path: path.resolve(process.env.NJSAGENT_APPROOT + '/logs/' + appName + '.log'),
    }]
};

var log;

new Logger(bunyanoptions, process.env.NBGC_AES, process.env.NBGC_KEY, calendar_level).then(function logOk(l) {

    log = l;
    log.info('Logging started');

    if (!process.env.RSSCOLLECTOR_PGSQL) {
        log.error('Missing RSSCOLLECTOR_PGSQL environment variable');
        process.exit(1);
    } else {
        log.trace('RSSCOLLECTOR_PGSQL=' + process.env.RSSCOLLECTOR_PGSQL);
    }
    if (!process.env.RSSCOLLECTOR_MYSQL) {
        log.error('Missing RSSCOLLECTOR_MYSQL environment variable');
        process.exit(1);
    } else {
        log.trace('RSSCOLLECTOR_MYSQL=' + process.env.RSSCOLLECTOR_MYSQL);
    }

    //
    // Handle signals
    //
    var stopSignals = [
        'SIGHUP', 'SIGINT', 'SIGQUIT', 'SIGILL', 'SIGTRAP', 'SIGABRT',
        'SIGBUS', 'SIGFPE', 'SIGUSR1', 'SIGSEGV', 'SIGUSR2', 'SIGTERM'
    ];
    stopSignals.forEach(function(signal) {
        process.once(signal, function(s) {
            log.info('Got signal, exiting...');
            setTimeout(function() {
                process.exit(1);
            }, 1000);
        });
    });
    process.once('uncaughtException', function(err) {
        log.fatal('Uncaught Exception.');
        log.error(err);
        setTimeout(function() {
            process.exit(1);
        }, 1000);
    });


    promiseWhile(function() {
        // Condition for stopping
        return true;
    }, function() {

        var then = moment().utc().format("DD/MM/YYYY HH:mm:ss");
        log.info('Feeds polling started at ' + then);
      
        // The function to run, should return a promise
        return new Promise(function(resolve, reject) {
            
            feedPoll().then(function() {

                var now = moment().utc().format("DD/MM/YYYY HH:mm:ss");
                var ms = moment.utc(now, "DD/MM/YYYY HH:mm:ss").diff(moment.utc(then, "DD/MM/YYYY HH:mm:ss"));
                var d = moment.duration(ms);
                var s = Math.floor(d.asHours()) + moment.utc(ms).format(":mm:ss");
                log.info('Feeds polling completed in ' + s);
                var next = moment.utc(then, "DD/MM/YYYY HH:mm:ss").add(10, "minutes" ).diff(moment.utc(now, "DD/MM/YYYY HH:mm:ss"));
                if (next < 1) {
                    next = 0;
                }
                d = moment.duration(next);
                s = Math.floor(d.asHours()) + moment.utc(next).format(":mm:ss");
                log.debug('Next poll will start after ' + next + 'ms (' + s + ')');
                setTimeout(function(){
                    resolve();
                }, next);
            });

        });

    });

}, function logNotOK(err) {
    console.error('Logging start failed: ', err);
});

function feedPoll () {

    return new Promise(function(resolve, reject) {

        execPhpFile('FeedPoll.php', []).then(function(data) {         
            resolve(data);
        });

    });

}

function execPhpFile (file, args, options, nolog) {

    var phpexec = process.env.PHPPATH || '/usr/bin/php';

    var execOPtions = {
        encoding: 'utf8',
        timeout: 0,
        maxBuffer: 500 * 1024,
        killSignal: 'SIGTERM',
        cwd: path.resolve(__dirname + '/php/'),
        env: process.env
    };

    options = options || execOPtions;
    args = args || [];
    file = path.resolve(__dirname + '/php/' + file);
    args.unshift(file);

    return new Promise(function(resolve, reject) {
        
        log.trace('Executing ' + phpexec, args);
        childProcess.execFile(phpexec, args, options, function(error, stdout, stderr) {

            if (error) {
                if (!nolog) {
                    log.error('Failed to execute ' + file, error);
                }
                resolve({
                    file: file,
                    stderr: stderr
                });
            } else {
                if (!nolog) {
                    log.trace('Successfully executed ' + file, stdout);
                }
                resolve(stdout);
            }

        });

    });

}

function promiseWhile(condition, action) {
    var resolver = Promise.defer();

    var loop = function() {
        if (!condition()) return resolver.resolve();       
        return Promise.cast(action())
            .then(loop)
            .catch(resolver.reject);
    };

    process.nextTick(loop);

    return resolver.promise;
};
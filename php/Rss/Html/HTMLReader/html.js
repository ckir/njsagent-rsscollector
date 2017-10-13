var utils = require('utils');
var casper = require('casper').create({
    //waitTimeout: 30000,
    stepTimeout: 85000,
    verbose: false,
    pageSettings: {
        loadImages: true, // The WebPage instance used by Casper will
        loadPlugins: true, // use these settings
        viewportSize: {
            width: 1024,
            height: 768
        },
        userAgent: 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36'
    }
});

var results = {};
var s, e;

casper.resourceTimeout = 25000;

casper.onResourceTimeout = function(err) {
    results.error = err;
    console.log(JSON.stringify(results));
  this.exit(2);
};

casper.on('error', function(err) {
    results.error = err;
    console.log(JSON.stringify(results));
    this.exit(3);
});

casper.on('onTimeout', function() {
    results.error = "timeout";
});

casper.on('load.failed', function(obj) {
    results.error = err;
    console.log(JSON.stringify(results));
    this.exit(4);
});

casper.on('url.changed', function(url) {
    results.redirectURL = url;
});

casper.on('step.error', function(err) {
    results.error = err;
    console.log(JSON.stringify(results));
    this.exit(5);
});

casper.on('step.timeout', function(err) {
    results.error = err;
    console.log(JSON.stringify(results));
    this.exit(6);
});

casper.on('load.started', function(requestData, request) {
    //console.log("request url " + requestData.url);
    if (typeof s === 'undefined'){
        s = new Date().getTime();
    }
});

casper.on('load.finished', function(response) {
    //console.log("response url " + response.url);
    e = new Date().getTime();
    results.loadtime = (e - s);
    results.http = this.currentResponse;
});

casper.start(casper.cli.get("url"));

casper.run(function() {
    var self = this;
    var xpath = casper.cli.get("xpath");
    if (xpath) {
        try {
            xpath = xpath.split(';');
            xpath.forEach(function(xp) {
                results.content = self.evaluate(function(xp) {
                    var elements = __utils__.getElementsByXPath(xp);
                    return [].map.call(elements, function(element) {
                        return element.innerText;
                    });
                }, xpath);
            });
        } catch (err) {
            results.error = err;
        }
    } else {
        results.content = self.page.content;
    }
    setTimeout(function() {
        console.log(JSON.stringify(results));
        self.exit();
    }, 2000);
});

// Utility function for retreiving the text value of an array of DOM nodes
function getText ( elems ) {
    var ret = "", elem;

    for ( var i = 0; elems[i]; i++ ) {
        elem = elems[i];

        // Get the text from text nodes and CDATA nodes
        if ( elem.nodeType === 3 || elem.nodeType === 4 ) {
            ret += elem.innerText || elem.textContent || nodeValue || '';

        // Traverse everything else, except comment nodes
        } else if ( elem.nodeType !== 8 ) {
            ret += getText( elem.childNodes );
        }
    }

    return ret;
};
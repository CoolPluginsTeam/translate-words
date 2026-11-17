/** Loads the Google widget after editor HTML processing has finished. */
(function () {
    'use strict';

    if (window.lmatLoadGoogleTranslate) return;
    var pending = null;

    window.lmatLoadGoogleTranslate = function () {
        var ctor = window.google && window.google.translate && window.google.translate.TranslateElement;
        if (typeof ctor === 'function') return Promise.resolve(ctor);
        if (pending) return pending;

        pending = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            var poll;
            var timeout;
            var finished = false;
            function finish(error) {
                if (finished) return;
                finished = true;
                clearInterval(poll);
                clearTimeout(timeout);
                script.onerror = null;
                if (error) {
                    script.remove();
                    pending = null;
                    reject(error);
                } else {
                    resolve(window.google.translate.TranslateElement);
                }
            }
            // Create a classic script at runtime so editor output processing cannot add CORS attributes.
            script.src = 'https://translate.google.com/translate_a/element.js';
            script.async = true;
            script.onerror = function () { finish(new Error('Google Translate script could not be loaded.')); };
            poll = setInterval(function () {
                if (window.google && window.google.translate && typeof window.google.translate.TranslateElement === 'function') finish();
            }, 100);
            timeout = setTimeout(function () { finish(new Error('Google Translate did not become ready.')); }, 15000);
            document.head.appendChild(script);
        });
        return pending;
    };

    // Start early; translation requests handle failures and can retry a failed load.
    window.lmatLoadGoogleTranslate().catch(function () {});
})();

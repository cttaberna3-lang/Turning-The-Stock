/* Saves an offline copy of the board being viewed (run by MainActivity after a page loads online). */
(function () {
  function run(tries) {
    try {
      if (window.__stacksOffline) return;                   // this page is itself an offline copy
      var path = location.pathname.toLowerCase();
      if (/\/(login|admin_login|logout)\.php$/.test(path)) return;
      if (!document.getElementById('app')) return;          // sign-in screen: nothing worth saving
      if (!window.AndroidOffline || !window.AndroidOffline.save) return;

      // wait until the cards are really on screen (not skeletons / still loading), then save
      if (document.readyState !== 'complete' || !window.__stacksReady || document.querySelector('.skeleton')) {
        if (tries < 15) setTimeout(function () { run(tries + 1); }, 800);
        return;
      }

      var origLinks = document.querySelectorAll('link[rel="stylesheet"]');
      var jobs = [];
      for (var i = 0; i < origLinks.length; i++) {
        (function (idx, href) {
          var same = false;
          try { same = new URL(href, location.href).origin === location.origin; } catch (e) {}
          if (!same) { jobs.push(Promise.resolve({ idx: idx, css: null })); return; }
          jobs.push(
            fetch(href, { credentials: 'same-origin' })
              .then(function (r) { return r.ok ? r.text() : null; })
              .then(function (css) { return { idx: idx, css: css, href: href }; })
              .catch(function () { return { idx: idx, css: null }; })
          );
        })(i, origLinks[i].href);
      }

      Promise.all(jobs).then(function (results) {
        var clone = document.documentElement.cloneNode(true);
        var copies = clone.querySelectorAll('link[rel="stylesheet"]');
        results.forEach(function (res) {
          var link = copies[res.idx];
          if (!link || res.css === null || !link.parentNode) return;
          var css = res.css.replace(/url\(\s*(['"]?)(?!data:|https?:|\/\/|#)([^'")]+)\1\s*\)/gi, function (m, q, u) {
            try { return 'url("' + new URL(u, res.href).href + '")'; } catch (e) { return m; }
          });
          var style = document.createElement('style');
          style.textContent = css;
          link.parentNode.replaceChild(style, link);
        });
        // don't freeze dialogs / toasts / entrance animations inside the saved copy
        Array.prototype.forEach.call(clone.querySelectorAll('.overlay.open'), function (el) { el.classList.remove('open'); });
        Array.prototype.forEach.call(clone.querySelectorAll('.toast'), function (el) { el.remove(); });
        Array.prototype.forEach.call(clone.querySelectorAll('.rise'), function (el) { el.classList.remove('rise'); });
        var body = clone.querySelector('body'); if (body) body.classList.remove('modal-open');
        window.AndroidOffline.save(location.href, '<!DOCTYPE html>' + clone.outerHTML);
      }).catch(function () {});
    } catch (e) {}
  }
  run(0);
})();

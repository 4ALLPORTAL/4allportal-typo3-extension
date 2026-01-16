/**
 * Plugin zum direkten Download eines Scripts aus der Dokumentation im Backend.
 * 
 */
(function () {
	if (typeof self === 'undefined' || !self.Prism || !self.document || !document.querySelector) {
		return;
	}

	Prism.plugins.toolbar.registerButton('download-file', function (env) {

		var pre = env.element.parentNode;
		if (!pre || !/pre/i.test(pre.nodeName) || !pre.hasAttribute('data-src') || !pre.hasAttribute('data-nndownload')) {
			return;
		}

		var btn = document.createElement('button');
		btn.textContent = pre.getAttribute('data-download-link-label') || 'Download';

		btn.removeEventListener('click', triggerDownload);
		btn.addEventListener('click', triggerDownload);

		var iv = false;

		function triggerDownload ( e ) {
			e.preventDefault();

			clearTimeout( iv );
			iv = setTimeout(function () {

				var src = pre.getAttribute('data-src');
				var element = document.createElement('a');

				// wird in NnhelpersBackendModule.js gesetzt
				var text = $(env.element).data().processedCode;
				var dataAttr = 'data:text/plain;charset=utf-8,' + encodeURIComponent(text);

				element.setAttribute('href', dataAttr);
				element.setAttribute('download', src);
				element.style.display = 'none';

				document.body.appendChild(element);
				element.click();
				document.body.removeChild(element);

				iv = false;

			}, 100);

			return false;
		}

		return btn;

	});

})();
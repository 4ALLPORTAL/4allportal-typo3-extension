import $ from 'jquery'

let NnhelpersBackendModule = {
	initialized: false
};

NnhelpersBackendModule.init = function() {
	
	if (this.initialized) return;
	this.initialized = true;		

	$(document).on('keyup', '.finder', function () {
		var $input = $(this);
		var sword = $input.val();

		$('article').each(function() {
			var $el = $(this);
			var grep = new RegExp(sword, 'i');
			var found = $el.text().search( grep ) > -1 || sword.length == 0;
			$el.toggleClass( 'd-none', !found );
		});

	});

	var updateIV;

	$(document).on('keyup', '[data-autofill-prefix]', function () {
		clearTimeout( updateIV );
		updateIV = setTimeout(autofill, 500);
	});

	// ------------------------------------------------
	// Alle <code> Inhalte merken
	
	$('code').each(function () {
		$(this).data({
			code: $(this).text(),
			downloadFilename: $(this).parent().data('src') || ''
		});
	});

	// Alle Platzhalter / Marker holen, die im <code> ersetzt werden können
	function getReplaceMap() {
		
		var map = {
			tstamp: new Date()*1
		};

		$('[data-autofill-prefix]').each(function () {

			var $input = $(this);

			// vendor || extkey || field ...    = my_ext
			var autofillPrefix = $input.data().autofillPrefix;

			// vendor-lower || extkey-lower ... = my_ext
			map[autofillPrefix + '-lower'] = $input.val().toLowerCase();

			// vendor-ucc || extkey-ucc ...     = MyExt
			map[autofillPrefix + '-ucc'] = capitalizeFirstLetter( $input.val() );

			// vendor-lower-ucc || extkey-lower-ucc ...  = myExt
			map[autofillPrefix + '-lower-ucc'] = lowerCaseFirstLetter( capitalizeFirstLetter( $input.val() ) );
		});
		
		return map;
	}

	// Im Code Platzhalter ersetzen

	// [tstamp]
	// [vendor-lower] 	[vendor-ucc] 	[vendor-lower-ucc]
	// [ext-lower] 		[ext-ucc]		[ext-lower-ucc]
	// [ext-ext-lower] 	[ext-ext-ucc]	[ext-ext-lower-ucc]
	// [field-lower] 	[field-ucc]		[field-lower-ucc]

	function autofill () {
		var map = getReplaceMap();
		$('code').each(function () {

			// Die ungerenderte Version aus dem Data-Container holen
			var code = $(this).data().code || '';
			var downloadFilename = $(this).data().downloadFilename || '';

			for (var k in map) {
				code = code.split('[' + k + ']').join( map[k] );
				downloadFilename = downloadFilename.split('[' + k + ']').join( map[k] );
			}

			// Platzhalter im <code>-Element ersetzen
			$(this).text( code );

			if (downloadFilename) {
				$(this).closest('pre').attr({'data-src': downloadFilename});
			}

			// und für Prism.js download-plugin die gerenderte Version merken
			$(this).data({processedCode:code});
		});

		if (window.Prism) {
			Prism.highlightAll();
		}
	}

	// my_ext_name => MyExtName
	function capitalizeFirstLetter(string) {
		string = string.split('_');
		for (var i in string) {
			string[i] = string[i].charAt(0).toUpperCase() + string[i].slice(1);
		}
		return string.join('');
	}

	// MyExtName => myExtName
	function lowerCaseFirstLetter(string) {
		return string.charAt(0).toLowerCase() + string.slice(1);
	}

	autofill();
};

$(function () {
	NnhelpersBackendModule.init();
});
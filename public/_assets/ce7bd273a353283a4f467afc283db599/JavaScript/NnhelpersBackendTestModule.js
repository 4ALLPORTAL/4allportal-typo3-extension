import $ from 'jquery'

let NnhelpersBackendTestModule = {
	initialized: false
};

NnhelpersBackendTestModule.init = function() {
	
	if (this.initialized) return;
	this.initialized = true;	

	$(function () {

		var allTests = [];
		$('.test-units .unit').each( function () {
			allTests.push( $(this) );
		});


		function run_next_test() {
			if (!allTests.length) return false;
			var $test = allTests.shift();

			run_test_scope( $test, 'BE', function () {
				run_test_scope( $test, 'FE', function () {
					setTimeout( run_next_test, 200 );
				});
			});

		}
		
		$('.run-test').click(() => {
			$('.test-units .unit .result-scope').remove();
			allTests = [];
			$('.test-units .unit').each( function () {
				allTests.push( $(this) );
			});
			run_next_test();
			return false;
		})

		function build_scope_links() {

			$.each(allTests, function ( cnt, $test ) {
				['FE', 'BE'].forEach((scope) => {
					var isBackend = scope == 'BE';
					var baseUrl = $('.test-units').data( isBackend ? 'backendUrl' : 'frontendUrl');
					var testUrl = baseUrl + '&testID=' + encodeURIComponent($test.data().id);
					var $resultFlag = $('<a href="'+testUrl+'" class="result-scope"><span>'+scope+'</span></a>');
					$test.prepend($resultFlag);
				})
			})
		}

		build_scope_links();

		function run_test_scope ( $test, scope, func ) {

			var isBackend = scope == 'BE';

			var baseUrl = $('.test-units').data( isBackend ? 'backendUrl' : 'frontendUrl');
			var testUrl = baseUrl + '&testID=' + encodeURIComponent($test.data().id);

			var $resultFlag = $('<a href="'+testUrl+'" class="result-scope"><span>'+scope+'</span></a>');
			$test.prepend($resultFlag);
			$resultFlag.removeClass( 'error success' );

			$.getJSON( testUrl ).done( function ( data ) {
				console.log( data );
				var hasErrors = data.errors.length;
				if (hasErrors) $test.addClass('error');
				$resultFlag.toggleClass( 'error', hasErrors );
				$resultFlag.toggleClass( 'success', !hasErrors );
				var result = hasErrors ? data.errors : data.success;
				$test.append( '<div class="' + (hasErrors ? 'error-list' : '') + '"><p><b>' 
					+ (isBackend ? 'Backend:' : 'Frontend') 
					+ '</b></p><ul><li>' + result.join('</li><li>') 
					+ '</li></ul></div>' 
				);
				func();
			}).fail( function () {
				$test.addClass( 'error' );
				$resultFlag.addClass( 'error' );
				func();
			});
		}

   });
};

$(function () {
	NnhelpersBackendTestModule.init();
});
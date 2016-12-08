// Inject to a WebView or in Chrome, run this, export text and parse.
let scrape = function() {
	$('.member-modal').each(function(l) { 
		$.get($(this).prop('href'), function(data) { 
			console.log($($.parseHTML(data)).find('.email').prop('href').replace('mailto:', '')); 
		}); 
	});
};

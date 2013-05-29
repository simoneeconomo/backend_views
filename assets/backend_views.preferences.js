jQuery(document).ready(function($) {
	$('.views-duplicator')
		.symphonyDuplicator({
			orderable: false,
			collapsible: true
		})
		.on('input blur keyup', '.instance input[data-view-name]', function(event) {
			var label = $(this),
				value = label.val();

			// Empty url-parameter
			if(value === '') {
				value = Symphony.Language.get('Untitled');
			}

			// Update title
			label.parents('.instance').find('header strong').text(value);
		});
});

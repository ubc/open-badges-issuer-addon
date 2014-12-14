
/**
 * Returns the version of Internet Explorer or a -1 (indicating the use of another browser).
 * This code was taken from: http://stackoverflow.com/a/17907562/1027723
 */
function getInternetExplorerVersion() {
	var rv = -1;

	if ( navigator.appName == 'Microsoft Internet Explorer' ) {
		var ua = navigator.userAgent;
		var re = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
		if ( re.exec(ua) != null ) {
			rv = parseFloat( RegExp.$1 );
		}
	} else if ( navigator.appName == 'Netscape' ) {
		var ua = navigator.userAgent;
		var re = new RegExp("Trident/.*rv:([0-9]{1,}[\.0-9]{0,})");
		if ( re.exec(ua) != null ) {
			rv = parseFloat( RegExp.$1 );
		}
	}

	return rv;
}

jQuery(document).ready( function($) {
	// Retrieve the list of badges.
	badgeos_backpack_list();

	// When the user clicks on a claim button, issue that badge.
	$('.badgeos_backpack').live( 'click', function( event ) {
		// Prevent any default behaviour on the button.
		event.preventDefault();

		// Don't let them press the button twice, that might cause weird stuff.
		$(this).attr( "disabled", true );

		// Issue the badge they just clicked on.
		issueBadges( [ $(this).attr('data-uid') ] );
	});
	
	// When the user clicks on the claim all button, issue all badges that have been checked.
	$('.badgeos_backpack_all').live( 'click', function( event ) {
		// Prevent any default behaviour on the button.
		event.preventDefault();

		// Collect all badges that have their checkbox checked.
		var values = $('input[name="badgeos_backpack_issues[]"]:checked').map( function() {
			return this.value;
		}).get();

		// Don't let them press the button twice, that might cause weird stuff.
		$(this).attr( "disabled", true );

		// Issue all the collected badges.
		issueBadges(values);
	});

	/**
	 * Retrieve the signed version of each url provided, from the server.
	 * @param urls the URLs that need to be retrieved.
	 * @param callback the function to execute once we've retrieved all assertions.
	 */
	function getSignedAssertions( urls, callback ) {
		var signatures = [];

		// Loop through each url.
		for (var i = urls.length - 1; i >= 0; i--) {
			// Make an AJAX request to retrieve the value of the url.
			$.ajax({
				url: urls[i],
				type: "GET",
				dataType: 'JSON',
				success: function( response ) {
					// Add the response to our list.
					signature.push( response );

					// Once all responses have been collected, they'll have the same length.
					if ( signatures.length == urls.length ) {
						// Then execute the callback.
						callback( signatures );
					}
				}
			});
		};
	}
	
	/**
	 * Issue a list of urls as badges.
	 */
	function issueBadges( urls ) {
		// If we are set to use signed assertions.
		if ( badgeos.use_signed_assertions === true ) {
			// Then retrieve the signed assertions, and pass openModal as the callback.
			getSignedAssertions( urls, openModal );
		} else {
			// Otherwise just issue the badges with the urls directly.
			openModal( urls );
		}
	}

	/**
	 * This opens a window in which the user can claim their badge.
	 */
	function openModal( assertions ) {
		// Issuer API can't do modal in IE https://github.com/mozilla/openbadges/issues/1002
		if ( getInternetExplorerVersion() != -1 ) {
			OpenBadges.issue_no_modal( assertions );
		} else {
			OpenBadges.issue(assertions, function( errors, successes ) {
				handle_backpack_response( errors, successes );
			});
		}
	}
	
	/**
	 * If using a modal, then we can hear back from the claim.
	 * We then record the claims that the user has made, and disable the buttons for anything they claimed.
	 */
	function handle_backpack_response( errors, successes ) {
		$.ajax({
			url: badgeos.ajax_url,
			data: {
				action: 'open_badges_recorder',
				user_id: badgeos.user_id,
				successes: ( successes ) ? successes : false,
				errors: ( errors ) ? errors : false,
			},
			type: "POST",
		    dataType: 'JSON',
			success: function( response ) {
				$('.badgeos_backpack.button').removeAttr('disabled');

				if ( response.data.successes ) {
					var recorded = response.data.successes;
					var recorded_length = recorded.length

					for ( i = 0; i < recorded_length; ++i ) {
						$('*[data-uid="'+recorded[i]+'"]').text( response.data.resend_text );
					}
				}
			}
		});
	}
	
	/**
	 * Get a list of of all badges that this user has earned, and render them on the page.
	 */
	function badgeos_backpack_list() {
		$.ajax({
			url: badgeos.json_url,
			data: {
				user_id: badgeos.user_id,
			},
			dataType: 'json',
			success: function( response ) {
				// Hide the loading icon.
				$('.badgeos-spinner').hide();

				if ( response.status !== 'ok' ) {
					console.log('No badge data returned');
				} else {
					// For each achievement, add it's html to the list.
					$.each( response.achievements, function( index, value ) {
						$('#badgeos-achievements-container').append( value.data );
					} );
				}
			}
		});
	}
});

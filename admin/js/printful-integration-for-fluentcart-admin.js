(function( $ ) {
	'use strict';

	function setStatus( $target, message, isError ) {
		if ( !$target.length ) {
			return;
		}

		$target
			.text( message || '' )
			.toggleClass( 'notice-error', !!isError )
			.toggleClass( 'notice-success', !isError );
	}

	function doAction( action, $button, $target, pendingMessage ) {
		if ( !$button.length ) {
			return;
		}

		$button.prop( 'disabled', true );
		setStatus( $target, pendingMessage || '', false );

		$.post(
			PrintfulFluentcart.ajaxUrl,
			{
				action: action,
				nonce: PrintfulFluentcart.nonce
			}
		).done( function( response ) {
			if ( response && response.success ) {
				setStatus( $target, response.data.message, false );
				return;
			}

			setStatus( $target, ( response && response.data && response.data.message ) ? response.data.message : PrintfulFluentcart.messages.error, true );
		} ).fail( function() {
			setStatus( $target, PrintfulFluentcart.messages.error, true );
		} ).always( function() {
			$button.prop( 'disabled', false );
		} );
	}

	$( function() {
		var $testBtn    = $( '#printful_fluentcart_test_connection' );
		var $testStatus = $( '#printful_fluentcart_test_status' );
		var $catalogBtn = $( '#printful_fluentcart_sync_catalog' );
		var $catalogStatus = $( '#printful_fluentcart_catalog_status' );
		var $mappingTable = $( '#printful_fluentcart_variant_table' );
		var $mappingSearch = $( '#printful_fluentcart_mapping_search' );
		var $mappingTextarea = $( '#printful_fluentcart_variant_mapping' );
		var $fetchCarriersBtn = $( '#printful_fluentcart_fetch_carriers' );
		var $originContainer = $( '#printful_origin_profiles' );
		var $addOriginBtn = $( '#printful_add_origin_profile' );
		var designerEnabled = !!$( '#printful_fluentcart_designer_embed' ).length;

		if ( $testBtn.length ) {
			$testBtn.on( 'click', function( e ) {
				e.preventDefault();
				doAction( 'printful_fluentcart_test_connection', $testBtn, $testStatus, PrintfulFluentcart.messages.testing );
			} );
		}

		if ( $catalogBtn.length ) {
			$catalogBtn.on( 'click', function( e ) {
				e.preventDefault();
				doAction( 'printful_fluentcart_sync_catalog', $catalogBtn, $catalogStatus, PrintfulFluentcart.messages.syncing );
			} );
		}

		if ( $mappingTable.length ) {
			$mappingTable.on( 'click', '.printful-fluentcart-add-mapping', function( e ) {
				e.preventDefault();
				var $row = $( this ).closest( 'tr' );
				var variantId = $row.data( 'variant-id' );
				if ( !variantId || !$mappingTextarea.length ) {
					return;
				}

				var current = $mappingTextarea.val();
				var line    = '' + variantId + ':' + variantId;

				// Append with newline if needed.
				if ( current && current.substr( -1 ) !== "\n" ) {
					current += "\n";
				}

				$mappingTextarea.val( current + line );
			} );
		}

		if ( $mappingSearch.length && $mappingTable.length ) {
			$mappingSearch.on( 'keyup change', function() {
				var term = $( this ).val().toLowerCase();
				$mappingTable.find( 'tbody tr' ).each( function() {
					var $row = $( this );
					var hay = (
						( $row.data( 'name' ) || '' ) + ' ' +
						( $row.data( 'sku' ) || '' ) + ' ' +
						( $row.data( 'product' ) || '' )
					).toLowerCase();
					$row.toggle( hay.indexOf( term ) !== -1 );
				} );
			} );
		}

		if ( $fetchCarriersBtn.length ) {
			$fetchCarriersBtn.on( 'click', function( e ) {
				e.preventDefault();
				$fetchCarriersBtn.prop( 'disabled', true ).text( PrintfulFluentcart.messages.testing );
				$.post(
					PrintfulFluentcart.ajaxUrl,
					{
						action: 'printful_fluentcart_fetch_carriers',
						nonce: PrintfulFluentcart.nonce
					}
				).always( function( response ) {
					$fetchCarriersBtn.prop( 'disabled', false ).text( 'Fetch carriers/services from Printful' );
					if ( response && response.success ) {
						window.location.reload();
					} else {
						alert( ( response && response.data && response.data.message ) ? response.data.message : PrintfulFluentcart.messages.error );
					}
				} );
			} );
		}

		if ( $addOriginBtn.length && $originContainer.length ) {
			$addOriginBtn.on( 'click', function( e ) {
				e.preventDefault();
				var idx = $originContainer.find( '.printful-origin-block' ).length;
				var tpl = '<table class="form-table printful-origin-block" role="presentation" style="border:1px solid #e2e8f0;margin-bottom:10px;">' +
					'<tbody>' +
					'<tr><th scope="row"><label>Destination countries</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][countries]" placeholder="US,CA,GB" /></td></tr>' +
					'<tr><th scope="row"><label>Contact name</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][name]" /></td></tr>' +
					'<tr><th scope="row"><label>Company</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][company]" /></td></tr>' +
					'<tr><th scope="row"><label>Address line 1</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][address_1]" /></td></tr>' +
					'<tr><th scope="row"><label>Address line 2</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][address_2]" /></td></tr>' +
					'<tr><th scope="row"><label>City</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][city]" /></td></tr>' +
					'<tr><th scope="row"><label>State/Region</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][state]" /></td></tr>' +
					'<tr><th scope="row"><label>Postcode</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][postcode]" /></td></tr>' +
					'<tr><th scope="row"><label>Country code</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][country]" /></td></tr>' +
					'<tr><th scope="row"><label>Phone</label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][' + idx + '][phone]" /></td></tr>' +
					'</tbody></table>';
				$originContainer.append( tpl );
			} );
		}

		// Designer modal.
		if ( designerEnabled ) {
			var $overlay = $( '<div class="printful-designer-overlay"><div class="printful-designer-frame"><button type="button" class="button button-secondary printful-designer-close">×</button><iframe src="about:blank" allowfullscreen></iframe></div></div>' );
			$( 'body' ).append( $overlay );

			$( document ).on( 'click', '.printful-open-designer', function( e ) {
				e.preventDefault();
				var url = $( this ).data( 'designer-url' );
				if ( !url ) {
					return;
				}
				$overlay.find( 'iframe' ).attr( 'src', url );
				$overlay.fadeIn( 150 );
				$( 'body' ).css( 'overflow', 'hidden' );
			} );

			$overlay.on( 'click', '.printful-designer-close', function() {
				$overlay.fadeOut( 150, function() {
					$overlay.find( 'iframe' ).attr( 'src', 'about:blank' );
				} );
				$( 'body' ).css( 'overflow', '' );
			} );

			$overlay.on( 'click', function( e ) {
				if ( e.target === this ) {
					$overlay.find( '.printful-designer-close' ).trigger( 'click' );
				}
			} );
		}
	} );

})( jQuery );

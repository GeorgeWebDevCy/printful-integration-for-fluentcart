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
	} );

})( jQuery );

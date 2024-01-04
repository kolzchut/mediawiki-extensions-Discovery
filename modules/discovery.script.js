( function ( mw, $ ) {
	'use strict';

	var discovery,
		api = new mw.Api();

	mw.discovery = discovery = {
		MAX_CHARS: 85,
		config: mw.config.get( 'wgDiscoveryConfig' ),
		disabled: mw.config.get('discovery-disabled'),
		template: '<div class="discovery-item"><a class="discovery-link"><div class="discovery-tags"></div><div class="discovery-text"></div></a></div>',

		buildDOM: function ( data ) {
			var $items = $([]);

			if ( !data ) {
				return;
			}

			$.each( data.ads, function ( i, e ) {
				$items = $items.add( discovery.buildDiscoveryItem( e ) );
			} )

			return $items;
		},
		buildDiscoveryItem: function ( item ) {
			var currentItem = $( this.template ),
				itemKeys = [],
				itemText = item.content.length > this.MAX_CHARS ? item.content.substring( 0, this.MAX_CHARS ) + '…' : item.content;

			if ( item.indicators ) {
				itemKeys = Object.keys( item.indicators );
				$.each( itemKeys, function ( i, e ) {
					if ( item.indicators[ e ] === 1 ) {
						currentItem.addClass( 'discovery-item-with-tags' );
						currentItem.find( '.discovery-tags' ).append( '<span class="discovery-tag discovery-tag-' + e + '"></span>' );
					}
				} );
			}

			currentItem.addClass( 'discovery-item-' + item.urlType );
			currentItem.find( '.discovery-link' ).attr( 'href', item.url );
			currentItem.find( '.discovery-text' ).text( itemText );

			if ( item.urlType !== null && item.urlType !== 'internal' ) {
				currentItem.find( '.discovery-text' ).append( '<span class="discovery-urltype discovery-urltype-' + item.urlType + '"></span>' );
			}

			currentItem.data( {
				name: item.name
			} );
			return currentItem;
		},

		trackDiscoveryEvents: function() {
			if ( mw.loader.getState( 'ext.googleUniversalAnalytics.utils' ) === null ) {
				return;
			}
			mw.loader.using( 'ext.googleUniversalAnalytics.utils' ).then( function () {
				$( '.discovery-item' ).each( function( i, e ) {
					$( this ).data( 'position', i + 1 );

					if ( mw.discovery.config.trackImpressions === true ) {
						// Send view hit
						mw.googleAnalytics.utils.recordEvent( {
							eventCategory: 'discovery',
							eventAction: 'impression',
							eventLabel: $( this ).data( 'name' ),
							eventValue: i + 1,
							nonInteraction: true
						} );
					}
				} );

				if ( mw.discovery.config.trackClicks === true ) {
					// And bind another event to a possible click...
					$( '.discovery' ).on( 'click', '.discovery-link', function ( e ) {
						var $item = $( this ).parent();

						mw.googleAnalytics.utils.recordClickEvent( e, {
							eventCategory: 'discovery',
							eventAction: 'click',
							eventLabel: $item.data( 'name' ),
							eventValue: parseInt( $item.data( 'position' ) ),
							nonInteraction: false
						} );
					} );
				}
			} );
		}
	};

	// wgArticleId is 0 for special pages and nonexistent pages
	if ( mw.config.get( 'wgArticleId' ) > 0 && !mw.discovery.disabled) {
		$( document ).ready( function () {
			api.get( {
				action: 'discovery',
				title: mw.config.get( 'wgPageName' )
			} )
			.then( function ( response ) {
				var discoveryDOM;

				if ( response.discovery.ads.length === 0 ) {
					$( '.discovery-wrapper, .discovery' ).addClass( 'discovery-no-ads' );
					return;
				}

				discoveryDOM= mw.discovery.buildDOM( response.discovery );
				$( '.discovery' ).append( discoveryDOM );
				mw.discovery.trackDiscoveryEvents();
			} );
		} );
	}
}( mediaWiki, jQuery ) );

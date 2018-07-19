( function ( mw, $ ) {
	'use strict';

	mw.discovery = {
		MAX_CHARS: 85,
		config: mw.config.get( 'wgDiscoveryConfig' ),
		buildDOM: function ( data ) {
			var finalDOM = $( '<div></div>' );

			if ( !data ) {
				return;
			}

			$.each( data.seeAlso, function ( i, e ) {
				var item = this.buildDiscoveryItem( e );
				finalDOM.append( item );
			}.bind( this ) );

			$.each( data.ads, function ( i, e ) {
				if ( i === 0 ) {
					finalDOM.prepend( this.buildDiscoveryItem( e ) );
				} else {
					finalDOM.append( this.buildDiscoveryItem( e ) );
				}
			}.bind( this ) );

			return finalDOM;
		},
		buildDiscoveryItem: function ( item ) {
			var currentItem = $( this.template ),
				itemKeys = [];

			if ( item.indicators ) {
				itemKeys = Object.keys( item.indicators );
				$.each( itemKeys, function ( i, e ) {
					if ( item.indicators[ e ] === 1 ) {
						currentItem.find( '.discovery-tags' ).append( '<span class="discovery-tag discovery-tag-' + e + '"></span>' );
					}
				} );
			}

			currentItem.find( '.discovery-link' ).attr( 'href', item.url );
			currentItem.find( '.discovery-text' ).text( item.content.length > this.MAX_CHARS ? item.content.substring( 0, this.MAX_CHARS ) + '…' : item.content );

			if ( item.urlType !== null && item.urlType !== 'internal' ) {
				currentItem.find( '.discovery-text' ).append( '<span class="discovery-urltype discovery-urltype-' + item.urlType + '"></span>' );
			}

			currentItem.data( {
				type: item.type || ( item.name ? 'ad' : 'see-also' ),
				name: item.name || item.content
			} );
			return currentItem;
		},
		template: '<div class="discovery-item"><a class="discovery-link"><div class="discovery-tags"></div><div class="discovery-text"></div></a></div>',
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

	if ( mw.config.get( 'wgCanonicalNamespace' ) !== 'Special' ) {
		$( document ).ready( function () {

			$.ajax( {
				method: 'GET',
				data: {
					action: 'discovery',
					title: mw.config.get( 'wgPageName' ),
					format: 'json'
				},
				url: mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php'
			} )
				.then( function ( response ) {
					var discoveryDOM = mw.discovery.buildDOM( response.discovery );
					$( '.discovery' ).append( discoveryDOM );
					mw.discovery.trackDiscoveryEvents();
				} );

		} );
	}

}( mediaWiki, jQuery ) );

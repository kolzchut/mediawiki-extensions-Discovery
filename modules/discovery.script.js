( function () {
	'use strict';

	let discovery;
	const api = new mw.Api();

	mw.discovery = discovery = {
		MAX_CHARS: 85,
		config: mw.config.get( 'wgDiscoveryConfig' ),
		disabled: mw.config.get( 'discovery-disabled' ),
		template: '<div class="discovery-item"><a class="discovery-link"><div class="discovery-tags"></div><div class="discovery-text"></div></a></div>',

		buildDOM: function ( data ) {
			const fragment = document.createDocumentFragment();

			if ( !data ) {
				return;
			}

			data.ads.forEach( ( e ) => {
				fragment.append( discovery.buildDiscoveryItem( e ) );
			} );

			return fragment;
		},
		buildDiscoveryItem: function ( item ) {
			const wrapper = document.createElement( 'div' );
			wrapper.innerHTML = this.template;
			const currentItem = wrapper.firstElementChild;
			const itemText = item.content.length > this.MAX_CHARS ? item.content.slice( 0, Math.max( 0, this.MAX_CHARS ) ) + '…' : item.content;

			if ( item.indicators ) {
				Object.keys( item.indicators ).forEach( ( e ) => {
					if ( item.indicators[ e ] === 1 ) {
						currentItem.classList.add( 'discovery-item-with-tags' );
						currentItem.querySelector( '.discovery-tags' ).insertAdjacentHTML( 'beforeend', '<span class="discovery-tag discovery-tag-' + e + '"></span>' );
					}
				} );
			}

			// The following classes are used here:
			// * discovery-item-internal
			// * discovery-item-blog
			// * discovery-item-external
			currentItem.classList.add( 'discovery-item-' + item.urlType );
			currentItem.querySelector( '.discovery-link' ).setAttribute( 'href', item.url );
			currentItem.querySelector( '.discovery-text' ).textContent = itemText;

			if ( item.urlType !== null && item.urlType !== 'internal' ) {
				currentItem.querySelector( '.discovery-text' ).insertAdjacentHTML( 'beforeend', '<span class="discovery-urltype discovery-urltype-' + item.urlType + '"></span>' );
			}

			currentItem.dataset.name = item.name;
			return currentItem;
		},

		trackDiscoveryEvents: function () {
			Array.prototype.forEach.call( document.querySelectorAll( '.discovery-item' ), ( el, i ) => {
				el.dataset.position = i + 1;

				if ( mw.discovery.config.trackImpressions === true ) {
					// Send view hit using mw.track
					mw.track( 'discovery.impression', {
						name: el.dataset.name,
						position: i + 1
					} );
				}

				if ( mw.discovery.config.trackClicks === true ) {
					// Track clicks on this item's link
					const link = el.querySelector( '.discovery-link' );
					if ( link ) {
						link.addEventListener( 'click', () => {
							mw.track( 'discovery.click', {
								name: el.dataset.name,
								position: i + 1
							} );
						} );
					}
				}
			} );
		}
	};

	// wgArticleId is 0 for special pages and nonexistent pages
	if ( mw.config.get( 'wgArticleId' ) > 0 && !mw.discovery.disabled ) {
		function initializeDiscovery() {
			api.get( {
				action: 'discovery',
				title: mw.config.get( 'wgPageName' )
			} )
				.then( ( response ) => {
					if ( response.discovery.ads.length === 0 ) {
						Array.prototype.forEach.call( document.querySelectorAll( '.discovery-wrapper, .discovery' ), ( el ) => {
							el.classList.add( 'discovery-no-ads' );
						} );
						return;
					}

					const discoveryDOM = mw.discovery.buildDOM( response.discovery );
					document.querySelector( '.discovery' ).append( discoveryDOM );
					mw.discovery.trackDiscoveryEvents();
				} );
		}

		// Use mw.hook for better MediaWiki integration
		mw.hook( 'wikipage.content' ).add( initializeDiscovery );
	}
}() );

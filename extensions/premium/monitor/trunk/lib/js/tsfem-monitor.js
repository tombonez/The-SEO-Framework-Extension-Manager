/**
 * This file holds Monitor extension for The SEO Framework plugin's JS code.
 * Serve JavaScript as an addition, not as an ends or means.
 *
 * @author Sybre Waaijer <https://cyberwire.nl/>
 * @pluginURI <https://wordpress.org/plugins/the-seo-framework-extension-manager/>
 */

/**
 * Monitor extension for The SEO Framework
 * Copyright (C) 2016-2018 Sybre Waaijer, CyberWire (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

// ==ClosureCompiler==
// @compilation_level ADVANCED_OPTIMIZATIONS
// @output_file_name tsfem_e_monitor.min.js
// @externs_url https://raw.githubusercontent.com/google/closure-compiler/master/contrib/externs/jquery-1.9.js
// @externs_url https://raw.githubusercontent.com/sybrew/The-SEO-Framework-Extension-Manager/master/lib/js/tsfem.externs.js
// @externs_url https://raw.githubusercontent.com/sybrew/The-SEO-Framework-Extension-Manager/master/extensions/premium/monitor/trunk/lib/js/externs/tsfem-monitor.externs.js
// ==/ClosureCompiler==
// http://closure-compiler.appspot.com/home

'use strict';

/**
 * Holds tsfem_e_monitor values in an object to avoid polluting global namespace.
 *
 * @since 1.0.0
 *
 * @constructor
 */
window.tsfem_e_monitor = {

	/**
	 * @since 1.0.0
	 * @param {String} nonce Ajax nonce
	 */
	nonce : tsfem_e_monitorL10n.nonce,

	/**
	 * @since 1.0.0
	 * @param {Array} i18n Localized strings
	 */
	i18n : tsfem_e_monitorL10n.i18n,

	/**
	 * @since 1.0.0
	 * @param {Number} rDataTimeout Remote data fetch timeout
	 */
	rDataTimeout : tsfem_e_monitorL10n.remote_data_timeout,

	/**
	 * @since 1.0.0
	 * @param {Number} rCrawlTimeout Remote crawl request timeout
	 */
	rCrawlTimeout : tsfem_e_monitorL10n.remote_crawl_timeout,

	/**
	 * Expands readmore button's content whilst removing button.
	 *
	 * @since 1.0.0
	 *
	 * @function
	 * @param {Object} event jQuery event
	 */
	showReadMore: function( event ) {

		let $parent = jQuery( '#' + event.target.id + '-wrap' ),
			$content = jQuery( '#' + event.target.id + '-content' );

		$parent.remove();
		$content.slideDown( 500 );
	},

	/**
	 * Requests crawl from Monitor API server.
	 *
	 * @since 1.0.0
	 *
	 * @function
	 * @param {!jQuery.event} event
	 * @return {(undefined|null)}
	 */
	requestCrawl: function( event ) {

		let $button = jQuery( event.target );

		if ( $button.prop( 'disabled' ) )
			return;

		let loading = 'tsfem-button-disabled tsfem-button-loading',
			loader = '#tsfem-e-monitor-cp-pane .tsfem-pane-header .tsfem-ajax';

		$button.addClass( loading );
		$button.prop( 'disabled', true );

		//* Reset ajax loader
		tsfem.resetAjaxLoader( loader );

		//* Set ajax loader.
		tsfem.setAjaxLoader( loader );

		jQuery.ajax( {
			method: 'POST',
			url: ajaxurl,
			datatype: 'json',
			data: {
				'action' : 'tsfem_e_monitor_crawl',
				'nonce' : tsfem_e_monitor.nonce,
				'remote_crawl_timeout' : tsfem_e_monitor.rCrawlTimeout,
			},
			timeout: 10000,
			async: true,
		} ).done( function( response ) {

			response = tsfem.convertJSONResponse( response );

			if ( tsfem.debug ) console.log( response );

			let data = response && response.data || void 0,
				type = response && response.type || void 0;

			if ( ! data ) {
				//* Erroneous output.
				tsfem.updatedResponse( loader, 0, tsfem.i18n['InvalidResponse'], 0 );
			} else {
				if ( 'undefined' !== typeof data.status['timeout'] )
					tsfem_e_monitor.rCrawlTimeout = data.status['timeout'];

				let status = data.status['type'],
					notice = data.status['notice'];

				if ( 'success' === status ) {
					tsfem.updatedResponse( loader, 1, notice, 0 );
				} else if ( 'yield_unchanged' === status ) {
					tsfem.updatedResponse( loader, 2, notice, 0 );
				} else if ( 'requires_fix' === status ) {
					tsfem_e_monitor.add_requires_fix( data.status['requires_fix'] );
					tsfem.updatedResponse( loader, 0, notice, 0 );
				} else {
					tsfem.updatedResponse( loader, 0, notice, 0 );
				}
			}
		} ).fail( function( jqXHR, textStatus, errorThrown ) {
			let _error = tsfem.getAjaxError( jqXHR, textStatus, errorThrown );
			tsfem.updatedResponse( loader, 0, _error, 0 );
		} ).always( function() {
			$button.removeClass( loading );
			$button.prop( 'disabled', false );
		} );
	},

	/**
	 * Fetches the data option and returns new values.
	 *
	 * @since 1.0.0
	 *
	 * @function
	 * @param {jQuery.event} event
	 */
	fetchData: function( event ) {

		let $button = jQuery( event.target );

		if ( $button.prop( 'disabled' ) )
			return;

		let loading = 'tsfem-button-disabled tsfem-button-loading',
			loader = '#tsfem-e-monitor-issues-pane .tsfem-pane-header .tsfem-ajax, #tsfem-e-monitor-stats-pane .tsfem-pane-header .tsfem-ajax';

		$button.addClass( loading );
		$button.prop( 'disabled', true );

		//* Reset ajax loader
		tsfem.resetAjaxLoader( loader );

		//* Set ajax loader.
		tsfem.setAjaxLoader( loader );

		let lastCrawled = document.getElementById( 'tsfem-e-monitor-last-crawled' ),
			lastCrawledClass = lastCrawled.classList.contains( 'tsfem-success' ) ? 'tsfem-success' : 'tsfem-error';
		lastCrawled.classList.remove( 'tsfem-success' );
		lastCrawled.classList.remove( 'tsfem-error' );
		lastCrawled.classList.add( 'tsfem-loading' );

		jQuery.ajax( {
			method: 'POST',
			url: ajaxurl,
			datatype: 'json',
			data: {
				'action' : 'tsfem_e_monitor_fetch',
				'nonce' : tsfem_e_monitor.nonce,
				'remote_data_timeout' : tsfem_e_monitor.rDataTimeout,
			},
			timeout: 15000,
			async: true,
		} ).done( function( response ) {

			response = tsfem.convertJSONResponse( response );

			if ( tsfem.debug ) console.log( response );

			let data = response && response.data || void 0,
				type = response && response.type || void 0;

			if ( ! data || ! data.status ) {
				//* Erroneous output.
				tsfem.updatedResponse( loader, 0, tsfem.i18n['InvalidResponse'], 0 );
			} else {
				if ( 'undefined' !== typeof data.status['timeout'] )
					tsfem_e_monitor.rDataTimeout = data.status['timeout'];

				let status = data.status['type'],
					content = data.status['content'],
					notice = data.status['notice'];

				if ( 'success' === status ) {
					let issues = content['issues'],
						stats = content['stats'],
						lc = content['lc'];

					if ( issues['found'] ) {
						//* Expected to be inputting a single div.
						jQuery( '.tsfem-e-monitor-issues-wrap' ).empty().css( 'opacity', 0 ).append( issues.data.wrap ).animate(
							{ 'opacity' : 1 },
							{ queue: true, duration: 250 },
							'swing'
						);

						//* Loop through each issue and slowly insert it.
						jQuery.each( issues.data.info, function( index, value ) {
							setTimeout( function() {
								jQuery( value ).appendTo( '.tsfem-e-monitor-issues-wrap > div' ).css( 'opacity', 0 ).animate(
									{ 'opacity' : 1 },
									{ queue: false, duration: 250 },
									'swing'
								);
							}, 250 * index );
						} );
					} else {
						let issuesOutput = '<div class="tsfem-pane-inner-wrap tsfem-e-monitor-issues-wrap">' + issues.data + '</div>';

						jQuery( '.tsfem-e-monitor-issues-wrap' ).empty().css( 'opacity', 0 ).append( issuesOutput ).animate(
							{ 'opacity' : 1 },
							{ queue: true, duration: 1000 },
							'swing'
						);
					}

					jQuery( '#tsfem-e-monitor-last-crawled' ).replaceWith( jQuery( lc ).css( 'opacity', 0 ) );
					//= Node is gone from memory. Reaccess it.
					jQuery( '#tsfem-e-monitor-last-crawled' ).animate(
						{ 'opacity' : 1 },
						{ queue: true, duration: 1000 },
						'swing'
					);

					jQuery( '.tsfem-e-monitor-stats-wrap' ).empty().css( 'opacity', 0 ).append( stats ).animate(
						{ 'opacity' : 1 },
						{ queue: true, duration: 1000 },
						'swing'
					);
					setTimeout( function() { tsfem.updatedResponse( loader, 1, notice, 0 ); }, 1000 );

					//* Update hover cache.
					tsfem.initDescHover();
				} else if ( 'yield_unchanged' === status ) {
					tsfem.updatedResponse( loader, 2, notice, 0 );
				} else if ( 'requires_fix' === status ) {
					tsfem_e_monitor.add_requires_fix();
					tsfem.updatedResponse( loader, 0, notice, 0 );
				} else {
					tsfem.updatedResponse( loader, 0, notice, 0 );
				}
			}
		} ).fail( function( jqXHR, textStatus, errorThrown ) {
			let _error = tsfem.getAjaxError( jqXHR, textStatus, errorThrown );
			tsfem.updatedResponse( loader, 0, _error, 0 );
		} ).always( function() {
			/**
			 * If the element isn't replaced, this will work as intended.
			 * If the elemnt is replaced, then the replacement is correct.
			 */
			if ( document.body.contains( lastCrawled ) ) {
				lastCrawled.classList.remove( 'tsfem-loading' );
				lastCrawled.classList.add( lastCrawledClass );
			}

			$button.removeClass( loading );
			$button.prop( 'disabled', false );
		} );
	},

	/**
	 * Inserts content fetched from AJAX into the account information wrapper.
	 *
	 * @since 1.0.0
	 *
	 * @param {String} content The inserted content.
	 * @function
	 * @return {Void} If element already exists.
	 */
	add_requires_fix: function() {

		if ( jQuery( '.tsfem-account-fix' ).length > 0 || jQuery( '.tsfem-account-info' ).length < 1 )
			return;

		jQuery.ajax( {
			method: 'POST',
			url: ajaxurl,
			datatype: 'json',
			data: {
				'action' : 'tsfem_e_monitor_get_requires_fix',
				'nonce' : tsfem_e_monitor.nonce,
			},
			timeout: 3000,
			async: true,
		} ).done( function( response ) {

			response = tsfem.convertJSONResponse( response );

			if ( tsfem.debug ) console.log( response );

			let data = response && response.data || void 0,
				type = response && response.type || void 0;

			//* No error handling, as this is invoked automatically.
			if ( data && data.html )
				jQuery( data.html ).insertAfter( '.tsfem-account-info' ).hide().slideDown( 500 );
		} ).fail( function( jqXHR, textStatus, errorThrown ) {
			//* No elaborate handling, as this function is invoked automatically.
			if ( tsfem.debug ) {
				console.log( jqXHR.responseText );
				console.log( errorThrown );
			}
		} );
	},

	/**
	 * Initialises all aspects of the scripts.
	 *
	 * Generally ordered with stuff that inserts new elements into the DOM first,
	 * then stuff that triggers an event on existing DOM elements when ready,
	 * followed by stuff that triggers an event only on user interaction. This
	 * keeps any screen jumping from occuring later on.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} jQ jQuery
	 * @function
	 */
	ready: function( jQ ) {
		// Disable semi-disabled buttons.
		jQ( 'a#tsfem-e-monitor-privacy-readmore' ).on( 'click touchstart MSPointerDown', tsfem_e_monitor.showReadMore );

		// AJAX crawl request.
		jQ( 'a#tsfem-e-monitor-crawl-button' ).on( 'click', tsfem_e_monitor.requestCrawl );

		// AJAX data update.
		jQ( 'a#tsfem-e-monitor-fetch-button' ).on( 'click', tsfem_e_monitor.fetchData );
	}
};
jQuery( tsfem_e_monitor.ready );

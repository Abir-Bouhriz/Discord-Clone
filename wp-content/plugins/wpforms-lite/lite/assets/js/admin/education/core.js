/* global wpforms_builder, wpforms_education */
/**
 * WPForms Education core for Lite.
 *
 * @since 1.6.6
 */

'use strict';

var WPFormsEducation = window.WPFormsEducation || {};

WPFormsEducation.liteCore = window.WPFormsEducation.liteCore || ( function( document, window, $ ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 1.6.6
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.6.6
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.6.6
		 */
		ready: function() {

			app.events();
		},

		/**
		 * Register JS events.
		 *
		 * @since 1.6.6
		 */
		events: function() {

			app.openModalButtonClick();
		},

		/**
		 * Registers click events that should open upgrade modal.
		 *
		 * @since 1.6.6
		 */
		openModalButtonClick: function() {

			$( document ).on(
				'click',
				'.education-modal',
				function( event ) {

					var $this = $( this ),
						name = $this.data( 'name' ),
						utmContent = WPFormsEducation.core.getUTMContentValue( $this );

					if ( $this.data( 'action' ) && [ 'activate', 'install' ].includes( $this.data( 'action' ) ) ) {
						return;
					}

					event.preventDefault();
					event.stopImmediatePropagation();

					if ( $this.hasClass( 'wpforms-add-fields-button' ) ) {
						name  = $this.text();
						name += name.indexOf( wpforms_builder.field ) < 0 ?  ' ' + wpforms_builder.field : '';
					}

					app.upgradeModal( name, utmContent, $this.data( 'license' ), $this.data( 'video' ) );
				}
			);
		},

		/**
		 * Upgrade modal.
		 *
		 * @since 1.6.6
		 *
		 * @param {string} feature    Feature name.
		 * @param {string} utmContent UTM content.
		 * @param {string} type       Feature license type: pro or elite.
		 * @param {string} video      Feature video URL.
		 */
		upgradeModal: function( feature, utmContent, type, video ) {

			// Provide a default value.
			if ( typeof type === 'undefined' || type.length === 0 ) {
				type = 'pro';
			}

			// Make sure we received only supported type.
			if ( $.inArray( type, [ 'pro', 'elite' ] ) < 0 ) {
				return;
			}

			var message = wpforms_education.upgrade[ type ].message.replace( /%name%/g, feature );

			$.alert( {
				title: feature + ' ' + wpforms_education.upgrade[type].title,
				icon: 'fa fa-lock',
				content: message,
				boxWidth: '550px',
				theme: 'modern,wpforms-education',
				closeIcon: true,
				onOpenBefore: function() {

					var videoHtml = ! _.isEmpty( video ) ? '<iframe src="' + video + '" class="feature-video" frameborder="0" allowfullscreen="" width="490" height="276"></iframe>' : '';

					this.$btnc.after( '<div class="discount-note">' + wpforms_education.upgrade_bonus + videoHtml + wpforms_education.upgrade[type].doc + '</div>' );
					this.$body.find( '.jconfirm-content' ).addClass( 'lite-upgrade' );
				},
				buttons : {
					confirm: {
						text    : wpforms_education.upgrade[type].button,
						btnClass: 'btn-confirm',
						keys    : [ 'enter' ],
						action: function() {

							window.open( WPFormsEducation.core.getUpgradeURL( utmContent, type ), '_blank' );
							app.upgradeModalThankYou( type );
						},
					},
				},
			} );
		},

		/**
		 * Upgrade modal second state.
		 *
		 * @since 1.6.6
		 *
		 * @param {string} type Feature license type: pro or elite.
		 */
		upgradeModalThankYou: function( type ) {

			$.alert( {
				title   : false,
				content : wpforms_education.upgrade[type].modal,
				icon    : 'fa fa-info-circle',
				type    : 'blue',
				boxWidth: '565px',
				buttons : {
					confirm: {
						text    : wpforms_education.ok,
						btnClass: 'btn-confirm',
						keys    : [ 'enter' ],
					},
				},
			} );
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPFormsEducation.liteCore.init();

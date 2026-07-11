/* global jQuery, ugNum */
( function ( $ ) {
	'use strict';

	var PER_PAGE = 10;

	var SORTS = [
		{ key: 'popular',    label: 'محبوب‌ترین' },
		{ key: 'bestseller', label: 'پرفروش‌ترین' },
		{ key: 'cheap',      label: 'ارزان‌ترین' },
		{ key: 'expensive',  label: 'گران‌ترین' }
	];

	function sortRows( rows, key ) {
		var r = rows.slice();
		r.sort( function ( a, b ) {
			switch ( key ) {
				case 'cheap':      return a.price - b.price;
				case 'expensive':  return b.price - a.price;
				case 'bestseller': return b.count - a.count;
				default: // popular
					if ( a.pop !== b.pop ) { return a.pop - b.pop; }
					return b.count - a.count;
			}
		} );
		return r;
	}

	$( function () {
		$( '.ug-nums' ).each( function () {
			var $root      = $( this );
			var view       = $root.data( 'view' );
			var $services  = $root.find( '.ug-nums-services' );
			var $countries = $root.find( '.ug-nums-countries' );
			var $hint      = $root.find( '.ug-nums-hint' );
			var $active    = $root.find( '.ug-nums-active' );
			var pollTimer  = null;

			var state = { code: '', name: '', rows: [], sort: 'popular', page: 1 };

			/* ---- service search ---- */
			$root.find( '.ug-nums-search' ).on( 'input', function () {
				var q = ( this.value || '' ).toLowerCase().trim();
				$services.find( '.ug-nums-svc' ).each( function () {
					var hay = $( this ).data( 'search' ) || '';
					$( this ).toggle( '' === q || hay.indexOf( q ) !== -1 );
				} );
			} );

			/* ---- pick a service → load its numbers ---- */
			$services.on( 'click', '.ug-nums-svc', function () {
				var $btn = $( this );
				$services.find( '.ug-nums-svc' ).removeClass( 'active' );
				$btn.addClass( 'active' );
				loadCountries( $btn.data( 'code' ), $btn.data( 'name' ) );
			} );

			function loadCountries( code, name ) {
				state.code = code; state.name = name; state.page = 1; state.rows = [];
				$hint.hide();
				$countries.html( '<div class="ug-nums-loading">در حال دریافت شماره‌ها و قیمت‌ها…</div>' );
				$.post( ugNum.ajaxUrl, {
					action: 'ug_number_countries',
					nonce: ugNum.nonce,
					service: code
				} ).done( function ( res ) {
					if ( ! res || ! res.success ) {
						$countries.html( '<div class="ug-nums-empty">' + ( ( res && res.data && res.data.message ) || 'شماره‌ای موجود نیست.' ) + '</div>' );
						return;
					}
					state.rows = res.data.countries || [];
					renderList();
				} ).fail( function () {
					$countries.html( '<div class="ug-nums-empty">خطا در دریافت اطلاعات. دوباره تلاش کنید.</div>' );
				} );
			}

			function renderList() {
				if ( ! state.rows.length ) {
					$countries.html( '<div class="ug-nums-empty">برای این سرویس شماره‌ای موجود نیست.</div>' );
					return;
				}
				var sorted = sortRows( state.rows, state.sort );
				var pages  = Math.max( 1, Math.ceil( sorted.length / PER_PAGE ) );
				if ( state.page > pages ) { state.page = pages; }
				var start  = ( state.page - 1 ) * PER_PAGE;
				var slice  = sorted.slice( start, start + PER_PAGE );

				var html = '<div class="ug-nums-toolbar2">' +
					'<div class="ug-nums-svc-title">' + esc( state.name ) + ' — ' + esc( String( sorted.length ) ) + ' کشور موجود</div>' +
					'<label class="ug-nums-sort-wrap">مرتب‌سازی: <select class="ug-nums-sort">';
				SORTS.forEach( function ( s ) {
					html += '<option value="' + s.key + '"' + ( s.key === state.sort ? ' selected' : '' ) + '>' + s.label + '</option>';
				} );
				html += '</select></label></div>';

				html += '<div class="ug-nums-country-list">';
				slice.forEach( function ( c, i ) {
					var rank = start + i + 1;
					html += '<div class="ug-num-country">' +
						'<span class="ug-num-rank">' + esc( String( rank ) ) + '</span>' +
						'<span class="ug-num-flag">' + esc( c.flag || '🌐' ) + '</span>' +
						'<span class="ug-num-cname">' + esc( c.fa || c.name ) + '</span>' +
						'<span class="ug-num-stock">موجودی: ' + esc( String( c.count ) ) + '</span>' +
						'<span class="ug-num-price">' + esc( c.price_fmt ) + '</span>' +
						buyBtn( state.code, c ) +
						'</div>';
				} );
				html += '</div>';
				html += pager( pages );
				$countries.html( html );
			}

			function pager( pages ) {
				if ( pages <= 1 ) { return ''; }
				var cur = state.page, html = '<div class="ug-nums-pager">';
				html += '<button type="button" class="ug-page-btn" data-page="' + ( cur - 1 ) + '"' + ( cur <= 1 ? ' disabled' : '' ) + '>‹ قبلی</button>';
				var from = Math.max( 1, cur - 2 ), to = Math.min( pages, cur + 2 );
				if ( from > 1 ) { html += '<button type="button" class="ug-page-btn" data-page="1">1</button>'; if ( from > 2 ) { html += '<span class="ug-page-dots">…</span>'; } }
				for ( var p = from; p <= to; p++ ) {
					html += '<button type="button" class="ug-page-btn' + ( p === cur ? ' active' : '' ) + '" data-page="' + p + '">' + p + '</button>';
				}
				if ( to < pages ) { if ( to < pages - 1 ) { html += '<span class="ug-page-dots">…</span>'; } html += '<button type="button" class="ug-page-btn" data-page="' + pages + '">' + pages + '</button>'; }
				html += '<button type="button" class="ug-page-btn" data-page="' + ( cur + 1 ) + '"' + ( cur >= pages ? ' disabled' : '' ) + '>بعدی ›</button>';
				html += '</div>';
				return html;
			}

			/* ---- sort + pagination events ---- */
			$countries.on( 'change', '.ug-nums-sort', function () {
				state.sort = this.value; state.page = 1; renderList();
			} );
			$countries.on( 'click', '.ug-page-btn', function () {
				var p = parseInt( $( this ).data( 'page' ), 10 );
				if ( ! isNaN( p ) ) { state.page = p; renderList(); window.scrollTo && $countries[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
			} );

			function buyBtn( code, c ) {
				var label = ( state.name || '' ) + ' ' + ( c.fa || c.name );
				if ( 'showcase' === view || ! ugNum.loggedIn ) {
					return '<a class="ug-num-buy" href="' + ugNum.authUrl + '">ورود برای خرید</a>';
				}
				return '<button type="button" class="ug-num-buy" data-code="' + esc( code ) +
					'" data-country="' + esc( String( c.id ) ) +
					'" data-label="' + esc( label.trim() ) + '">خرید شماره</button>';
			}

			/* ---- buy ---- */
			$countries.on( 'click', 'button.ug-num-buy', function () {
				var $btn = $( this );
				if ( $btn.prop( 'disabled' ) ) { return; }
				$btn.prop( 'disabled', true ).text( 'در حال رزرو…' );
				$.post( ugNum.ajaxUrl, {
					action: 'ug_buy_number',
					nonce: ugNum.nonce,
					service: $btn.data( 'code' ),
					country: $btn.data( 'country' ),
					label: $btn.data( 'label' )
				} ).done( function ( res ) {
					if ( res && res.success ) {
						showActive( res.data );
					} else {
						var d = ( res && res.data ) || {};
						alert( d.recharge ? 'موجودی کیف پول کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.' : ( d.message || 'خرید ناموفق بود.' ) );
						$btn.prop( 'disabled', false ).text( 'خرید شماره' );
					}
				} ).fail( function ( x ) {
					var m = 'خرید ناموفق بود.';
					try { m = JSON.parse( x.responseText ).data.message || m; } catch ( e ) {}
					alert( m );
					$btn.prop( 'disabled', false ).text( 'خرید شماره' );
				} );
			} );

			/* ---- active number card + polling ---- */
			function showActive( d ) {
				var html = '<div class="ug-num-active-card" data-order="' + esc( String( d.order_id ) ) + '">' +
					'<div class="ug-num-active-head"><span class="ug-num-active-label">' + esc( d.label || 'شماره مجازی' ) + '</span>' +
					'<span class="ug-num-active-price">' + esc( d.price || '' ) + '</span></div>' +
					'<div class="ug-num-active-number"><span class="ug-num-lbl">شماره:</span> <b class="ug-num-value">' + esc( d.number || '—' ) + '</b>' +
					'<button type="button" class="ug-num-copy" data-copy="' + esc( d.number || '' ) + '">کپی</button></div>' +
					'<div class="ug-num-active-otp"><span class="ug-num-lbl">کد تأیید:</span> <b class="ug-num-otp-value">در انتظار دریافت کد…</b></div>' +
					'<div class="ug-num-active-actions">' +
					'<span class="ug-num-status">در انتظار کد</span>' +
					'<button type="button" class="ug-num-cancel">لغو و بازگشت وجه</button></div>' +
					'</div>';
				$active.prepend( html );
				pollStatus( d.order_id );
			}

			function pollStatus( orderId ) {
				var $card = $active.find( '.ug-num-active-card[data-order="' + orderId + '"]' );
				if ( ! $card.length ) { return; }
				clearTimeout( pollTimer );
				var tick = function () {
					$.post( ugNum.ajaxUrl, {
						action: 'ug_number_status',
						nonce: ugNum.nonce,
						order_id: orderId
					} ).done( function ( res ) {
						if ( ! res || ! res.success ) { return; }
						var d = res.data;
						$card.find( '.ug-num-status' ).text( d.status_label || '' );
						if ( d.number ) { $card.find( '.ug-num-value' ).text( d.number ); $card.find( '.ug-num-copy' ).data( 'copy', d.number ); }
						if ( d.otp ) {
							$card.find( '.ug-num-otp-value' ).html( '<span class="ug-num-code">' + esc( d.otp ) + '</span>' +
								' <button type="button" class="ug-num-copy" data-copy="' + esc( d.otp ) + '">کپی</button>' );
							$card.addClass( 'done' );
							$card.find( '.ug-num-cancel' ).remove();
							return;
						}
						if ( d.status === 'canceled' || d.status === 'failed' || d.status === 'refunded' ) {
							$card.find( '.ug-num-otp-value' ).text( 'لغو شد — وجه بازگردانده شد.' );
							$card.addClass( 'canceled' );
							$card.find( '.ug-num-cancel' ).remove();
							return;
						}
						pollTimer = setTimeout( tick, 5000 );
					} ).fail( function () {
						pollTimer = setTimeout( tick, 8000 );
					} );
				};
				pollTimer = setTimeout( tick, 4000 );
			}

			/* ---- cancel ---- */
			$active.on( 'click', '.ug-num-cancel', function () {
				var $card = $( this ).closest( '.ug-num-active-card' );
				var orderId = $card.data( 'order' );
				if ( ! confirm( 'شماره لغو و مبلغ به کیف پول بازگردانده شود؟' ) ) { return; }
				$( this ).prop( 'disabled', true ).text( 'در حال لغو…' );
				$.post( ugNum.ajaxUrl, {
					action: 'ug_number_cancel',
					nonce: ugNum.nonce,
					order_id: orderId
				} ).done( function ( res ) {
					if ( res && res.success ) {
						$card.addClass( 'canceled' );
						$card.find( '.ug-num-status' ).text( 'لغو شد' );
						$card.find( '.ug-num-otp-value' ).text( 'وجه بازگردانده شد.' );
						$card.find( '.ug-num-cancel' ).remove();
					} else {
						alert( ( res && res.data && res.data.message ) || 'لغو ناموفق بود.' );
					}
				} );
			} );

			/* ---- copy ---- */
			$root.on( 'click', '.ug-num-copy', function () {
				var v = String( $( this ).data( 'copy' ) || '' );
				if ( ! v ) { return; }
				var self = this;
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( v ).then( function () { flashCopied( self ); } );
				} else {
					var t = document.createElement( 'textarea' );
					t.value = v; document.body.appendChild( t ); t.select();
					try { document.execCommand( 'copy' ); } catch ( e ) {}
					document.body.removeChild( t ); flashCopied( self );
				}
			} );

			function flashCopied( el ) {
				var $el = $( el ), old = $el.text();
				$el.text( 'کپی شد!' );
				setTimeout( function () { $el.text( old ); }, 1200 );
			}

			/* auto-open the first service */
			$services.find( '.ug-nums-svc.active' ).first().trigger( 'click' );
		} );
	} );

	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
} )( jQuery );

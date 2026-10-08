/* KSR Debug Log Viewer */
jQuery(document).ready(function($){

	var i18n      = ksrdlv.i18n;
	var $wrap     = $('.ksrdlv-wrap');
	var $notices  = $wrap.find('.ksrdlv-notices');
	var $editor   = $wrap.find('.ksrdlv-editor');
	var $textarea = $wrap.find('.ksrdlv-textarea');
	var $gutter   = $wrap.find('.ksrdlv-gutter');
	var $meta     = $wrap.find('.ksrdlv-meta');
	var $dirty    = $wrap.find('.ksrdlv-dirty');
	var $spinner  = $wrap.find('.ksrdlv-actions .spinner');
	var $buttons  = $wrap.find('.ksrdlv-actions .button');
	var $save     = $wrap.find('.ksrdlv-save');
	var $wrapOpt  = $('#ksrdlv-wrap');
	var $autoOpt  = $('#ksrdlv-autoscroll');

	// Last loaded/saved state of the file
	var state = { content: '', size: 0, mtime: 0, exists: false, truncated: false, writable: true };
	var lineCount = 0;
	var busy = false;

	/* ---------- Helpers ---------- */

	function storageGet( key, fallback ) {
		try {
			var v = window.localStorage.getItem( 'ksrdlv_' + key );
			return v === null ? fallback : v === '1';
		} catch ( e ) {
			return fallback;
		}
	}

	function storageSet( key, value ) {
		try {
			window.localStorage.setItem( 'ksrdlv_' + key, value ? '1' : '0' );
		} catch ( e ) {}
	}

	function sprintf( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return str.replace( /%(\d+)\$s/g, function( m, n ){ return args[ n - 1 ]; } ).replace( /%s/g, function(){ return args.shift(); } );
	}

	function notice( type, message ) {
		var $n = $('<div class="notice is-dismissible"><p></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
		$n.addClass( 'notice-' + type ).find('p').text( message );
		$notices.empty().append( $n );

		if ( type === 'success' ) {
			setTimeout( function(){ $n.fadeOut( 300, function(){ $n.remove(); } ); }, 4000 );
		}
	}

	function errorMessage( xhr ) {
		var res = xhr && ( xhr.responseJSON || xhr );
		return ( res && res.data && res.data.message ) ? res.data.message : i18n.request_failed;
	}

	function setBusy( on ) {
		busy = on;
		$spinner.toggleClass( 'is-active', on );
		$buttons.prop( 'disabled', on );
		if ( !on ) {
			$save.prop( 'disabled', state.truncated || !state.writable );
		}
	}

	function request( action, data ) {
		return $.post( ksrdlv.ajax_url, $.extend( { action: action, nonce: ksrdlv.nonce }, data || {} ) );
	}

	function isDirty() {
		return !state.truncated && $textarea.val() !== state.content;
	}

	function updateDirty() {
		$dirty.prop( 'hidden', !isDirty() );
	}

	function updateMeta( data ) {
		var parts = [];
		if ( data.exists ) {
			parts.push( data.size_h );
			if ( data.mtime_h ) parts.push( data.mtime_h );
		} else {
			parts.push( i18n.not_found );
		}
		$meta.text( '— ' + parts.join( ' · ' ) );
	}

	/* ---------- Editor ---------- */

	function updateGutter( force ) {
		if ( $editor.hasClass('is-wrapped') ) return;

		var count = $textarea.val().split( '\n' ).length;
		if ( !force && count === lineCount ) return;
		lineCount = count;

		var lines = new Array( count );
		for ( var i = 0; i < count; i++ ) {
			lines[i] = i + 1;
		}
		$gutter.text( lines.join( '\n' ) );
		syncScroll();
	}

	function syncScroll() {
		$gutter.scrollTop( $textarea.scrollTop() );
	}

	function fitHeight() {
		var top = $editor.offset().top;
		var height = Math.max( 400, $(window).height() - top - 30 );
		$editor.css( 'height', height + 'px' );
	}

	function setWrap( on ) {
		$editor.toggleClass( 'is-wrapped', on );
		$textarea.attr( 'wrap', on ? 'soft' : 'off' );
		if ( !on ) updateGutter( true );
	}

	function applyLog( data ) {
		state.content   = data.content || '';
		state.size      = data.size;
		state.mtime     = data.mtime;
		state.exists    = data.exists;
		state.truncated = !!data.truncated;
		state.writable  = !!data.writable;

		$textarea.val( state.content );
		$textarea.prop( 'readonly', state.truncated || !state.writable );
		$textarea.attr( 'placeholder', data.exists ? '' : i18n.no_file );
		$save.prop( 'disabled', state.truncated || !state.writable );

		if ( state.truncated ) {
			notice( 'warning', sprintf( i18n.truncated, data.max_h ) );
		}

		updateMeta( data );
		updateGutter( true );
		updateDirty();

		if ( $autoOpt.is(':checked') ) {
			$textarea.scrollTop( $textarea[0].scrollHeight );
		} else {
			$textarea.scrollTop( 0 );
		}
		syncScroll();
	}

	/* ---------- Actions ---------- */

	function loadLog( showNotice ) {
		if ( busy ) return;
		setBusy( true );

		request( 'ksrdlv_get_log' )
			.done( function( res ){
				if ( res.success ) {
					applyLog( res.data );
					if ( showNotice && !res.data.truncated ) notice( 'success', i18n.refreshed );
				} else {
					notice( 'error', errorMessage( res ) );
				}
			})
			.fail( function( xhr ){ notice( 'error', errorMessage( xhr ) ); } )
			.always( function(){ setBusy( false ); } );
	}

	function saveLog( force ) {
		if ( busy || state.truncated || !state.writable ) return;
		setBusy( true );

		var content  = $textarea.val();
		var conflict = false;

		request( 'ksrdlv_save_log', { content: content, size: state.size, mtime: state.mtime, force: force ? 1 : 0 } )
			.done( function( res ){
				if ( res.success ) {
					state.content = content;
					state.size    = res.data.size;
					state.mtime   = res.data.mtime;
					state.exists  = res.data.exists;
					$textarea.attr( 'placeholder', '' );
					updateMeta( res.data );
					updateDirty();
					notice( 'success', i18n.saved );
				} else {
					notice( 'error', errorMessage( res ) );
				}
			})
			.fail( function( xhr ){
				var res = xhr.responseJSON;
				if ( res && res.data && res.data.code === 'conflict' ) {
					conflict = true;
					return;
				}
				notice( 'error', errorMessage( xhr ) );
			})
			.always( function(){
				setBusy( false );

				// File changed on disk since it was loaded, ask before overwriting
				if ( conflict && window.confirm( i18n.conflict ) ) saveLog( true );
			});
	}

	function deleteLog() {
		if ( busy || !window.confirm( i18n.confirm_delete ) ) return;
		setBusy( true );

		request( 'ksrdlv_delete_log' )
			.done( function( res ){
				if ( res.success ) {
					applyLog( res.data );
					notice( 'success', i18n.deleted );
				} else {
					notice( 'error', errorMessage( res ) );
				}
			})
			.fail( function( xhr ){ notice( 'error', errorMessage( xhr ) ); } )
			.always( function(){ setBusy( false ); } );
	}

	function applyStates( states ) {
		$.each( states, function( name, on ){
			$wrap.find('.ksrdlv-toggle[data-constant="' + name + '"]').prop( 'checked', on );
			$wrap.find('[data-badge="' + name + '"]')
				.toggleClass( 'is-on', on )
				.toggleClass( 'is-off', !on )
				.text( on ? i18n.enabled : i18n.disabled );
		});
	}

	function toggleConstant( $input ) {
		var name   = $input.data('constant');
		var value  = $input.is(':checked');
		var $label = $input.closest('.ksrdlv-switch');

		$input.prop( 'disabled', true );
		$label.addClass('is-busy');

		request( 'ksrdlv_toggle_constant', { constant: name, value: value ? 1 : 0 } )
			.done( function( res ){
				if ( res.success ) {
					applyStates( res.data.states );
					notice( 'success', sprintf( i18n.constant_saved, name, value ? i18n.enabled : i18n.disabled ) );
				} else {
					$input.prop( 'checked', !value );
					notice( 'error', errorMessage( res ) );
				}
			})
			.fail( function( xhr ){
				$input.prop( 'checked', !value );
				notice( 'error', errorMessage( xhr ) );
			})
			.always( function(){
				$input.prop( 'disabled', false );
				$label.removeClass('is-busy');
			});
	}

	/* ---------- Events ---------- */

	$textarea.on( 'input', function(){
		updateGutter();
		updateDirty();
	});

	$textarea.on( 'scroll', syncScroll );

	// Tab inserts a tab character, like a code editor
	$textarea.on( 'keydown', function( e ){
		if ( e.key !== 'Tab' || e.shiftKey || e.ctrlKey || e.altKey || e.metaKey || this.readOnly ) return;
		e.preventDefault();

		// execCommand keeps the native undo stack, setRangeText is the fallback
		if ( !document.execCommand || !document.execCommand( 'insertText', false, '\t' ) ) {
			this.setRangeText( '\t', this.selectionStart, this.selectionEnd, 'end' );
			$textarea.trigger('input');
		}
	});

	// Ctrl/Cmd + S saves
	$(document).on( 'keydown', function( e ){
		if ( ( e.ctrlKey || e.metaKey ) && !e.altKey && ( e.key === 's' || e.key === 'S' ) ) {
			e.preventDefault();
			saveLog( false );
		}
	});

	$wrap.on( 'click', '.ksrdlv-save', function(){ saveLog( false ); } );
	$wrap.on( 'click', '.ksrdlv-delete', deleteLog );
	$wrap.on( 'click', '.ksrdlv-refresh', function(){
		if ( isDirty() && !window.confirm( i18n.confirm_reload ) ) return;
		loadLog( true );
	});

	$wrap.on( 'change', '.ksrdlv-toggle', function(){ toggleConstant( $(this) ); } );

	$notices.on( 'click', '.notice-dismiss', function(){
		$(this).closest('.notice').remove();
	});

	$wrapOpt.on( 'change', function(){
		var on = $(this).is(':checked');
		storageSet( 'wrap', on );
		setWrap( on );
	});

	$autoOpt.on( 'change', function(){
		storageSet( 'autoscroll', $(this).is(':checked') );
	});

	$(window).on( 'beforeunload', function( e ){
		if ( !isDirty() ) return;
		e.preventDefault();
		e.originalEvent.returnValue = i18n.unsaved;
		return i18n.unsaved;
	});

	$(window).on( 'resize', fitHeight );

	/* ---------- Init ---------- */

	$wrapOpt.prop( 'checked', storageGet( 'wrap', false ) );
	$autoOpt.prop( 'checked', storageGet( 'autoscroll', true ) );
	setWrap( $wrapOpt.is(':checked') );
	fitHeight();
	loadLog( false );
});
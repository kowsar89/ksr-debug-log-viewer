/* KSR Debug Log Viewer */
jQuery( function( $ ) {
	var i18n = ksrdlv.i18n;

	var $wrap             = $( '.ksrdlv-wrap' );
	var $notices          = $wrap.find( '.ksrdlv-notices' );
	var $editor           = $wrap.find( '.ksrdlv-editor' );
	var $textarea         = $wrap.find( '.ksrdlv-textarea' );
	var $gutter           = $wrap.find( '.ksrdlv-gutter' );
	var $meta             = $wrap.find( '.ksrdlv-meta' );
	var $dirtyIndicator   = $wrap.find( '.ksrdlv-dirty' );
	var $spinner          = $wrap.find( '.ksrdlv-actions .spinner' );
	var $actionButtons    = $wrap.find( '.ksrdlv-actions .button' );
	var $saveButton       = $wrap.find( '.ksrdlv-save' );
	var $wordWrapToggle   = $( '#ksrdlv-wrap' );
	var $autoScrollToggle = $( '#ksrdlv-autoscroll' );

	// The file as last loaded or saved.
	var file = { content: '', size: 0, mtime: 0, truncated: false, writable: true };
	var lineCount = 0;
	var busy = false;

	/* ---------- Helpers ---------- */

	function readOption( key, fallback ) {
		try {
			var value = window.localStorage.getItem( 'ksrdlv_' + key );
			return value === null ? fallback : value === '1';
		} catch ( e ) {
			return fallback;
		}
	}

	function saveOption( key, enabled ) {
		try {
			window.localStorage.setItem( 'ksrdlv_' + key, enabled ? '1' : '0' );
		} catch ( e ) {}
	}

	// Fills %s and %1$s placeholders, like PHP's sprintf().
	function sprintf( format ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return format
			.replace( /%(\d+)\$s/g, function( match, position ) {
				return args[ position - 1 ];
			} )
			.replace( /%s/g, function() {
				return args.shift();
			} );
	}

	function showNotice( type, message ) {
		var $notice = $( '<div class="notice is-dismissible"><p></p><button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button></div>' );
		$notice.addClass( 'notice-' + type );
		$notice.find( 'p' ).text( message );
		$notice.find( '.screen-reader-text' ).text( i18n.dismiss );
		$notices.empty().append( $notice );

		if ( type === 'success' ) {
			setTimeout( function() {
				$notice.fadeOut( 300, function() {
					$notice.remove();
				} );
			}, 4000 );
		}
	}

	// A failure is either a jqXHR or an unsuccessful wp_send_json_error() response.
	function errorData( failure ) {
		var response = failure && ( failure.responseJSON || failure );
		return ( response && response.data ) || {};
	}

	function showError( failure ) {
		showNotice( 'error', errorData( failure ).message || i18n.request_failed );
	}

	// Resolves with the response data, or rejects when the request or the action failed.
	function request( action, data ) {
		return $.post( ksrdlv.ajax_url, $.extend( { action: action, nonce: ksrdlv.nonce }, data ) )
			.then( function( response ) {
				return response.success ? response.data : $.Deferred().reject( response );
			} );
	}

	// Like request(), but locks the toolbar until it's done. The lock is released before
	// the caller's callbacks run, so they can start another action.
	function runFileAction( action, data ) {
		setBusy( true );
		return request( action, data ).always( function() {
			setBusy( false );
		} );
	}

	function setBusy( isBusy ) {
		busy = isBusy;
		$spinner.toggleClass( 'is-active', isBusy );
		$actionButtons.prop( 'disabled', isBusy );
		if ( ! isBusy ) {
			$saveButton.prop( 'disabled', ! isEditable() );
		}
	}

	function isEditable() {
		return ! file.truncated && file.writable;
	}

	function isDirty() {
		return ! file.truncated && $textarea.val() !== file.content;
	}

	function updateDirtyIndicator() {
		$dirtyIndicator.prop( 'hidden', ! isDirty() );
	}

	function updateMeta( stat ) {
		var parts = [];
		if ( stat.exists ) {
			parts.push( stat.size_h );
			if ( stat.mtime_h ) {
				parts.push( stat.mtime_h );
			}
		} else {
			parts.push( i18n.not_found );
		}
		$meta.text( '— ' + parts.join( ' · ' ) );
	}

	/* ---------- Editor ---------- */

	function updateGutter( force ) {
		// The gutter is hidden while wrapping, since wrapped lines don't line up with numbers.
		if ( $editor.hasClass( 'is-wrapped' ) ) {
			return;
		}

		var count = $textarea.val().split( '\n' ).length;
		if ( ! force && count === lineCount ) {
			return;
		}
		lineCount = count;

		var numbers = new Array( count );
		for ( var i = 0; i < count; i++ ) {
			numbers[ i ] = i + 1;
		}
		$gutter.text( numbers.join( '\n' ) );
		syncGutterScroll();
	}

	function syncGutterScroll() {
		$gutter.scrollTop( $textarea.scrollTop() );
	}

	// Stretches the editor to the bottom of the window.
	function fitEditorHeight() {
		var height = Math.max( 400, $( window ).height() - $editor.offset().top - 30 );
		$editor.css( 'height', height + 'px' );
	}

	function setWordWrap( enabled ) {
		$editor.toggleClass( 'is-wrapped', enabled );
		$textarea.attr( 'wrap', enabled ? 'soft' : 'off' );
		if ( ! enabled ) {
			updateGutter( true );
		}
	}

	function showLog( log ) {
		file.content   = log.content || '';
		file.size      = log.size;
		file.mtime     = log.mtime;
		file.truncated = !! log.truncated;
		file.writable  = !! log.writable;

		$textarea.val( file.content );
		$textarea.prop( 'readonly', ! isEditable() );
		$textarea.attr( 'placeholder', log.exists ? '' : i18n.no_file );
		$saveButton.prop( 'disabled', ! isEditable() );

		if ( file.truncated ) {
			showNotice( 'warning', sprintf( i18n.truncated, log.max_h ) );
		}

		updateMeta( log );
		updateGutter( true );
		updateDirtyIndicator();

		$textarea.scrollTop( $autoScrollToggle.is( ':checked' ) ? $textarea[ 0 ].scrollHeight : 0 );
		syncGutterScroll();
	}

	/* ---------- Actions ---------- */

	function loadLog( announce ) {
		if ( busy ) {
			return;
		}

		runFileAction( 'ksrdlv_get_log' )
			.done( function( log ) {
				showLog( log );
				if ( announce && ! log.truncated ) {
					showNotice( 'success', i18n.refreshed );
				}
			} )
			.fail( showError );
	}

	function saveLog( force ) {
		if ( busy || ! isEditable() ) {
			return;
		}

		var content = $textarea.val();

		runFileAction( 'ksrdlv_save_log', { content: content, size: file.size, mtime: file.mtime, force: force ? 1 : 0 } )
			.done( function( stat ) {
				file.content = content;
				file.size    = stat.size;
				file.mtime   = stat.mtime;
				$textarea.attr( 'placeholder', '' );
				updateMeta( stat );
				updateDirtyIndicator();
				showNotice( 'success', i18n.saved );
			} )
			.fail( function( failure ) {
				if ( errorData( failure ).code !== 'conflict' ) {
					showError( failure );
					return;
				}

				// The file changed on disk since it was loaded, so ask before overwriting it.
				if ( window.confirm( i18n.conflict ) ) {
					saveLog( true );
				}
			} );
	}

	function deleteLog() {
		if ( busy || ! window.confirm( i18n.confirm_delete ) ) {
			return;
		}

		runFileAction( 'ksrdlv_delete_log' )
			.done( function( log ) {
				showLog( log );
				showNotice( 'success', i18n.deleted );
			} )
			.fail( showError );
	}

	function showConstantStates( states ) {
		$.each( states, function( name, enabled ) {
			$wrap.find( '.ksrdlv-toggle[data-constant="' + name + '"]' ).prop( 'checked', enabled );
			$wrap.find( '[data-badge="' + name + '"]' )
				.toggleClass( 'is-on', enabled )
				.toggleClass( 'is-off', ! enabled )
				.text( enabled ? i18n.enabled : i18n.disabled );
		} );
	}

	function toggleConstant( $toggle ) {
		var name    = $toggle.data( 'constant' );
		var enabled = $toggle.is( ':checked' );
		var $switch = $toggle.closest( '.ksrdlv-switch' );

		$toggle.prop( 'disabled', true );
		$switch.addClass( 'is-busy' );

		request( 'ksrdlv_toggle_constant', { constant: name, value: enabled ? 1 : 0 } )
			.done( function( data ) {
				showConstantStates( data.states );
				showNotice( 'success', sprintf( i18n.constant_saved, name, enabled ? i18n.enabled : i18n.disabled ) );
			} )
			.fail( function( failure ) {
				$toggle.prop( 'checked', ! enabled );
				showError( failure );
			} )
			.always( function() {
				$toggle.prop( 'disabled', false );
				$switch.removeClass( 'is-busy' );
			} );
	}

	/* ---------- Events ---------- */

	$textarea.on( 'input', function() {
		updateGutter();
		updateDirtyIndicator();
	} );

	$textarea.on( 'scroll', syncGutterScroll );

	// Tab inserts a tab character, like a code editor.
	$textarea.on( 'keydown', function( event ) {
		if ( event.key !== 'Tab' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey || this.readOnly ) {
			return;
		}
		event.preventDefault();

		// execCommand keeps the native undo stack; setRangeText is the fallback.
		if ( ! document.execCommand || ! document.execCommand( 'insertText', false, '\t' ) ) {
			this.setRangeText( '\t', this.selectionStart, this.selectionEnd, 'end' );
			$textarea.trigger( 'input' );
		}
	} );

	// Ctrl/Cmd + S saves.
	$( document ).on( 'keydown', function( event ) {
		if ( ( event.ctrlKey || event.metaKey ) && ! event.altKey && ( event.key === 's' || event.key === 'S' ) ) {
			event.preventDefault();
			saveLog( false );
		}
	} );

	$wrap.on( 'click', '.ksrdlv-save', function() {
		saveLog( false );
	} );

	$wrap.on( 'click', '.ksrdlv-delete', deleteLog );

	$wrap.on( 'click', '.ksrdlv-refresh', function() {
		if ( isDirty() && ! window.confirm( i18n.confirm_reload ) ) {
			return;
		}
		loadLog( true );
	} );

	$wrap.on( 'change', '.ksrdlv-toggle', function() {
		toggleConstant( $( this ) );
	} );

	$notices.on( 'click', '.notice-dismiss', function() {
		$( this ).closest( '.notice' ).remove();
	} );

	$wordWrapToggle.on( 'change', function() {
		var enabled = $( this ).is( ':checked' );
		saveOption( 'wrap', enabled );
		setWordWrap( enabled );
	} );

	$autoScrollToggle.on( 'change', function() {
		saveOption( 'autoscroll', $( this ).is( ':checked' ) );
	} );

	$( window ).on( 'beforeunload', function( event ) {
		if ( ! isDirty() ) {
			return;
		}
		event.preventDefault();
		event.originalEvent.returnValue = i18n.unsaved;
		return i18n.unsaved;
	} );

	$( window ).on( 'resize', fitEditorHeight );

	/* ---------- Init ---------- */

	$wordWrapToggle.prop( 'checked', readOption( 'wrap', true ) );
	$autoScrollToggle.prop( 'checked', readOption( 'autoscroll', true ) );
	setWordWrap( $wordWrapToggle.is( ':checked' ) );
	fitEditorHeight();
	loadLog( false );
} );

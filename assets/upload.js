// Some fields
( function () {
	const maxVisualHeight = 400;
	const minDimension = 64;
	let multiplier = 1;

	let dragMode = 0;

	let visualHeight;
	let visualWidth;

	const maxRes = mw.config.get( 'wgMaxAvatarResolution' );

	let startOffset;
	let startX;
	let startY;

	// Objects
	const $submitButton = OO.ui.infuse( $( '#submit-button' ) );
	const $currentAvatar = $( '<div>' ).append( $.html( '<img class="current-avatar"></img>' ).attr( 'src', mw.config.get( 'wgScriptPath' ) + '/index.php?title=Special:Avatar&wpUsername=' + mw.user.id() + '&wpRes=original&wpNocache&wpVer=' + Math.floor( Date.now() / 1000 ).toString( 16 ) ) );
	const $container = $.html( '<div class="cropper-container" disabled=""></div>' );
	const $imageObj = $.html( '<img src=""></img>' );
	const $selector = $.html( '<div class="cropper"><div class="tl-resizer"></div><div class="tr-resizer"></div><div class="bl-resizer"></div><div class="br-resizer"></div><div class="round-preview"></div></div>' );
	const $msgBelow = $( '<p>' ).text( mw.msg( 'uploadavatar-nofile' ) );
	const $hiddenField = $( '[name=wpAvatar]' );
	const $pickfile = OO.ui.infuse( $( '#select-button' ) );
	const $errorMsg = $( '#errorMsg' );
	const $roundPreview = $selector.find( '.round-preview' );

	// Helper function to limit the selection clip
	function normalizeBound( inner, outer ) {
		if ( inner.left < outer.left ) {
			inner.left = outer.left;
		}
		if ( inner.left + inner.width > outer.left + outer.width ) {
			inner.left = outer.left + outer.width - inner.width;
		}
		if ( inner.top < outer.top ) {
			inner.top = outer.top;
		}
		if ( inner.top + inner.height > outer.top + outer.height ) {
			inner.top = outer.top + outer.height - inner.height;
		}
	}

	function normalizeRange( pt, min, max ) {
		if ( pt < min ) {
			return min;
		} else if ( pt > max ) {
			return max;
		} else {
			return pt;
		}
	}

	// Helper function to easily get bound
	function getBound( obj ) {
		const bound = obj.offset();
		bound.width = obj.width();
		bound.height = obj.height();
		return bound;
	}

	function setBound( obj, bound ) {
		obj.offset( bound );
		obj.width( bound.width );
		obj.height( bound.height );
	}

	function cropImage( image, x, y, dim, targetDim ) {
		if ( dim > 2 * targetDim ) {
			const crop = cropImage( image, x, y, dim, 2 * targetDim );
			return cropImage( crop, 0, 0, 2 * targetDim, targetDim );
		} else {
			const buffer = $( '<canvas>' )
				.attr( 'width', targetDim )
				.attr( 'height', targetDim )[ 0 ];
			buffer
				.getContext( '2d' )
				.drawImage( image, x, y, dim, dim, 0, 0, targetDim, targetDim );
			return buffer;
		}
	}

	// Event listeners
	function updateHidden() {
		const bound = getBound( $selector );
		const outer = getBound( $container );
		// When window is zoomed,
		// width set != width get, so we do some nasty trick here to counter the effect
		const dim = Math.round( ( bound.width - $container.width() + visualWidth ) * multiplier );
		let res = dim;
		if ( res > maxRes ) {
			res = maxRes;
		}
		const image = cropImage( $imageObj[ 0 ],
			( bound.left - outer.left ) * multiplier,
			( bound.top - outer.top ) * multiplier,
			dim, res );
		$hiddenField.val( image.toDataURL() );

		// We have an image here, so we can easily calcaulte the reverse color
		const data = image.getContext( '2d' ).getImageData( 0, 0, res, res ).data;
		let r = 0, g = 0, b = 0, c = 0;
		for ( let i = 0; i < data.length; i += 4 ) {
			c++;
			r += data[ i ];
			g += data[ i + 1 ];
			b += data[ i + 2 ];
		}

		$roundPreview.css( 'border-color', 'rgb(' + ( 256 - Math.round( r / c ) ) + ', ' + ( 256 - Math.round( g / c ) ) + ',' + ( 256 - Math.round( b / c ) ) + ')' );
	}

	function onDragStart( event ) {
		startOffset = getBound( $selector );
		startX = event.pageX;
		startY = event.pageY;
		event.preventDefault();
		event.stopPropagation();

		$( 'body' ).on( 'pointermove', onDrag ).on( 'pointerup', onDragEnd );
	}

	function onDrag( event ) {
		let min, max;
		const bound = getBound( $selector );
		const outer = getBound( $container );
		const point = {
			left: event.pageX,
			top: event.pageY,
			width: 0,
			height: 0
		};
		normalizeBound( point, outer );
		let deltaX = point.left - startX;
		let deltaY = point.top - startY;

		// All min, max below uses X direction as positive
		switch ( dragMode ) {
			case 0:
				bound.left = startOffset.left + deltaX;
				bound.top = startOffset.top + deltaY;
				normalizeBound( bound, outer );
				break;
			case 1:
				min = -Math.min( startOffset.left - outer.left, startOffset.top - outer.top );
				max = startOffset.width - minDimension;
				deltaX = deltaY = normalizeRange( Math.min( deltaX, deltaY ), min, max );
				bound.width = startOffset.width - deltaX;
				bound.left = startOffset.left + startOffset.width - bound.width;
				bound.height = startOffset.height - deltaY;
				bound.top = startOffset.top + startOffset.height - bound.height;
				break;
			case 2:
				min = minDimension - startOffset.width;
				max = Math.min(
					outer.left + outer.width - startOffset.left - startOffset.width,
					startOffset.top - outer.top
				);
				deltaY = -( deltaX = normalizeRange( Math.max( deltaX, -deltaY ), min, max ) );
				bound.width = startOffset.width + deltaX;
				bound.height = startOffset.height - deltaY;
				bound.top = startOffset.top + startOffset.height - bound.height;
				break;
			case 3:
				min = -Math.min(
					startOffset.left - outer.left,
					outer.top + outer.height - startOffset.top - startOffset.height
				);
				max = startOffset.width - minDimension;
				deltaY = -( deltaX = normalizeRange( Math.min( deltaX, -deltaY ), min, max ) );
				bound.width = startOffset.width - deltaX;
				bound.left = startOffset.left + startOffset.width - bound.width;
				bound.height = startOffset.height + deltaY;
				break;
			case 4:
				min = minDimension - startOffset.width;
				max = Math.min(
					outer.left + outer.width - startOffset.left - startOffset.width,
					outer.top + outer.height - startOffset.top - startOffset.height
				);
				deltaX = deltaY = normalizeRange( Math.max( deltaX, deltaY ), min, max );
				bound.width = startOffset.width + deltaX;
				bound.height = startOffset.height + deltaY;
				break;
		}

		setBound( $selector, bound );
		event.preventDefault();
	}

	function onDragEnd( event ) {
		$( 'body' ).off( 'pointermove', onDrag ).off( 'pointerup', onDragEnd );
		event.preventDefault();

		updateHidden();
	}

	function onImageLoaded() {
		const width = $imageObj.width();
		const height = $imageObj.height();

		if ( width < minDimension || height < minDimension ) {
			$errorMsg.text( mw.msg( 'avatar-toosmall' ) );
			$imageObj.attr( 'src', '' );
			$container.attr( 'disabled', '' );
			$currentAvatar.show();
			$msgBelow.text( mw.msg( 'uploadavatar-nofile' ) );
			$submitButton.setDisabled( true );
			return;
		}

		$errorMsg.text( '' );

		$container.removeAttr( 'disabled' );
		$submitButton.setDisabled( false );
		$currentAvatar.hide();
		$msgBelow.text( mw.msg( 'uploadavatar-hint' ) );
		visualHeight = height;
		visualWidth = width;

		if ( visualHeight > maxVisualHeight ) {
			visualHeight = maxVisualHeight;
			visualWidth = visualHeight * width / height;
		}

		multiplier = width / visualWidth;

		$container.width( visualWidth );
		$container.height( visualHeight );
		$imageObj.width( visualWidth );
		$imageObj.height( visualHeight );

		const bound = getBound( $container );
		bound.width = bound.height = Math.min( bound.width, bound.height );
		setBound( $selector, bound );
		updateHidden();
	}

	function onImageLoadingFailed() {
		if ( !$imageObj.attr( 'src' ) ) {
			return;
		}

		$errorMsg.text( mw.msg( 'avatar-invalid' ) );
		$imageObj.attr( 'src', '' );
		$container.attr( 'disabled', '' );
		$submitButton.setDisabled( true );
		$currentAvatar.show();
		$msgBelow.text( mw.msg( 'uploadavatar-nofile' ) );
		return;
	}
	function processTouchEvent( event ) {
		event.preventDefault();
	}
	// Event registration
	$selector.on( 'pointerdown', function ( event ) {
		dragMode = 0;
		onDragStart( event );
	} ).on( 'touchstart', processTouchEvent );
	$selector.find( '.tl-resizer' ).on( 'pointerdown', function ( event ) {
		dragMode = 1;
		onDragStart( event );
	} );
	$selector.find( '.tr-resizer' ).on( 'pointerdown', function ( event ) {
		dragMode = 2;
		onDragStart( event );
	} );
	$selector.find( '.bl-resizer' ).on( 'pointerdown', function ( event ) {
		dragMode = 3;
		onDragStart( event );
	} );
	$selector.find( '.br-resizer' ).on( 'pointerdown', function ( event ) {
		dragMode = 4;
		onDragStart( event );
	} );

	$pickfile.on( 'click', function () {
		const $picker = $.html( '<input type="file"></input>' );
		$picker.on( 'change', function ( event ) {
			const file = event.target.files[ 0 ];
			if ( file ) {
				const reader = new FileReader();
				reader.onloadend = function () {
					$imageObj.width( 'auto' ).height( 'auto' );
					$imageObj.attr( 'src', reader.result );
				};
				reader.readAsDataURL( file );
			}
		} );
		$picker.trigger( 'click' );
		// event.preventDefault();
	} );

	$imageObj
		.on( 'load', onImageLoaded )
		.on( 'error', onImageLoadingFailed );

	// UI modification
	$submitButton.setDisabled( true );
	$container.append( $imageObj );
	$container.append( $selector );
	$hiddenField.before( $currentAvatar );
	$hiddenField.before( $container );
	$hiddenField.before( $msgBelow );
}() );

$( '.mw-userlink' ).each( function ( _, $item ) {
	const $useritem = $( $item );
	$useritem.prepend( $( '<img>' ).addClass( 'userlink-avatar' ).attr( 'src', mw.config.get( 'wgScriptPath' ) + '/extensions/Avatar/avatar.php?user=' + $useritem.text() ) );
} );

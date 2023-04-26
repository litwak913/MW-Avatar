const $img = $( '<img>' ).attr( 'src', mw.config.get( 'wgScriptPath' ) + '/extensions/Avatar/avatar.php?user=' + mw.user.id() );
const $link = $( '<a>' ).attr( 'href', mw.util.getUrl( 'Special:UploadAvatar' ) ).append( $img );
$( '#pt-userpage' ).before( $( '<li>' ).attr( 'id', 'pt-avatar' ).append( $link ) );

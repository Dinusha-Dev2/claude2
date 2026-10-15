( function () {
	var button = document.getElementById( 'pct-scroll-top' );
	if ( ! button ) {
		return;
	}

	function toggle() {
		button.classList.toggle( 'is-visible', window.pageYOffset > 300 );
	}

	function toTop( event ) {
		event.preventDefault();
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	}

	window.addEventListener( 'scroll', toggle, { passive: true } );
	button.addEventListener( 'click', toTop );
	button.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) {
			toTop( event );
		}
	} );
	toggle();
}() );

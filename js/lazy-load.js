/* globals WPComLazyLoadConfig */
(function($, config) {
	lazy_load_init();
	$( 'body' ).bind( 'post-load', lazy_load_init ); // Work with WP.com infinite scroll

	function lazy_load_init() {
		$( 'img[data-lazy-src]' ).bind( 'scrollin', { distance: parseInt( config.distance ) }, function() {
			lazy_load_image( this );
		});
		$( '[data-lazy-background]' ).bind( 'scrollin', { distance: parseInt( config.distanceBG )}, function() {
			lazy_load_background( this );
		});

		// Force load images with the class 'exclude-lazy-load'. Users can add the class to an image and the image will load directly.
		$( 'img.exclude-lazy-load' ).each( function() {
			lazy_load_image(this);
		});

		// We need to force load gallery images in Jetpack Carousel and give up lazy-loading otherwise images don't show up correctly
		$( '[data-carousel-extra]' ).each( function() {
			$( this ).find( 'img[data-lazy-src]' ).each( function() {
				lazy_load_image( this );
			} );
		} );
	}

	/**
	 * Lazy load images in post content which are specified
	 * within a background[-image] style attribute
	 * @author Mat Lipe
	 *
	 * @since 0.7.1
	 *
	 * @param e
	 */
	function lazy_load_background( img ) {
		var $img = jQuery( img ),
			src = $img.attr( 'data-lazy-background' );

		$img.unbind( 'scrollin' ) // remove event binding
			.removeAttr( 'data-lazy-background' )
			.attr( 'data-lazy-loaded', 'true' );

		$img.css( "background-image", 'url(' + src + ')' );
	}

	function lazy_load_image( img ) {
		var $img = jQuery( img ),
			src = $img.attr( 'data-lazy-src' ),
			srcset = $img.attr( 'data-lazy-srcset' ),
			sizes = $img.attr( 'data-lazy-sizes' );

		if ( ! src || 'undefined' === typeof( src ) )
			return;

		$img.unbind( 'scrollin' ) // remove event binding
			.hide()
			.removeAttr( 'data-lazy-src' )
			.removeAttr( 'data-lazy-srcset' )
			.removeAttr( 'data-lazy-sizes' )
			.attr( 'data-lazy-loaded', 'true' );

		img.src = src;
		if ( srcset ) {
			img.srcset = srcset;
		}
		if ( sizes ) {
			img.sizes = sizes;
		}
		$img.fadeIn();
	}
})(jQuery, WPComLazyLoadConfig);

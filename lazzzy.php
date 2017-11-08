<?php
/**
 * @link              http://www.redpik.net
 * @since             1.0.0
 * @package           Lazzzy
 *
 * @wordpress-plugin
 * Plugin Name:       Lazzzy
 * Plugin URI:        lazzzy.com
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Redpik
 * Author URI:        http://www.redpik.net
 * License:           WTFPL
 * License URI:       http://www.wtfpl.net/
 * Text Domain:       lazzzy
 * Domain Path:       /languages
 */



/* méthode?
 * base64 d'un thumbnail généré puis stocké en BdD? non. dossier "lazzzy" dans upload image
 * thumb wp? non
 * placeholder couleur
 * placeholder span avec bg color et picto


 */



// lazysizes inside
// https://github.com/aFarkas/lazysizes
// http://codepen.io/redpik/pen/BWmOyx


// variables à définir :
// classes images (par défaut: "lazyload")
// taille thumbnail (par défaut: 24px)


/*

back:
 - bouton vider le cache?
 - taille par défaut (24px). 0 pour couleur unie?
 - nom de la classe (défaut: lazyload)
 - checkbox ne pas charger lazysizes (par défaut: oui)
 - checkbox ne pas charger le js (par défaut: oui)
 - textarea custom JS
 - checkbox ne pas charger le CSS (par défaut: oui)
 - textarea custom CSS

TODO
* [ ] lazzzy_get_image_id_by_class à remplacer par lazzzy_get_image_id_by_string (au cas où pas de class: on se base sur l'url)
* [ ] <?php echo lazzzy('assets/img/kitten.png'); ?>
* [ ] comme ajax thumbnail rebuild (bouton?)
* [ ] images toutes petites: pas de lazy loading
* [ ] et si on a inséré une photo croppée?
*/



add_filter( 'body_class', function( $classes ) {
	return array_merge( $classes, array( 'no-js' ) );
} );



function lazzzy_thumb() {
	add_image_size('lazzzy-thumbnail', 24, 24, false);
}
add_action('after_setup_theme', 'lazzzy_thumb');

// hide lazzzy-thumbnail size from image size names choose
function lazzzy_remove_image_size_name($all_img_sizes) {
	unset($all_img_sizes['lazzzy-thumbnail']);
	return $all_img_sizes;
}
add_filter('image_size_names_choose', 'lazzzy_remove_image_size_name', 999);



function lazzzy_scripts() {
	wp_enqueue_style( 'lazzzy_front', plugins_url( 'css/lazzzy-front.css', __FILE__ ), array(), '1.0.0' );
	wp_enqueue_script( 'lazysizes', plugins_url( 'js/lazysizes.min.js', __FILE__ ), array(), '3.0.0', true );
	wp_enqueue_script( 'lazzzy_front', plugins_url( 'js/lazzzy-front.js', __FILE__ ), array(), '1.0.0', true );
}
//add_action( 'wp_enqueue_scripts', 'lazzzy_scripts' );





if (!class_exists('Lazzzy')) {

	class Lazzzy {

		const version = '0.0.2';

		function __construct() {
			if (is_admin())
				return;
			add_action('wp_enqueue_scripts', array($this, 'lazzzy_scripts'));

			add_filter('the_content', array($this, 'go_lazzzy'), 99);
			add_filter('post_thumbnail_html', array($this, 'go_lazzzy'), 11);
			add_filter('widget_text', array($this, 'go_lazzzy'), 11);
			add_filter('get_avatar', array($this, 'go_lazzzy' ), 11);
		}

		function lazzzy_scripts() {
			wp_enqueue_script( 'lazysizes',  plugins_url( 'js/lazysizes.min.js', __FILE__ ), array(), '4.0.1', true );
			wp_enqueue_style( 'lazzzy_front', plugins_url( 'css/lazzzy-front.css', __FILE__ ), array(), '1.0.0' );
			wp_enqueue_script( 'lazzzy_front', plugins_url( 'js/lazzzy-front.js', __FILE__ ), array('jquery'), '1.0.0', true );
		}

		function go_lazzzy( $content ) {

			if( is_feed()
			    || is_preview()
			    || strpos( $_SERVER['HTTP_USER_AGENT'], 'Opera Mini' )
				|| intval( get_query_var( 'print' ) ) == 1
				|| intval( get_query_var( 'printpage' ) ) == 1
			) return $content;



			$respReplace = 'data-sizes="auto" data-srcset=';

			$matches = array();
			$skip_images_regex = '/class=".*lazyload.*"/';

			//$placeholder_image = apply_filters( 'lazysizes_placeholder_image', 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' );

			preg_match_all( '/<img\s+.*?>/', $content, $matches );
			$search = array();
			$replace = array();
			foreach ( $matches[0] as $imgHTML ) {
				// Don't to the replacement if a skip class is provided and the image has the class.
				if ( ! ( preg_match( $skip_images_regex, $imgHTML ) ) ) {

					$image_ID = $this->lazzzy_get_image_id_by_class( $imgHTML );
					$placeholder_image = $this->get_lazzzy_image_src($image_ID, 'thumbnail');

					$replaceHTML = preg_replace( '/<img(.*?)src=/i',
						'<img$1src="' . $placeholder_image . '" data-src=', $imgHTML );
					$replaceHTML = preg_replace( '/srcset=/i', $respReplace, $replaceHTML );
					$replaceHTML = $this->_add_class( $replaceHTML, 'lazzzy lazyload' );
					$replaceHTML .= '<noscript>' . $imgHTML . '</noscript>';



					array_push( $search, $imgHTML );
					array_push( $replace, $replaceHTML );
				}
			}
			$content = str_replace( $search, $replace, $content );


			return $content;
		}

		private function _add_class( $htmlString = '', $newClass ) {
			$pattern = '/class="([^"]*)"/';
			// Class attribute set.
			if ( preg_match( $pattern, $htmlString, $matches ) ) {
				$definedClasses = explode( ' ', $matches[1] );
				if ( ! in_array( $newClass, $definedClasses ) ) {
					$definedClasses[] = $newClass;
					$htmlString = str_replace(
						$matches[0],
						sprintf( 'class="%s"', implode( ' ', $definedClasses ) ),
						$htmlString
					);
				}
				// Class attribute not set.
			} else {
				$htmlString = preg_replace( '/(\<.+\s)/', sprintf( '$1class="%s" ', $newClass ), $htmlString );
			}
			return $htmlString;
		}

		private function lazzzy_get_image_id_by_class($classes)
		{
			preg_match('#wp-image-(\d+)#', $classes, $matches);
			return (isset($matches[1]) && $matches[1]) ? $matches[1] : false;
		}

		private function get_lazzzy_image_src($id) {
			$updir = wp_upload_dir();
			$lazzzy_upload_dir = $updir['basedir'].'/lazzzy';

			$origin = wp_get_attachment_image_src($id, 'full');
			$path_parts = pathinfo($origin[0]);
			$ext = $path_parts['extension'];

			$image = wp_get_image_editor($origin[0]);
			if ( ! is_wp_error( $image ) ) {
				$image->resize( 50, 50, false );
				$image->set_quality(50);
				$image->save($lazzzy_upload_dir.'/'.$id.'.'.$ext);

				$image_src = $updir['baseurl'].'/lazzzy/'.$id.'.'.$ext;

				return $image_src;

			}

			return 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
		}

	}

	$lazzzy = new Lazzzy();

}
















// TODO : à mettre en cache absolument
function lazzzy_get_dominant_color($img)
{
	$rTotal = $gTotal = $bTotal = $total = 0;

	for ( $x = 0; $x < imagesx( $img ); $x ++ ) {
		for ( $y = 0; $y < imagesy( $img ); $y ++ ) {
			$rgb = imagecolorat( $img, $x, $y );
			$r   = ( $rgb >> 16 ) & 0xFF;
			$g   = ( $rgb >> 8 ) & 0xFF;
			$b   = $rgb & 0xFF;
			$rTotal += $r;
			$gTotal += $g;
			$bTotal += $b;
			$total ++;
		}
	}
	$rAverage = round( $rTotal / $total );
	$gAverage = round( $gTotal / $total );
	$bAverage = round( $bTotal / $total );

	return array($rAverage, $gAverage, $bAverage);
}

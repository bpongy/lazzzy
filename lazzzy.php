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
 * base64 d'un thumbnail généré puis stocké en BdD?
 * thumb wp
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
* lazzzy_get_image_id_by_class à remplacer par lazzzy_get_image_id_by_string (au cas où pas de class: on se base sur l'url)
* <?php echo lazzzy('assets/img/kitten.png'); ?>
* comme ajax thumbnail rebuild (bouton?)
* ne PAS faire apparaître la taille "lazzzy-thumbnail dans les médias (lors d'une insertion d'img dans un post par exemple)"
*/




add_filter( 'body_class', function( $classes ) {
	return array_merge( $classes, array( 'no-js' ) );
} );




function lazzzy_thumb() {
	add_image_size('lazzzy-thumbnail', 24, 24, false);
}
add_action('after_setup_theme', 'lazzzy_thumb');




function lazzzy_scripts() {
	wp_enqueue_style( 'lazzzy_front', plugins_url( 'css/lazzzy-front.css', __FILE__ ), array(), '1.0.0' );
	wp_enqueue_script( 'lazysizes', plugins_url( 'js/lazysizes.min.js', __FILE__ ), array(), '3.0.0', true );
	wp_enqueue_script( 'lazzzy_front', plugins_url( 'js/lazzzy-front.js', __FILE__ ), array(), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'lazzzy_scripts' );





function cm_add_image_placeholders($content)
{
	// cleaning
	// c'est dégueux et ça risque de faire péter pas mal de trucs.
	// à améliorer
	// il faut se baser sur la function wp_make_content_images_responsive
	$html = preg_replace("/\r\n|\r|\n/im", '', $content);
	$html = preg_replace("/<noscript>.*?<\/noscript>/i", '', $html);



	// img attributes
	
	$expr = '/<img.*?src=[\'"](.*?)[\'"].*?>/i';
	preg_match_all($expr, $html, $matches);
	
	$replacements = array();
	
	foreach ($matches[0] as $k=>$image)
	{
		// $element->class = 'lazyload lazy-opacity ' . $element->class
		$hasClass = strpos($image, ' class="');
		if ($hasClass)
			$new_image = str_replace(' class="', ' class="lazyload ', $image);
		else {
			$new_image = str_replace(' src="', ' class="lazyload" src="', $image);
		}

		// src -> data-src
		$new_image = str_replace(' src=', ' data-src=', $new_image);
		// srcsrcset -> data-srcsrcset
		$new_image = str_replace(' srcset=', ' data-srcset=', $new_image);
		
		
		//$image_ID = lazzzy_get_image_id_by_class($element->class);
		//$image_src = wp_get_attachment_image_src($image_ID, 'lazzzy-thumbnail');
		//$element->src = $image_src[0];
		$get_classes = '/<img.*?class=[\'"](.*?)[\'"].*?>/i';
		preg_match($get_classes, $new_image, $match_classes);

		if (isset($match_classes[1])) {
			$image_ID = lazzzy_get_image_id_by_class( $match_classes[1] );
			$lazzzy_thumbnail = wp_get_attachment_image_src($image_ID, 'lazzzy-thumbnail');

			if (isset($lazzzy_thumbnail[0]) && $lazzzy_thumbnail[0])
				$new_image = str_replace('<img ', '<img src="'.$lazzzy_thumbnail[0].'" ', $new_image);

		}
		
		$replacements[$k] = $new_image;
		
	}

	
	$html = str_replace($matches[0], $replacements, $html);

	return $html;
}

add_filter('the_content', 'cm_add_image_placeholders', 99);









function lazzzy_get_image_id_by_class($classes)
{
	preg_match('#wp-image-(\d+)#', $classes, $matches);
	return (isset($matches[1]) && $matches[1]) ? $matches[1] : false;
}







// shortcode !
function shortcode_lazzzy($attrs){
	extract(shortcode_atts(array(
		'src' => false,
		'class' => 'lazyload '
	), $attrs));

	if (!$src)
		return;





	return '<img src="'.$src.'" class="'.$class.'" alt="" />';
}
add_shortcode('lazzzy', 'shortcode_lazzzy');











function lazzzy_make_thumb($src, $width=24, $quality=20) {

	/* read the source image */
	$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
	if ($ext == 'jpg' || $ext == 'jpeg')
		$source_image = imagecreatefromjpeg($src);
	if ($ext == 'gif')
		$source_image = imagecreatefromgif($src);
	if ($ext == 'png')
		$source_image = imagecreatefrompng($src);

	if (intval($width))
	{
		/* find the "desired height" of this thumbnail, relative to the desired width  */
		$desired_height = floor( $width * imagesy($source_image) / imagesx($source_image) );
	} else {
		$width = 1;
		$desired_height = 1;
	}



	/* create a new, "virtual" image */
	$virtual_image = imagecreatetruecolor($width, $desired_height);

	/* copy source image at a resized size */
	imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $width, $desired_height, imagesx($source_image), imagesy($source_image));

	/* create the physical thumbnail image to its destination */
	ob_start();

	// temp
//	$dominant_color = lazzzy_get_dominant_color($virtual_image);
//	$one_color = imagecolorallocate($virtual_image, $dominant_color[0], $dominant_color[1], $dominant_color[2]);
//	imagefill($virtual_image, 0, 0, $one_color);

	imagepng($virtual_image, null, 0);

	$image_data = ob_get_contents();
	ob_end_clean();
	return base64_encode($image_data);
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

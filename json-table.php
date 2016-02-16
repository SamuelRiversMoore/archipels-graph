<?php include('../atlas/wp-load.php'); 

header('Content-type: application/json');

global $post; // le futur post wordpress natif :)

$elements = [];
$tables = [];
$links = [];
$ids = [];


/*--------------
-----Tables-----
--------------*/
$args = array(
	'post_type' => 'table',
	'posts_per_page' => '-1'
);

$query = new WP_Query($args);


if ( $query->have_posts() ) {
	while ( $query->have_posts() ) {
			$query->the_post();
		
			$ids[] = get_the_ID();

			// maintenant il faut extraire uniquement les données souhaitées
			$tables[] = array('name' => get_the_title(), 'type' => 'table');
	}
}

/*--------------
----Elements----
--------------*/
$args = array(
	'post_type' => 'element',
	'post_status' => 'publish',
	'posts_per_page' => '-1'
);

$query = new WP_Query($args);


if ( $query->have_posts() ) {
	while ( $query->have_posts() ) {
		$query->the_post();
		
		$this_ID = get_the_ID();
		
		$ids[] = $this_ID;

		$elements[] = array('name' => get_the_title(), 'type' => 'element');

		$values = get_single_element_values(['id', 'title', 'abrege', 'coords', 'classes', 'tables-ids']);
		$tables_ids = $values['tables'];
		foreach($tables_ids as $table) {
			$source = $table;
			$target = $this_ID;
			$couple = array($source, $target);
			sort($couple);
			$couple = implode($couple,'-');
			$link = ['source' => array_search($source, $ids), 'target' => array_search($target, $ids), 'weight' => .5, 'type' => 'table-element' ];
			$links[$couple] = $link ;
			foreach($tables_ids as $target) {
				if($target!=$source) {
					$couple = array($source, $target);
					sort($couple);
					$couple = implode($couple,'-');
					$link = ['source' => array_search($source, $ids), 'target' => array_search($target, $ids), 'weight' => 1, 'type' => 'table-table' ];
					if( array_key_exists($couple, $links) ) {
						$links[$couple]['weight']++;
					} else {
						$links[$couple] = $link ;
					}
				}
			}
		}

	}
}

echo json_encode(array('nodes' => array_merge( $tables, $elements ), 'links' => array_values($links) ));

////////////////////////////////////////////////////
// extraction-tri des données d'un element / table
////////////////////////////////////////////////////

function get_single_element_values($fields){ // extraction des champs/valeurs d'un élément
	global $post;
	$id = $post->ID;
	$data = array();

	// source : page-atlas2.php

	if(array_search('id', $fields)!==false) 			$data['id'] = $id;
	if(array_search('date', $fields)!==false) 			$data['date'] = get_field('annee', $id);
	if(array_search('title', $fields)!==false) 			$data['title'] = get_the_title();
			if(array_search('legende', $fields)!==false) 		$data['legende'] = fabriqueLegende($id);
			if(array_search('abrege', $fields)!==false) 		$data['abrege'] = get_field('abrege', $id);	

			if(array_search('large-image', $fields)!==false){
				$image_data = get_field('image', $id);
				$data['image'] = $this->get_image_path('large'); // large pour le panel	
			}
			if(array_search('thumb-image', $fields)!==false){
				$image_data = get_field('image', $id);
				$data['miniature'] = $this->get_image_path('thumbnail'); // la miniature
			}

			if(array_search('classes', $fields)!==false){
				$data['classesA'] = get_field('classification_a', $id);
				$data['classesB'] = implodeIf(' ', get_field('classification_b', $id));
		}

			if(array_search('coords', $fields)!==false){
				$coord = explode(",", get_post_meta( $post->ID,'coord', true ));
				$data['x'] = $coord[0];
				$data['y'] = $coord[1];
		}

			if(array_search('author-id', $fields)!==false)		$data['author-id'] = get_the_author_id();
			if(array_search('author-name', $fields)!==false)	$data['author-name'] = get_the_author();
	
			if(array_search('tables-ids', $fields)!==false)			$data['tables'] = get_element_tables(false);

			return $data;
}
function get_element_tables($details=false){ global $post; // retourne les tables dans lesquelles se trouve l'élément
	$id = $post->ID;

			$args = array (
					'post_type'              => 'table',
					'meta_query'             => array(
							array(
									'key'       => 'elementsList',
									'value'     => $id,
									'compare'   => 'LIKE',
							),
					),
			);

			$query = new WP_Query( $args );

			$j=0;
			$tables = array();
			if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
							$query->the_post();
							// $rgb = substr( get_field('couleur'), 1 );
							// $r = hexdec(substr($rgb,0,2));
							// $g = hexdec(substr($rgb,2,2));
							// $b = hexdec(substr($rgb,4,2));
							// $hsl = rgbToHsl($r, $g, $b);
							// $rotateVal = $hsl['h'];

							// détail de la structure : http://www.archipels.org/wp-json/posts?type=table
							if($details){
								$table = array();
								// $table['id'] = get_the_ID();
								$table['title'] = get_the_title();
								$table['color'] = get_field('couleur');
								$table['link'] = get_permalink(); //$post->link;
								$tables[] = $table;
							} else {
								// juste les ids
								$tables[] = get_the_ID();
							}

							$j++;
					}
			}

			wp_reset_postdata(); // $post redevient le même qu'avant, et pas celui de cette loop-ci

			return $tables;
}

function implodeIf($sep, $obj){
	if(is_array($obj)){
		return implode($sep, $obj);
	} else {
		return $obj;
	}
}
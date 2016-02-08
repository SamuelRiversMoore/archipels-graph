<?php include('atlas/wp-load.php'); 

//header('Content-type: application/json');

/*
Homemade Ajax API
by Gildas P. 2016

Fabrique et affiche un JSON suivant les params demandés,
ou rien du tout

params GET ou POST (pas encore décidé) :
action = {get, set}
type = {element, table, ?} - le type d'item demandé

params optionnels :
id - l'id d'un item spécifique 

*/

class Request {

	public $type = ''; // type d'item demandé (element, table, page, etc)
	public $args = array(); // les arguments pour WP_Query(...)
	public $query;

	public $data = array(); // va se remplir avec les données souhaitées
	public $json = '';

	private $types = ['element', 'table', 'page'];

	function __construct() {

		switch (get_param('action')) {

			case 'get':
				if(get_param('type') && array_search(get_param('type'), $this->types)!==false){
					$this->type = get_param('type');

					// eco('type :');
					// eco($this->type.'');

					// construit les params de la loop à partir des paramètres GET/POST
					$this->build_args();

					// effectue la loop wp et le tri des données suivant le contexte
					$this->do_query();

					// sortie json
					$this->give_json();

				}
				break;
			
			default:
				eco('pas de type...');
				exit();
				break;
		}
	}

	function build_args(){ // construit les arguments de la future loop, à partir des params GET/POST envoyés

		if($this->type != ''){ // le type doit être défini

			if($this->type == 'page'){
				if(get_param('id') && intval(get_param('id'))!=0){
				
					$this->args['page_id'] = intval(get_param('id'));

				} else {
					exit();
				}

			} else {
				// type d'item demandé
				$this->args['post_type'] = $this->type;

				// paramètres possibles
				if(get_param('id') && intval(get_param('id'))!=0){
					$this->args['p'] = intval(get_param('id'));
				} else if(get_param('slug') && strlen(get_param('slug'))>0){
					$this->args['name'] = get_param('slug');
				}

				$this->args['posts_per_page'] = '-1';
			}

			// ordre des résultats via param 'order' du genre 'date-asc' ou 'modified-desc'
			// 'desc' par défaut, si le param vaut seulement 'modified' par ex...
			if(get_param('order') && strpos(get_param('order'), '-')!==false){
				$bits = explode('-', get_param('order'));
				if(count($bits)>0){
					$by = $bits[0];
					if(count($bits)==1){ 
						$way = 'desc';
					} else {
						$way = $bits[1];
					}
					$this->args['orderby'] = $by;
					$this->args['order'] = $way;
				}				
			}					
			
			eco(export_object($this->args));
			eco();

		} else {
			exit();
		}
	}

	function do_query(){ // effectue la loop WP

		global $post; // le futur post wordpress natif :)

		if(count($this->args)>0){
			$this->query = new WP_Query($this->args);

			if ( $this->query->have_posts() ) {
			    while ( $this->query->have_posts() ) {
			        $this->query->the_post();
			        // eco(export_object($post));

			        // maintenant il faut extraire uniquement les données souhaitées
			        $this->extract_post_data();

			    }
			}
		}
	}

	function extract_post_data(){ global $post; // prépare les données suivant le param 'context'
		switch (get_param('context')) {

			case 'panel':

				if($this->type == 'element'){ // panel d'un element

					// http://www.archipels.org/ajax-api?action=get&type=element&id=1386&context=panel&debug
					$this->add_single_element_values(['id', 'date', 'title', 'legende', 'abrege', 'large-image', 'classes', 'tables-details']);

				} else if($this->type == 'table'){ // panel d'une table

					if(get_param('id')){
						// une seule table, son panel détaillé
						// http://www.archipels.org/ajax-api?action=get&type=table&id=371&context=panel&debug
						$this->add_single_table_values(['id', 'date', 'title', 'abrege', 'couleur', 'link', 'author-id', 'author-name']);

					} else {
						// la liste de toutes les tables
						// http://www.archipels.org/ajax-api?action=get&type=table&context=panel&order=modified-desc
						// même url, mais sans id :)
						$this->add_single_table_values(['id', 'title', 'abrege', 'couleur', 'link-base', 'author-name']);

					}

				} else if($this->type == 'page'){

					// A propos :
					// http://www.archipels.org/ajax-api?action=get&type=page&id=1563&context=panel&debug
					$this->add_single_page_values();
				}
				break;

			case 'atlas': // peu d'infos mais sur TOUS les éléments

				// http://www.archipels.org/ajax-api?action=get&type=element&context=atlas
				$this->add_single_element_values(['id', 'title', 'abrege', 'coords', 'classes', 'tables-ids']);
				break;
				
			case 'graphTables': // peu d'infos mais sur TOUS les éléments

				// www.archipels.org/get-json-atlas.php?action=get&type=table&context=graph
				//$this->add_single_element_values(['id', 'title', 'abrege', 'coords', 'classes', 'tables-ids']);
				global $post;
				$id = $post->ID;
				$this->data[$id] = array( 'name' => get_the_title(), 'group' => get_the_author() ) ;
				break;
				
			case 'graphLinks': // peu d'infos mais sur TOUS les éléments

				// www.archipels.org/get-json-atlas.php?action=get&type=table&context=graph
				//$this->add_single_element_values(['tables-ids']);
				$response = $this->get_single_element_values(['tables-ids']);
				$links = $this->data['links'] ? $this->data['links'] : [] ;
				$tables = $response['tables'];
				foreach($tables as $table) {
					$source = $table;
					foreach($tables as $target) {
						if($target!=$source) {
							$couple = array($source, $target);
							sort($couple);
							$couple = implode($couple,'-');
							$link = ['source' => $source, 'target' => $target, 'value' => 1];
							if( array_key_exists($couple, $links) ) {
								$this->data['links'][$couple]['value']++;
							} else {
								$this->data['links'][$couple] = $link ;
							}
						}
					}
				}
				//sort($this->data['links']);
				
				/*global $post;
				$id = $post->ID;
				$this->data[] = array( 'title' => get_the_title(), 'group' => get_the_author() ) ;*/
				break;

			case 'table':

				// par id
				// http://www.archipels.org/ajax-api?action=get&type=table&id=1223&context=table
				// par slug !
				// http://www.archipels.org/ajax-api?action=get&type=table&context=table&slug=raising-kern
				$this->add_single_table_values(['id', 'date', 'title', 'abrege', 'couleur', 'link', 'author-id', 'author-name', 'position-details', 'elements', 'notes', 'lignes']);
				break;
			
			default:
				exit();
				break;
		}
	}

	function give_json(){ // fabrication et sortie du json à partir de $this->data, alimenté via extract_post_data()
		// eco('$this->data :');
		eco(export_object($this->data));
		eco();

		// eco('sortie json :');
		if (get_param('context')=='graphLinks') {
			$couples = $this->data['links'];
			$result = [];
			foreach ($couples as $couple) {
				$result[] = $couple;
			}
			$this->json = json_encode($result);
		} else {
			$this->json = json_encode($this->data);
		}
		
		echo $this->json;
	}

	////////////////////////////////////////////////////
	// extraction-tri des données d'un element / table
	////////////////////////////////////////////////////

	function add_single_element_values($fields){ // AJOUT aux données à sortir des champs/valeurs demandés d'un element unique
		$this->data[] = $this->get_single_element_values($fields);
	}
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

        // en dernier ça marche mieux (crée une autre loop) !
        if(array_search('tables-details', $fields)!==false)		$data['tables'] = $this->get_element_tables(true);
        if(array_search('tables-ids', $fields)!==false)			$data['tables'] = $this->get_element_tables(false);

        return $data;
	}

	function add_single_table_values($fields){
		$this->data[] = $this->get_single_table_values($fields);
	}
	function get_single_table_values($fields){ // extraction des champs/valeurs d'une page
		global $post;
		$id = $post->ID;
		$data = array();

		// source : inc-table.php

		if(array_search('id', $fields)!==false) 				$data['id'] = $id;
		if(array_search('title', $fields)!==false) 			$data['title'] = get_the_title();
    if(array_search('abrege', $fields)!==false) 		$data['abrege'] = get_field('abrege', $id);	
		if(array_search('couleur', $fields)!==false) 		$data['couleur'] = get_field('couleur');
		if(array_search('link', $fields)!==false) 			$data['link'] = get_permalink();
		if(array_search('link-base', $fields)!==false) 	$data['link-base'] = basename(get_permalink());

		if(array_search('status', $fields)!==false) 		$data['status'] = get_post_status();

		if(array_search('position', $fields)!==false){ // l'array position brut
			$pos = get_post_meta( get_the_ID(),'table_position', true );
			if(!$pos) $pos = [1, 1, 1];
	        $data['position'] = $pos;
	    }
		if(array_search('position-details', $fields)!==false){ // les paramètres séparés
			$pos = get_post_meta( get_the_ID(),'table_position', true );
			if(!$pos) $pos = [1, 1, 1];
	        $data['top'] = $pos[0];
	        $data['left'] = $pos[1];
	        $data['scale'] = $pos[2];
	    }

        if(array_search('elements', $fields)!==false){        	
        	$elmts_ = get_post_meta( get_the_ID(),'elements', true );
        	$elmts = [];

        	// il faut ajouter les détails de chaque élément
        	foreach ($elmts_ as $elmt) {
        		$id = $elmt['id'];

        		// depuis /themes/.../functions.php
        		$elmt['abrege'] = get_field('abrege', $id);
        		$elmt['type'] = get_field('type', $id);
        		$elmt['date'] = get_field('annee', $id);
        		$elmt['legende'] = $legende = fabriqueLegende($id);

        		if ($elmt['type'] != "" ){
					if ($elmt['type'] == 'image'){

						$imageInfos = get_field('image', $id);

						$elmt['src_medium'] = $imageInfos['sizes']['medium'];
						$elmt['src_large'] = $imageInfos['sizes']['large'];
						$elmt['src_full'] = $imageInfos[url];

						// champs inutiles
						unset($elmt['fontSize']);
						unset($elmt['lineHeight']);
						unset($elmt['ombre']);

					} else if ($elmt['type'] == 'video') {
						$videoUrl = get_field('vid_url', $id);
						$video = videoinfo($videoUrl);

						$elmt['video'] = $video; // ['iframe'=>url, 'thumb'=>...]
					}
				}

				$elmts[] = $elmt;
        	}

        	$data['elements'] = $elmts;
        }
        if(array_search('notes', $fields)!==false)			$data['notes'] = get_post_meta( get_the_ID(),'notes', true );
        if(array_search('lignes', $fields)!==false)			$data['lignes'] = get_post_meta( get_the_ID(),'lignes', true );

        if(array_search('author-id', $fields)!==false)		$data['author-id'] = get_the_author_id();
        if(array_search('author-name', $fields)!==false)	$data['author-name'] = get_the_author();
        // + de détails sur l'auteur : status, level, etc
        // https://codex.wordpress.org/Function_Reference/get_the_author_meta

		return $data;
	}

	function add_single_page_values($fields=[]){
		$this->data[] = $this->get_single_page_values($fields);
	}
	function get_single_page_values($fields=[]){ // extraction des champs/valeurs d'une table
		
		if(count($fields) == 0) $fields = ['id', 'title', 'content', 'author-id', 'author-name'];

		global $post;
		$id = $post->ID;
		$data = array();

		if(array_search('id', $fields)!==false) 			$data['id'] = $id;
		if(array_search('title', $fields)!==false) 			$data['title'] = get_the_title();
		if(array_search('content', $fields)!==false){
			$base = get_the_content(); // pb : pas de paragraphes :/
			$data['content'] = wpautop($base, true); // et hop paragraphes à la wordpress !
		}

        if(array_search('author-id', $fields)!==false)		$data['author-id'] = get_the_author_id();
        if(array_search('author-name', $fields)!==false)	$data['author-name'] = get_the_author();
        // + de détails sur l'auteur : status, level, etc
        // https://codex.wordpress.org/Function_Reference/get_the_author_meta

		return $data;
	}



	////////////////////////////////////////////////////
	// data parsing stuff
	////////////////////////////////////////////////////

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

	function get_image_path($size, $field='image'){ global $post;
		// extrait juste le chemin vers l'image à la bonne taille

		/*
		la structure d'un champ 'image' d'un element :

		'id' => 1387
		'alt' => ''
		'title' => 'etreinteBD'
		'caption' => ''
		'description' => ''
		'mime_type' => 'image/jpeg'
		'url' => 'http://www.archipels.org/atlas/wp-content/uploads/2015/08/etreinteBD.jpg'
		'width' => 1280
		'height' => 980
		'sizes' => array ( 
			'thumbnail' => 'http://www.archipels.org/atlas/wp-content/uploads/2015/08/etreinteBD-150x150.jpg'
			'thumbnail-width' => 150
			'thumbnail-height' => 150
			'medium' => 'http://www.archipels.org/atlas/wp-content/uploads/2015/08/etreinteBD-250x191.jpg'
			'medium-width' => 250
			'medium-height' => 191
			'large' => 'http://www.archipels.org/atlas/wp-content/uploads/2015/08/etreinteBD-700x536.jpg'
			'large-width' => 700
			'large-height' => 536
			'small' => 'http://www.archipels.org/atlas/wp-content/uploads/2015/08/etreinteBD-120x92.jpg'
			'small-width' => 120
			'small-height' => 92
			'custom-size' => 'http://www.archipels.org/atlas/wp-content/uploads/2015/08/etreinteBD-700x200.jpg'
			'custom-size-width' => 700
			'custom-size-height' => 200
		)
		*/

		$id = $post->ID;
		$image_data = get_field($field, $id);
		$sizes = $image_data['sizes'];
		return $sizes[$size];
	}

	

	/*function addData($dat){
		array_push($this->data
	}*/


}

new Request();

// tools

function get_param($id){ // centralisé par sécurité, et permet de passer tout en POST si besoin
	if(isset($_POST[$id])){
		return mysql_real_escape_string($_POST[$id]);
	} else if(isset($_GET[$id])){
		return mysql_real_escape_string($_GET[$id]);
	} else {
		return false;
	}
}

function eco($txt=' '){ // une sortie de debug, fermable entièrement d'ici si besoin !
	if(get_param('debug')!==false) echo $txt.'<br />'."\n";
}

function export_object($obj){ // afficher un objet php en string lisible
	return str_replace("\n", "", var_export($obj, true));
}

function implodeIf($sep, $obj){
	if(is_array($obj)){
		return implode($sep, $obj);
	} else {
		return $obj;
	}
}



/*
'post_type' : 'element', 'table', 'page', ('attachment', 'sequence')
http://www.archipels.org/wp-json/posts/types

'posts_per_page'         => '-1', // pour avoir tous les résultats

le contenu de $post :

'ID' => 489
'post_author' => '2'
'post_date' => '2014-05-18 14:22:18'
'post_date_gmt' => '2014-05-18 13:22:18'
'post_content' => ''
'post_title' => ' Global Positioning System'
'post_excerpt' => ''
'post_status' => 'publish'
'comment_status' => 'closed'
'ping_status' => 'closed'
'post_password' => ''
'post_name' => 'global-positioning-system'
'to_ping' => ''
'pinged' => ''
'post_modified' => '2014-05-18 14:22:18'
'post_modified_gmt' => '2014-05-18 13:22:18'
'post_content_filtered' => ''
'post_parent' => 0
'guid' => 'http://www.archipels.org/?post_type=element&p=489'
'menu_order' => 0
'post_type' => 'element'
'post_mime_type' => ''
'comment_count' => '0'
'filter' => 'raw',

et get_post_meta(...) pour le reste...

connecté/non-connecté :
https://codex.wordpress.org/Function_Reference/is_user_logged_in
puis verif via le compte ?
is_admin() is_author(author_id)
-> get_current_user_id() !!! et hop verif du compte...
--> mieux : wp_get_current_user() retourne tous les datas et pas que l'id...

https://codex.wordpress.org/Function_Reference


*/

?>
<?php
/*
Plugin Name: Woocommerce Goddess.nl
Plugin URI: http://donninger.nl
Description: Various customisations for Goddess.nl
Version: 0.1
Author: Donninger Consultancy
Author Email: niels@donninger.nl
License:

  Copyright 2011 Donninger Consultancy (niels@donninger.nl)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

class WoocommerceGoddessNL {

	/*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const name = 'Woocommerce Goddess.nl';
	const slug = 'woocommerce-goddess-nl';
	
	/**
	 * Constructor
	 */
	function __construct() {
		//register an activation hook for the plugin
		register_activation_hook( __FILE__, array( &$this, 'install_plugin' ) );

		//Hook up to the init action
		add_action( 'init', array( &$this, 'init_plugin' ) );
	}
  
	/**
	 * Runs when the plugin is activated
	 */  
	function install_plugin() {
		// do not generate any output here
	}
  
	/**
	 * Runs when the plugin is initialized
	 */
	function init_plugin() {
		global $product;
		if ( is_admin() ) {
		
			//this will run when in the WordPress admin
			/**
			* save all bandnames in the titles
			*/
			$this->fix_all_titles();

			/**
			 *  Save custom attributes as post's meta data as well so that we can use in sorting and searching
			 */
			add_action( 'save_post', array($this,'save_woocommerce_attr_to_meta'), 20 );
			/**
			* save the category as product attribute so filtering is done better
			*/
			add_action( 'save_post', array($this,'save_category_as_attr'), 20 );
			/**
			* add the bandname to the product title upon saving a product
			*/
			add_action( 'save_post', array(&$this,'modify_post_title'), 20 );

		} else {
			//this will run when on the frontend
			/** Show the band name on the archives pages */
			add_action( 'woocommerce_before_shop_loop_item_title', array($this,'show_attribute_archive'));
			
			/** Show the category name on the archives pages */
			add_action( 'woocommerce_before_shop_loop_item_title', array($this,'show_category_archive'));
			
			/**
			* custom product title in archives
			*/
			add_action( 'woocommerce_before_shop_loop_item_title', array($this,'show_custom_title_in_archive'), 90);

			/** Show the band name on the single product pages */
			add_action( 'woocommerce_single_product_summary', array($this,'show_attribute_single_product'));
			
			/**
			 *  Defines the criteria for sorting with options defined in the method below
			 */
			add_filter('woocommerce_get_catalog_ordering_args', array($this,'custom_woocommerce_get_catalog_ordering_args'));

			/**
			 *  Adds the sorting options to dropdown list .. The logic/criteria is in the method above
			 */
			add_filter('woocommerce_catalog_orderby', array($this,'custom_woocommerce_catalog_orderby'));
			
			/**
			* no related products please!
			*/
			remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
			
						
		}
	}
 
	
	/**
	* go through all the products and change the title.
	* one-time action only
	*/
	private function fix_all_titles(){
		global $wpdb;
		// The Query
		$query = "
		UPDATE 
 godwc_posts as p join
 godwc_postmeta as m on
	m.post_id=p.ID
SET p.post_title = CONCAT(m.meta_value, ' - ', p.post_title) 
WHERE post_type='product'
AND m.meta_key='band'
AND LCASE(p.post_title) NOT LIKE LCASE(CONCAT('%', m.meta_value, '%'))";
		//$wpdb->Query($query);
	}
	
	/** 
	* set the bandname in front of the product title
	*/
	function modify_post_title( $post_id ) {
		$product = get_post($post_id);

	    // If this isn't a 'product' post, don't update it.
	    if ( $product->post_type !== "product" ) {
	        return;
	    }
	    
		/** Get the band name from the product attributes **/
		$band = $this->get_band($post_id);
		
		//echo "bandname: $band<br/>";
		/** add the bandname to the title if it is not yet in there */
		if($band) {
			if(strpos(strtolower($product->post_title), strtolower($band)) !== false) {
				//band name is in the title: do nothing
			} else {
				//put the band name in front
				$product->post_title = ucwords($band) . " - " . $product->post_title;
				wp_update_post($product);
			}
		}
	}
	
	/**
	* 1. store the bandname and category name as tags as well
	* 2. store the bandname in a custom field so it can be sorted on
	*/
	function save_woocommerce_attr_to_meta( $post_id ) {
		$product = get_post($post_id);
		
	    // If this isn't a 'product' post, don't update it.
	    if ( $product->post_type !== "product" ) {
	        return;
	    }
	    
    	/** Get the band name from the product attributes */
		$band = $this->get_band($product->ID);
		
		/** save band name in custom field 'band' */
		add_post_meta( $product->ID, 'band', $band, true ) || update_post_meta( $product->ID, 'band', $band ); 

		/** find the product's category name */		
		$categories = get_the_terms($product->ID,"product_cat"); 
		$category = ($categories[0]->name ? $categories[0]->name : "none");

		/** save product tags if they don't exist yet */
		/** check current tags first */
		$current_tags = wp_get_post_tags($product->ID);
		$tags = array();
		foreach($current_tags as $tag) {
			$tags[] = $tag->name;
		}
		//echo "old tags:"; var_dump($tags);
		//if(!in_array($band, $tags) || !in_array($category, $tags)) {
			$new_tags = array($band,$category);
			//wp_set_post_tags( $product->ID, $new_tags, false );

			$this->write_tags($product->ID, $new_tags);
			//echo "new tags: "; var_dump($new_tags);
			//wp_update_post($product);
		//}
	}
	
	/**
	* helper function to store the product tags
	* (doesn't seem to work...)
	*/
	private function write_tags($id, $tags) {
		$taxonomy = 'post_tag';
		
		foreach ($tags as $solotag) {
			$solotag = trim($solotag);
			$check = is_term($solotag, $taxonomy);
			if (is_null($check)) {
				$tag = wp_insert_term($solotag, $taxonomy);
		
				if(!is_wp_error($tag)) {
					$tagid = $tag['term_id'];
				} else {
					$tagid = $check['term_id'];
				}
			}
		}

		wp_set_post_tags( $id, $tags, false );
	}

	
	/**
	* order by bandname
	*/
	function custom_woocommerce_get_catalog_ordering_args( $args ) {
	    global $wp_query;
	        // Changed the $_SESSION to $_GET
	    if (isset($_GET['orderby'])) {
	        switch ($_GET['orderby']) :
	            case 'band' :
	                $args['order'] = 'ASC';
	                $args['meta_key'] = 'band';
	                $args['orderby'] = 'meta_value';
	            break;
	        endswitch;
	    } else {
            $args['order'] = 'ASC';
            $args['meta_key'] = 'band';
            $args['orderby'] = 'meta_value';
		}
	    return $args;
	}
	
	/**
	* order by bandname
	*/
	function custom_default_catalog_orderby() {
	     return 'band';
	}

	/**
	* order by bandname
	*/
	function custom_woocommerce_catalog_orderby( $sortby ) {
	    $sortby['band'] = 'Sort by band: A to Z';
	    return $sortby;
	}
	
	private function image_exists($url) {
	    $options['http'] = array(
	        'method' => "HEAD",
	        'ignore_errors' => 1,
	        'max_redirects' => 0
		);
		$body = file_get_contents($url, NULL, stream_context_create($options));
		sscanf($http_response_header[0], 'HTTP/%*d.%*d %d', $code);
		return $code === 200;
	}
	
	private function get_band_image($band) {
		/** CHECK PNG **/
		$filename1= 'http://' . $_SERVER['HTTP_HOST'] . '/wp-content/uploads/bands/' . $band . '.png';
		$filename2= 'http://' . $_SERVER['HTTP_HOST'] . '/wp-content/uploads/bands/' . $band . '.jpg';
		if($this->image_exists($filename1)) {
			return $filename1;
		} elseif($this->image_exists($filename2)) {
			return $filename2;
		} else {
			return false;
		}
	}
	
	private function get_band($product_id) {
		$attributeArray = get_the_terms( $product_id, 'pa_band');
		if(is_array($attributeArray)) {
			foreach($attributeArray as $term) {
				$attributeName = $term->name;	
			}
		}
		return strtolower($attributeName);
	}

	private function get_band_id($product_id) {
		$attributeArray = get_the_terms( $product_id, 'pa_band');
		if(is_array($attributeArray)) {
			foreach($attributeArray as $term) {
				$attributeID = $term->term_id;	
			}
		}
		return strtolower($attributeID);
	}
		
	/**
	* show the product attribute (bandname) in the archive pages
	* @var string band
	*/
	function show_attribute_archive() {
		global $product;
		$band=$this->get_band($product->id);
		if($band) {
			echo "<span class='product_attribute'>" . ucwords($band) . "</span>";
		}
		//var_dump($attributeArray);
	}
	
	/**
	* show the product category in the archive pages
	* @var string category
	*/
	function show_category_archive() {
		global $product;
		$terms = get_the_terms( $product->ID, 'product_cat' );
		$term = array_shift(array_values($terms));
		$category = $term->name;
		echo "<span class='product_category'>$category</span>";
	}
	
	function get_band_logo_url($band_name) {
		echo $source_url = "http://www.metal-archives.com/bands/" . str_replace(" ", "_",ucwords($band_name));
		return "";
	}
	
	function get_band_image_url($band_name) {
		return "";
	}
	/** 
	* single product page / product detail page
	*/
	function show_attribute_single_product() {
		global $product;
		$band=$this->get_band($product->id);
		$band_id=$this->get_band_id($product->id);
		if($band) {
			$band_logo_url = $this->get_band_logo_url($band);
			$band_image_url = $this->get_band_image_url($band);
			$image = $this->get_band_image($band);
			echo "<a href='/shop/?filtering=1&filter_band=${band_id}/'>";
			if($image) {
				echo "<img src='{$image}'/>";
			} else {
				echo "<span class='band'>" . ucwords($band) . "</span>";
			}
			echo "</a>";
		}
	}
	
	/** 
	* try to split up the title by trimming the bandname
	* bandname is shown by function show_attribute_single_product()
	* be sure to hide the current product title with for example CSS
	*/
	function show_custom_title_in_archive() {
		global $product;
		$post = $product->post;
		echo "<a href=\"" . get_permalink($product->ID) . "\">";
		echo "<span class=\"product_title\">";
		$title = explode(" - ", $post->post_title);
		if(sizeof($title)==2) {
			 echo $title[1];
		} else {
			echo $post->post_title;
		}
		echo "</span></a>";
	} 
	
	/**
	* Save the category name as attribute so filtering is done in a better way
	*/
	function save_category_as_attr($_product_id = 0) {
		global $product;
		$product_id = $_product_id ? $_product_id : $product->ID;
		$terms = get_the_terms( $product_id, 'product_cat' );
		$term = array_shift(array_values($terms));
		$category = $term->name;
		
		/** find the matching attribute */
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		$taxonomy_terms = array();
		
		if ( $attribute_taxonomies ) :
		    foreach ($attribute_taxonomies as $tax) :
		    if (taxonomy_exists(wc_attribute_taxonomy_name($tax->attribute_name))) {
		    	echo "'" . wc_attribute_taxonomy_name($tax->attribute_name) . "'<br/>";
		    	if($tax->attribute_name === "cat-attr") {
			        $taxonomy_terms[$tax->attribute_name] = get_terms( wc_attribute_taxonomy_name($tax->attribute_name), 'orderby=name&hide_empty=0' );
			    }
		    }
		endforeach;
		endif;
		
		var_dump($taxonomy_terms);
		
		exit;

		
		$att_terms = get_the_terms( $product_id, 'pa_band');
		
		$terms = get_post_meta( $product_id, '_product_attributes', true );
		$band_terms = $terms["pa_band"];
		$category_terms = array("name" => "pa_cat_attr", "value" => "", "postition" => "1", "is_visible" => 1, "is_variation" => 0, "is_taxonomy" => 1);
		
		$new_terms = array("pa_band" => $band_terms, "pa_cat_attr" => $category_terms);
		var_dump($new_terms); exit(0);
		//update_post_meta( $product_id, 'pa_cat_attr', $category);
	}
} // end class
new WoocommerceGoddessNL();

?>
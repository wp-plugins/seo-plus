<?php
add_action('init','cat_seo_init');
function cat_seo_init()
{
	global $wpdb,$blog_id;
	$wpdb->taxonomymeta = "{$wpdb->prefix}taxonomymeta";
	$taxonomy_metadata = new Aheadzen_Taxonomy_Metadata;
	register_activation_hook( __FILE__, array($taxonomy_metadata, 'activate') );
}

class Aheadzen_Taxonomy_Metadata {
	function __construct() {
		add_action( 'admin_init', array($this, 'wpdbfix') );
		add_action( 'switch_blog', array($this, 'wpdbfix') );
		add_action('wpmu_new_blog', 'new_blog', 10, 6);
		add_action('admin_init', array($this,'taxonomy_metadata_init'));
		add_filter('taxonomy_form_field_terms_filter',array($this,'taxonomy_form_field_terms_filter_fun'));
		/**ALL IN ONE SEO****/
		global $aioseop_plugin_name;
		if($aioseop_plugin_name){
			add_filter( 'aioseop_title', array($this,'wp_ah_category_title'), 10, 2 ); 
			add_filter( 'aioseop_description', array($this,'ah_seo_meta_desc'), 10, 2  );
			add_filter( 'aioseop_keywords', array($this,'ah_seo_meta_kw'), 10, 2 );
		}else{
			add_filter( 'wp_title', array($this,'wp_ah_category_title'), 10, 2 ); 
			add_action ('wp_head',array($this,'wp_ah_category_meta'));
		}
	}

	/*
	 * Quick touchup to wpdb
	 */
	function wpdbfix() {
		global $wpdb,$blog_id;
		
		$wpdb->taxonomymeta = "{$wpdb->prefix}taxonomymeta";
		$charset_collate = '';	
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
		
		$tables = $wpdb->get_results("show tables like '{$wpdb->prefix}taxonomymeta'");
		if (!count($tables))
			$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}taxonomymeta (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				taxonomy_id bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY	(meta_id),
				KEY taxonomy_id (taxonomy_id),
				KEY meta_key (meta_key)
			) $charset_collate;");
	}
	
	
	/**
	 * Add meta data field to a term.
	 *
	 * @param int $term_id Post ID.
	 * @param string $key Metadata name.
	 * @param mixed $value Metadata value.
	 * @param bool $unique Optional, default is false. Whether the same key should not be added.
	 * @return bool False for failure. True for success.
	 */
	function add_term_meta($term_id, $meta_key, $meta_value, $unique = false) {
		return add_metadata('taxonomy', $term_id, $meta_key, $meta_value, $unique);
	}
	
	/**
	 * Remove metadata matching criteria from a term.
	 *
	 * You can match based on the key, or key and value. Removing based on key and
	 * value, will keep from removing duplicate metadata with the same key. It also
	 * allows removing all metadata matching key, if needed.
	 *
	 * @param int $term_id term ID
	 * @param string $meta_key Metadata name.
	 * @param mixed $meta_value Optional. Metadata value.
	 * @return bool False for failure. True for success.
	 */
	function delete_term_meta($term_id, $meta_key, $meta_value = '') {
		return delete_metadata('taxonomy', $term_id, $meta_key, $meta_value);
	}
	
	/**
	 * Retrieve term meta field for a term.
	 *
	 * @param int $term_id Term ID.
	 * @param string $key The meta key to retrieve.
	 * @param bool $single Whether to return a single value.
	 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single
	 *  is true.
	 */
	function get_term_meta($term_id, $key, $single = false) {
		return get_metadata('taxonomy', $term_id, $key, $single);
	}
	
	/**
	 * Update term meta field based on term ID.
	 *
	 * Use the $prev_value parameter to differentiate between meta fields with the
	 * same key and term ID.
	 *
	 * If the meta field for the term does not exist, it will be added.
	 *
	 * @param int $term_id Term ID.
	 * @param string $key Metadata key.
	 * @param mixed $value Metadata value.
	 * @param mixed $prev_value Optional. Previous value to check before removing.
	 * @return bool False on failure, true if success.
	 */
	function update_term_meta($term_id, $meta_key, $meta_value, $prev_value = '') {
		return update_metadata('taxonomy', $term_id, $meta_key, $meta_value, $prev_value);
	}
	
	/**
	 * Add additional taxonomy fields to all public taxonomies
	 */
	function taxonomy_metadata_init() {
		// Require the Taxonomy Metadata plugin
		
		// Get a list of all public custom taxonomies
		$taxonomies = get_taxonomies( array(
			'public'   => true,
		), 'names', 'and');
		
		$taxonomies = apply_filters('taxonomy_form_field_terms_filter',$taxonomies);
		//$taxonomies=get_taxonomies('','names'); 

		// Attach additional fields onto all custom, public taxonomies
		if ( $taxonomies ) {
			foreach ( $taxonomies  as $taxonomy ) {
				if($taxonomy){
				// Add fields to "add" and "edit" term pages
				add_action("{$taxonomy}_add_form_fields", array($this,'taxonomy_metadata_edit'), 10, 1);
				add_action("{$taxonomy}_edit_form_fields",array($this,'taxonomy_metadata_edit'), 10, 1);
				// Process and save the data
				add_action("created_{$taxonomy}", array($this,'save_taxonomy_metadata'), 10, 1);
				add_action("edited_{$taxonomy}", array($this,'save_taxonomy_metadata'), 10, 1);
				}
			}
		}
	}
	
	/**
	 * Add additional fields to the taxonomy edit view
	 * e.g. /wp-admin/edit-tags.php?action=edit&taxonomy=category&tag_ID=27&post_type=post
	 */
	function taxonomy_metadata_edit( $tag ) {
		// Only allow users with capability to publish content
		$term_taxonomy_id = $tag->term_taxonomy_id;
		if ( current_user_can( 'publish_posts' ) ): ?>
		<tr class="form-field">
				<th scope="row" valign="top">
					<h3><?php _e('SEO Settings','aheadzen'); ?></h3>
					<input type="hidden" name="term_taxonomy_id" value="<?php echo $term_taxonomy_id;?>" >
				</th>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="meta_title"><?php _e('SEO Title','aheadzen'); ?></label>
				</th>
				<td>
					<input name="seo_title" id="seo_title" type="text" value="<?php echo $this->get_term_meta($term_taxonomy_id, 'seo_title', true); ?>" size="60"  />
					<p class="description"><?php _e('maximum title size should be 60 characters.','aheadzen'); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="meta_title"><?php _e('Description','aheadzen'); ?></label>
				</th>
				<td>
					<textarea name="seo_desc" id="seo_desc" type="text"><?php echo $this->get_term_meta($term_taxonomy_id, 'seo_desc', true); ?></textarea>
					<p class="description"><?php _e('maximum title size should be 155 characters.','aheadzen'); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="meta_title"><?php _e('Keywords','aheadzen'); ?></label>
				</th>
				<td>
					<input name="seo_kw" id="seo_kw" type="text" value="<?php echo $this->get_term_meta($term_taxonomy_id, 'seo_kw', true); ?>" size="200"  />
					<p class="description"><?php _e('enter comma separated keywords.','aheadzen'); ?></p>
				</td>
			</tr>
		<?php endif;
	}
	
	/**
	 * Save taxonomy metadata
	 *
	 * Currently the Taxonomy Metadata plugin is needed to add a few features to the WordPress core
	 * that allow us to store this information into a new database table
	 *
	 *	http://wordpress.org/extend/plugins/taxonomy-metadata/
	 */
	function save_taxonomy_metadata( $term_id ) {
		$term_taxonomy_id = $_POST['term_taxonomy_id'];
		if(!$term_taxonomy_id){
			global $wpdb;
			$term_taxonomy_id = $wpdb->get_var("select term_taxonomy_id from $wpdb->term_taxonomy where term_id=\"$term_id\"");
		}
		$this->update_term_meta( $term_taxonomy_id, 'seo_title', esc_attr($_POST['seo_title']) );
		$this->update_term_meta( $term_taxonomy_id, 'seo_desc', esc_attr($_POST['seo_desc']) );
		$this->update_term_meta( $term_taxonomy_id, 'seo_kw', esc_attr($_POST['seo_kw']) );	
	}
	
	function taxonomy_form_field_terms_filter_fun($terms)
	{
		
		/*foreach($terms as $cat => $name)
		{
			if($cat==VA_LISTING_CATEGORY || $cat==VA_EVENT_CATEGORY)
			{
			}else{
				unset($terms[$cat]);	
			}
		}*/
		return $terms;
	}
	function wp_ah_category_title($title, $sep){
		
		global $wp_query;
		if(!$sep){$sep='|';}
		$cat_obj = $wp_query->get_queried_object();
		$term_taxonomy_id = $cat_obj->term_taxonomy_id;
		if($term_taxonomy_id){
			$seo_title = trim( strip_tags($this->get_term_meta($term_taxonomy_id, 'seo_title', true)));
			if($seo_title){return $seo_title.' '.$sep.' '.get_bloginfo('name');}
		}
		return $title;
	  }
	  
	function wp_ah_category_meta(){
		$kw =  $this->ah_seo_meta_kw();
		if($kw){ echo '<meta name="keywords" content="'.$kw.'" />';}
		$desc = $this->ah_seo_meta_desc();
		if($desc){ echo '<meta name="description" content="'.$desc.'" />';}
	}
	
	function ah_seo_meta_kw()
	{
		global $wp_query;			
		$cat_obj = $wp_query->get_queried_object();
		$term_taxonomy_id = $cat_obj->term_taxonomy_id;
		$return = '';
		if($term_taxonomy_id){
			return trim( strip_tags($this->get_term_meta($term_taxonomy_id, 'seo_kw', true)));
		}
		return $return;
	}
	
	function ah_seo_meta_desc()
	{
		global $wp_query;			
		$cat_obj = $wp_query->get_queried_object();
		$term_taxonomy_id = $cat_obj->term_taxonomy_id;
		$return = '';
		if($term_taxonomy_id){
			return trim( strip_tags($this->get_term_meta($term_taxonomy_id, 'seo_desc', true)));
		}
		return $return;
	}
	
}

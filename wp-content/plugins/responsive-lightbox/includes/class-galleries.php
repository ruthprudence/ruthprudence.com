<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Galleries class.
 *
 * @class Responsive_Lightbox_Galleries
 */
class Responsive_Lightbox_Galleries {

	public $tabs;
	public $fields;
	public $tab;
	public $sizes;
	public $gallery_args;
	public $current_page = 1;
	private $menu_item;

	/**
	 * Constructor.
	 *
	 * @param bool $read_only Whether is it read only mode
	 * @return void
	 */
	public function __construct( $read_only = false ) {
		// set instance
		Responsive_Lightbox()->galleries = $this;

		if ( $read_only )
			return;

		// actions
		add_action( 'init', array( $this, 'init' ), 11 );
		add_action( 'admin_init', array( $this, 'init_admin' ) );
		add_action( 'edit_form_after_title', array( $this, 'after_title_nav_menu' ) );
		add_action( 'admin_footer', array( $this, 'modal_gallery_template' ) );
		add_action( 'media_buttons', array( $this, 'add_gallery_button') );
		add_action( 'add_meta_boxes_rl_gallery', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_rl_gallery', array( $this, 'save_post' ), 10, 3 );
		add_action( 'manage_rl_gallery_posts_custom_column', array( $this, 'gallery_columns_content' ), 10, 2 );
		add_action( 'admin_action_duplicate_gallery', array( $this, 'duplicate_gallery' ) );
		add_action( 'wp_ajax_rl-get-menu-content', array( $this, 'get_menu_content' ) );
		add_action( 'wp_ajax_rl-get-preview-content', array( $this, 'get_gallery_preview_content' ) );
		add_action( 'wp_ajax_rl-post-get-galleries', array( $this, 'post_get_galleries' ) );
		add_action( 'wp_ajax_rl-post-gallery-preview', array( $this, 'post_gallery_preview' ) );
		add_action( 'wp_ajax_rl-get-gallery-page-content', array( $this, 'get_gallery_page' ) );
		add_action( 'wp_ajax_nopriv_rl-get-gallery-page-content', array( $this, 'get_gallery_page' ) );
		add_action( '_wp_put_post_revision', array( $this, 'save_revision' ) );
		add_action( 'shutdown', array( $this, 'shutdown_preview' ) );

		// filters
		add_filter( 'manage_rl_gallery_posts_columns', array( $this, 'gallery_columns' ) );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'admin_post_thumbnail_html' ), 10, 2 );
		add_filter( 'post_thumbnail_html', array( $this, 'post_thumbnail_html' ), 10, 5 );
		add_filter( 'preview_post_link', array( $this, 'preview_post_link' ) );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions_duplicate' ), 10, 2 );

		if ( ! empty( $_POST['rl_active_tab'] ) )
			add_filter( 'redirect_post_location', array( $this, 'add_active_tab' ) );
	}

	/**
	 * Get default gallery single image template.
	 *
	 * @param array $args Template arguments
	 * @return string Rendered template
	 */
	public function get_media_item_template( $args = array() ) {
		$args = array_merge(
			array(
				'draggable'	=> false,
				'editable'	=> false,
				'removable'	=> false,
				'changable'	=> false
			),
			$args
		);

		return '
		<li class="rl-gallery-image __IMAGE_STATUS__" data-attachment_id="__IMAGE_ID__"' . ( $args['draggable'] ? ' style="cursor: move;"' : '' ) . '>
			<div class="rl-gallery-inner">__IMAGE__</div>
			<div class="rl-gallery-actions">' . 
				( $args['changeable'] ? '<a href="#" class="rl-gallery-image-status dashicons dashicons-marker" title="' . __( 'Status', 'responsive-lightbox' ) . '"></a>' : '' ) . 
				( $args['editable'] ? '<a href="#" class="rl-gallery-image-edit dashicons dashicons-edit" title="' . __( 'Edit image', 'responsive-lightbox' ) . '"></a>' : '' ) . 
				( $args['removable'] ? '<a href="#" class="rl-gallery-image-remove dashicons dashicons-no" title="' . __( 'Remove image', 'responsive-lightbox' ) . '"></a>' : '' ) . '
			</div>
		</li>';
	}

	/**
	 * Load needed data.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() )
			add_filter( 'get_post_metadata', array( $this, 'filter_preview_metadata' ), 10, 4 );

		// register shortcode
		add_shortcode( 'rl_gallery', array( $this, 'gallery_shortcode' ) );

		// get main instance
		$rl = Responsive_Lightbox();

		$config_menu_items = apply_filters( 'rl_gallery_types', $rl->gallery_types );
		$config_menu_items['default'] = __( 'Global', 'responsive-lightbox' );

		$this->tabs = apply_filters(
			'rl_gallery_tabs',
			array(
				'images'	=> array(
					'label'			=> __( 'Images', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the contents of the gallery.', 'responsive-lightbox' ),
					'menu_items'	=> array(
						'media'		=> __( 'Media Library', 'responsive-lightbox' ),
						'featured'	=> __( 'Featured Content', 'responsive-lightbox' )
					)
				),
				'config'	=> array(
					'label'			=> __( 'Config', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the configuration options for the gallery.', 'responsive-lightbox' ),
					'menu_items'	=> $config_menu_items
				),
				'design'	=> array(
					'label'			=> __( 'Design', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the gallery design options.', 'responsive-lightbox' )
				),
				'paging'	=> array(
					'label'			=> __( 'Paging', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the gallery pagination options.', 'responsive-lightbox' )
				),
				'lightbox'	=> array(
					'label'			=> __( 'Lightbox', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the lightbox options.', 'responsive-lightbox' ),
				),
				'misc'		=> array(
					'label'			=> __( 'Misc', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust miscellaneous options.', 'responsive-lightbox' ),
				)
			)
		);

		// add folders if active
		// if ( $rl->options['folders']['active'] )
			$this->tabs['images']['menu_items']['folders'] = __( 'Media Folders', 'responsive-lightbox' );

		// add remote library if active
		// if ( $rl->options['remote_library']['active'] )
			$this->tabs['images']['menu_items']['remote_library'] = __( 'Remote Library', 'responsive-lightbox' );

		// use sizes as keys and values
		$this->sizes = $this->get_image_sizes();
		$sizes = array_combine( array_keys( $this->sizes ), array_keys( $this->sizes ) );

		// add default, custom and full image size
		$sizes['full'] = __( 'Full size', 'responsive-lightbox' );
		$sizes['global'] = __( 'Global', 'responsive-lightbox' );
		$sizes['rl_custom_size'] = __( 'Custom size', 'responsive-lightbox' );

		// positions
		$positions = array(
			'none'		=> __( 'None', 'responsive-lightbox' ),
			'top'		=> __( 'Top', 'responsive-lightbox' ),
			'bottom'	=> __( 'Bottom', 'responsive-lightbox' )
		);

		// merge titles
		$merged_titles = array( 'global' => __( 'Global', 'responsive-lightbox' ) ) + $rl->settings->image_titles;

		$this->fields = apply_filters(
			'rl_gallery_tab_fields',
			array(
				'images' => array(
					'media' => array(
						'attachments' => array(
							'title' => '',
							'type' => 'media_library',
							'default' => array(
								'ids' => array(),
								'exclude' => array()
							),
							'preview' => array(
								'pagination' => true,
								'draggable' => true,
								'editable' => true,
								'removable' => true,
								'changeable' => true
							)
						)
					),
					'featured' => array(
						'attachments' => array(
							'title' => '',
							'type' => 'media_preview',
							'default' => array(
								'exclude' => array()
							),
							'preview' => array(
								'pagination' => true,
								'draggable' => false,
								'editable' => true,
								'removable' => false,
								'changeable' => false
							)
						),
						'number_of_posts' => array(
							'title' => __( 'Number of Posts', 'responsive-lightbox' ),
							'type' => 'number',
							'description' => __( 'Enter the number of posts.', 'responsive-lightbox' ),
							'default' => 10,
							'min' => 0
						),
						'orderby' => array(
							'title' => __( 'Posts Sorting', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select the posts sorting.', 'responsive-lightbox' ),
							'default' => 'date',
							'options' => array(
								'id'		=> __( 'ID', 'responsive-lightbox' ),
								'author'	=> __( 'Author', 'responsive-lightbox' ),
								'title'		=> __( 'Title', 'responsive-lightbox' ),
								'name'		=> __( 'Slug', 'responsive-lightbox' ),
								'date'		=> __( 'Date', 'responsive-lightbox' ),
								'modified'	=> __( 'Last modified date', 'responsive-lightbox' ),
								'parent'	=> __( 'Parent ID', 'responsive-lightbox' ),
								'rand'		=> __( 'Random', 'responsive-lightbox' )
							)
						),
						'order' => array(
							'title' => __( 'Posts Order', 'responsive-lightbox' ),
							'type' => 'radio',
							'description' => __( 'Select the posts order.', 'responsive-lightbox' ),
							'default' => 'asc',
							'options' => array(
								'asc'	=> __( 'Ascending', 'responsive-lightbox' ),
								'desc'	=> __( 'Descending', 'responsive-lightbox' )
							)
						),
						'offset' => array(
							'title' => __( 'Posts Offset', 'responsive-lightbox' ),
							'type' => 'number',
							'description' => __( 'Enter the posts offset.', 'responsive-lightbox' ),
							'default' => 0,
							'min' => 0
						),
						'image_source' => array(
							'title' => __( 'Image Source', 'responsive-lightbox' ),
							'type' => 'radio',
							'description' => __( 'Select the image source.', 'responsive-lightbox' ),
							'default' => 'thumbnails',
							'options' => array(
								'thumbnails' => __( 'Post Thumbnails', 'responsive-lightbox' ),
								'attached_images' => __( 'Post Attached Images', 'responsive-lightbox' )
							)
						),
						'images_per_post' => array(
							'title' => __( 'Images per Post', 'responsive-lightbox' ),
							'type' => 'number',
							'description' => __( 'Enter maximum number of images for a post.', 'responsive-lightbox' ),
							'default' => 1,
							'min' => 1
						),
						'post_type' => array(
							'title' => __( 'Post Type', 'responsive-lightbox' ),
							'type' => 'multiselect',
							'description' => __( 'Select the post types to query.', 'responsive-lightbox' ),
							'options' => array(),
							'default' => array()
						),
						'post_status' => array(
							'title' => __( 'Post Status', 'responsive-lightbox' ),
							'type' => 'multiselect',
							'description' => __( 'Select the post status.', 'responsive-lightbox' ),
							'options' => array(),
							'default' => array()
						),
						'post_format' => array(
							'title' => __( 'Post Format', 'responsive-lightbox' ),
							'type' => 'multiselect',
							'description' => __( 'Select the post format.', 'responsive-lightbox' ),
							'options' => array(),
							'default' => array()
						),
						'post_term' => array(
							'title' => __( 'Post Term', 'responsive-lightbox' ),
							'type' => 'multiselect',
							'description' => __( 'Select the post taxonomy terms to query.', 'responsive-lightbox' ),
							'options' => array(),
							'default' => array()
						),
						'post_author' => array(
							'title' => __( 'Post Author', 'responsive-lightbox' ),
							'type' => 'multiselect',
							'description' => __( 'Select the post author.', 'responsive-lightbox' ),
							'options' => array(),
							'default' => array()
						),
						'page_parent' => array(
							'title' => __( 'Page Parent', 'responsive-lightbox' ),
							'type' => 'multiselect',
							'description' => __( 'Select the post parent.', 'responsive-lightbox' ),
							'options' => array(),
							'default' => array()
						),
						'page_template' => array(
							'title' => __( 'Page Template', 'responsive-lightbox' ),
							'type' => 'multiselect',
							'description' => __( 'Select the page template.', 'responsive-lightbox' ),
							'options' => array(),
							'default' => array()
						)
					),
					'folders' => array(
						'attachments' => array(
							'title' => '',
							'type' => 'media_preview',
							'default' => array(
								'exclude' => array()
							),
							'preview' => array(
								'pagination' => true,
								'draggable' => false,
								'editable' => true,
								'removable' => false,
								'changeable' => false
							)
						),
						'folder' => array(
							'title' => __( 'Media Folder', 'responsive-lightbox' ),
							'type' => 'taxonomy',
							'description' => __( 'Select media folder.', 'responsive-lightbox' ),
							'default' => array(
								'id' => 0,
								'children' => false
							),
							'include_children' => true,
							'taxonomy' => $rl->options['folders']['media_taxonomy']
						)
					),
					'remote_library' => array(
						'attachments' => array(
							'title' => '',
							'type' => 'media_preview',
							'default' => array(
								'exclude' => array()
							),
							'preview' => array(
								'pagination' => true,
								'draggable' => false,
								'editable' => false,
								'removable' => false,
								'changeable' => false
							)
						),
						'media_search' => array(
							'title' => __( 'Search string', 'responsive-lightbox' ),
							'type' => 'text',
							'description' => __( 'Enter the search phrase.', 'responsive-lightbox' ),
							'default' => ''
						),
						'media_provider' => array(
							'title' => __( 'Media Providers', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select which remote library should be used.', 'responsive-lightbox' ),
							'default' => 'all',
							'options' => array(
								'all'	=> __( 'All Media Providers', 'responsive-lightbox' )
							)
						),
						'response_data' => array(
							'title' => '',
							'type' => 'hidden',
							'description' => '',
							'default' => '',
							'callback' => array( $rl->remote_library, 'remote_library_response_data' )
						)
					)
				),
				'config' => array(),
				'design' => array(
					'options' => array(
						'show_title' => array(
							'title' => __( 'Thumbnail title', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select title for the gallery thumbnails.', 'responsive-lightbox' ),
							'default' => 'global',
							'options' => $merged_titles
						),
						'show_caption' => array(
							'title' => __( 'Thumbnail caption', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select caption for the gallery thumbnails.', 'responsive-lightbox' ),
							'default' => 'global',
							'options' => $merged_titles
						),
						'show_icon' => array(
							'title' => __( 'Thumbnail icon', 'responsive-lightbox' ),
							'type' => 'radio',
							'description' => __( 'Select icon for the gallery thumbnails.', 'responsive-lightbox' ),
							'default' => '0',
							'options' => array(
								'0' => __( 'none', 'responsive-lightbox' ),
								'1' => '',
								'2' => '',
								'3' => '',
								'4' => '',
								'5' => '',
								'6' => '',
								'7' => '',
								'8' => '',
								'9' => '',
								'10' => ''
							)
						),
						'hover_effect' => array(
							'title' => __( 'Hover effect', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select thumbnail effect on hover.', 'responsive-lightbox' ),
							'default' => '0',
							'options' => array(
								'0' => __( 'none', 'responsive-lightbox' ),
								'1' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 1 ),
								'2' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 2 ),
								'3' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 3 ),
								'4' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 4 ),
								'5' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 5 ),
								'6' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 6 ),
								'7' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 7 ),
								'8' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 8 ),
								'9' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 9 )
							)
						),
						'title_color' => array(
							'title' => __( 'Title Color', 'responsive-lightbox' ),
							'type' => 'color_picker',
							'default' => '#ffffff'
						),
						'caption_color' => array(
							'title' => __( 'Caption Color', 'responsive-lightbox' ),
							'type' => 'color_picker',
							'default' => '#cccccc'
						),
						'background_color' => array(
							'title' => __( 'Background Color', 'responsive-lightbox' ),
							'type' => 'color_picker',
							'default' => '#000000'
						),
						'background_opacity' => array(
							'title' => __( 'Background Opacity', 'responsive-lightbox' ),
							'type' => 'number',
							'default' => 80,
							'step' => 1,
							'min' => 0,
							'max' => 100,
							'append' => '%'
						),
						'border_color' => array(
							'title' => __( 'Border Color', 'responsive-lightbox' ),
							'type' => 'color_picker',
							'default' => '#000000'
						),
						'border_width' => array(
							'title' => __( 'Border Width', 'responsive-lightbox' ),
							'type' => 'number',
							'default' => 0,
							'step' => 1,
							'min' => 0,
							'max' => 100,
							'append' => 'px'
						)
					)
				),
				'paging' => array(
					'options' => array(
						'pagination' => array(
							'title' => __( 'Use pagination', 'responsive-lightbox' ),
							'type' => 'boolean',
							'label' => __( 'Enable pagination.', 'responsive-lightbox' ),
							'default' => false
						),
						'pagination_type' => array(
							'title' => __( 'Pagination type', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select pagination type.', 'responsive-lightbox' ),
							'default' => 'paged',
							'options' => array(
								'paged' => __( 'standard', 'responsive-lightbox' ),
								'ajax' => __( 'AJAX', 'responsive-lightbox' ),
								'infinite' => __( 'infinite scroll', 'responsive-lightbox' )
							)
						),
						'pagination_position' => array(
							'title' => __( 'Pagination position', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select pagination position.', 'responsive-lightbox' ),
							'default' => 'bottom',
							'options' => array(
								'bottom' => __( 'bottom', 'responsive-lightbox' ),
								'top' => __( 'top', 'responsive-lightbox' ),
								'both' => __( 'top & bottom', 'responsive-lightbox' )
							)
						),
						'images_per_page' => array(
							'title' => __( 'Images per page', 'responsive-lightbox' ),
							'type' => 'number',
							'description' => __( 'Number of images per page.', 'responsive-lightbox' ),
							'default' => get_option( 'posts_per_page', 20 ),
							'step' => 1,
							'min' => 0
						),
						'load_more' => array(
							'title' => __( 'Load More', 'responsive-lightbox' ),
							'type' => 'radio',
							'description' => __( 'Select the load more trigger (infinite scroll only).', 'responsive-lightbox' ),
							'default' => 'automatically',
							'options' => array(
								'automatically' => __( 'Automatically', 'responsive-lightbox' ),
								'manually' => __( 'On click', 'responsive-lightbox' )
							)
						)
					)
				),
				'lightbox' => array(
					'options' => array(
						'lightbox_enable' => array(
							'title' => __( 'Enable Lightbox', 'responsive-lightbox' ),
							'type' => 'boolean',
							'label' => __( 'Enable lightbox effect for the gallery.', 'responsive-lightbox' ),
							'default' => true
						),
						'lightbox_image_size' => array(
							'title' => __( 'Image Size', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select image size for gallery lightbox.', 'responsive-lightbox' ),
							'default' => 'global',
							'options' => $sizes
						),
						'lightbox_custom_size' => array(
							'title' => __( 'Custom size', 'responsive-lightbox' ),
							'type' => 'multiple',
							'description' => __( 'Choose the custom image size for gallery lightbox (used if Custom Image size is selected).', 'responsive-lightbox' ),
							'fields' => array(
								'lightbox_custom_size_width' => array(
									'type' => 'number',
									'append' => __( 'width in px', 'responsive-lightbox' ),
									'default' => (int) get_option( 'large_size_w' )
								),
								'lightbox_custom_size_height' => array(
									'type' => 'number',
									'append' => __( 'height in px', 'responsive-lightbox' ),
									'default' => (int) get_option( 'large_size_h' )
								)
							)
						),
						'lightbox_image_title' => array(
							'title' => __( 'Image Title', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select image title for gallery lightbox.', 'responsive-lightbox' ),
							'default' => 'global',
							'options' => $merged_titles
						),
						'lightbox_image_caption' => array(
							'title' => __( 'Image Caption', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select image caption for gallery lightbox (used if supported by selected lightbox effect).', 'responsive-lightbox' ),
							'default' => 'global',
							'options' => $merged_titles
						)
					)
				),
				'misc' => array(
					'options' => array(
						'gallery_title_position' => array(
							'title' => __( 'Title Position', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select where to display the title.', 'responsive-lightbox' ),
							'default' => 'none',
							'options' => $positions
						),
						'gallery_description_position' => array(
							'title' => __( 'Description Position', 'responsive-lightbox' ),
							'type' => 'select',
							'description' => __( 'Select where to display the description.', 'responsive-lightbox' ),
							'default' => 'none',
							'options' => $positions
						),
						'gallery_description' => array(
							'title' => __( 'Gallery Description', 'responsive-lightbox' ),
							'type' => 'textarea',
							'description' => __( 'Enter the gallery description (optional).', 'responsive-lightbox' ),
							'default' => '',
							'class' => 'large-text'
						),
						'gallery_custom_class' => array(
							'title' => __( 'Custom Classes', 'responsive-lightbox' ),
							'type' => 'class',
							'description' => __( 'Add custom, space saparated CSS classes (optional).', 'responsive-lightbox' ),
							'default' => '',
							'class' => 'large-text'
						)
					)
				)
			)
		);

		// is remote library active?
		if ( $rl->options['remote_library']['active'] ) {
			// get providers
			$providers = $rl->remote_library->get_providers();
			$active_providers = $rl->remote_library->get_active_providers();

			// update active providers
			foreach ( $active_providers as $provider ) {
				$this->fields['images']['remote_library']['media_provider']['options'][$provider] = $providers[$provider]['name'];
			}
		}
	}

	/**
	 * Duplicate gallery action in admin.
	 *
	 * @return void
	 */
	public function duplicate_gallery() {
		if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) ) || ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['rl_gallery_nonce'] ) || ( isset( $_REQUEST['rl_gallery_nonce'] ) && ! wp_verify_nonce( esc_attr( $_REQUEST['rl_gallery_nonce'] ), 'responsive-lightbox-duplicate-gallery' ) ) )
			wp_die( __( 'No gallery to duplicate has been supplied!', 'responsive-lightbox' ) );

		// get the original post
		$post_id = ( isset( $_GET['post'] ) ? (int) $_GET['post'] : (int) $_POST['post'] );

		if ( empty( $post_id ) )
			wp_die( __( 'No gallery to duplicate has been supplied!', 'responsive-lightbox' ) );

		if ( ! current_user_can( 'edit_post', $post_id ) )
			wp_die( __( 'You do not have permission to copy this gallery.', 'responsive-lightbox' ) );

		$post = get_post( $post_id );

		// copy the post and insert it
		if ( isset( $post ) && $post != null ) {
			$new_id = $this->create_gallery_duplicate( $post );

			// redirect to the post list screen
			wp_redirect( admin_url( 'edit.php?post_type=' . $post->post_type ) );
			exit;
		} else
			wp_die( esc_attr( __( 'Copy creation failed, could not find original gallery:', 'responsive-lightbox' ) ) . ' ' . htmlspecialchars( $post_id ) );
	}

	/**
	 * Add duplicate link to gallery listing.
	 *
	 * @param array $actions Link actions
	 * @param object $post Post object
	 * @return array
	 */
	public function post_row_actions_duplicate( $actions, $post ) {
		global $pagenow;

		if ( $post->post_type !== 'rl_gallery' )
			return $actions;

		if ( ! current_user_can( 'edit_post', $post->ID ) )
			return $actions;

		// duplicate link
		$actions['duplicate_gallery'] = '<a class="duplicate-gallery" title="' . esc_attr__( 'Duplicate this item', 'responsive-lightbox' ) . '" href="' . wp_nonce_url( admin_url( $pagenow . '?post=' . $post->ID . '&action=duplicate_gallery' ), 'responsive-lightbox-duplicate-gallery', 'rl_gallery_nonce' ) . '">' . __( 'Duplicate', 'responsive-lightbox' ) . '</a>';

		return $actions;
	}

	/**
	 * Create a gallery duplicate.
	 *
	 * @param $post object Post object
	 * @return void|int
	 */
	public function create_gallery_duplicate( $post ) {
		// skip revisions
		if ( $post->post_type == 'revision' )
			return;

		$new_post = apply_filters(
			'rl_duplicate_gallery_args',
			array(
				'menu_order'	 => $post->menu_order,
				'comment_status' => $post->comment_status,
				'ping_status'	 => $post->ping_status,
				'post_author'	 => $post->post_author,
				'post_content'	 => $post->post_content,
				'post_excerpt'	 => $post->post_excerpt,
				'post_mime_type' => $post->post_mime_type,
				'post_parent'	 => $post->post_parent,
				'post_password'	 => $post->post_password,
				'post_status'	 => $post->post_status,
				'post_title'	 => $post->post_title,
				'post_type'		 => $post->post_type,
				'post_date'		 => current_time( 'mysql' ),
				'post_date_gmt'	 => get_gmt_from_date( current_time( 'mysql' ) )
			),
			$post
		);

		$new_post_id = wp_insert_post( $new_post );

		// if the copy is published or scheduled, we have to set a proper slug
		if ( $new_post['status'] == 'publish' || $new_post['status'] == 'future' ) {
			$post_name = wp_unique_post_slug( $post->post_name, $new_post_id, $new_post['status'], $post->post_type, $new_post['post_parent'] );

			$new_post = array();
			$new_post['ID'] = $new_post_id;
			$new_post['post_name'] = $post_name;

			// update the post into the database
			wp_update_post( $new_post );
		}

		// create metadata for the duplicated gallery
		$this->create_gallery_duplicate_metadata( $new_post_id, $post );

		// copy taxonomies
		$this->duplicate_gallery_taxonomies( $new_post_id, $post );

		// action hook for developers
		do_action( 'rl_after_duplicate_gallery', $new_post_id, $post );

		return $new_post_id;
	}

	/**
	 * Create a gallery duplicate metadata.
	 *
	 * @param int $new_post_id Post ID
	 * @param object $post Post object
	 * @return void
	 */
	public function create_gallery_duplicate_metadata( $new_post_id, $post ) {
		if ( empty( $post ) || $post == null )
			return;

		// meta keys to be copied
		$meta_keys = apply_filters( 'rl_duplicate_gallery_meta_keys', get_post_custom_keys( $post->ID ) );

		if ( empty( $meta_keys ) )
			return;

		foreach ( $meta_keys as $meta_key ) {
			// meta values to be copied
			$meta_values = apply_filters( 'rl_duplicate_gallery_meta_values', get_post_custom_values( $meta_key, $post->ID ) );

			foreach ( $meta_values as $meta_value ) {
				$meta_value = maybe_unserialize( $meta_value );

				// add metadata to duplicated post
				add_post_meta( $new_post_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Copy the taxonomies of a gallery to another gallery.
	 *
	 * @param int $new_post_id Post ID
	 * @param object $post Post object
	 * @return void
	 */
	function duplicate_gallery_taxonomies( $new_post_id, $post ) {
		global $wpdb;

		if ( isset( $wpdb->terms ) ) {
			// clear default category
			wp_set_object_terms( $new_post_id, null, 'category' );

			// get gallery taxonomies
			$gallery_taxonomies = get_object_taxonomies( $post->post_type );

			if ( ! empty( $gallery_taxonomies ) ) {
				foreach ( $gallery_taxonomies as $taxonomy ) {
					$terms = array();

					// get taxonomy terms
					$post_terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'orderby' => 'term_order' ) );

					if ( ! empty( $post_terms ) ) {
						foreach ( $post_terms as $term ) {
							$terms[] = $term->slug;
						}
					}

					// copy taxonomy terms
					wp_set_object_terms( $new_post_id, $terms, $taxonomy );
				}
			}
		}
	}

	/**
	 * Add a gallery shortcode.
	 *
	 * @param array $args Shortcode arguments
	 * @return string Generated gallery shortcode
	 */
	public function gallery_shortcode( $args ) {
		// prepare defaults
		$defaults = array(
			'id' => 0
		);

		// merge defaults with arguments
		$args = array_merge( $defaults, $args );

		// parse ID
		$args['id'] = (int) $args['id'];

		// is it gallery?
		if ( get_post_type( $args['id'] ) !== 'rl_gallery' )
			return '';
		
		// get images
		$images = $this->get_gallery_images( $args['id'], array( 'exclude' => true ) );

		if ( ! $images )
			return '';
		
		$attachments = array();

		// build config
		foreach ( $images as $image ) {
			if ( ! empty( $image['id'] ) )
				$attachments[] = $image['id'];
		}
		
		// get config data
		$config = get_post_meta( $args['id'], '_rl_config', true );

		// prepare gallery shortcode parameters
		$fields = array();

		// get main instance
		$rl = Responsive_Lightbox();

		// valid menu item?
		if ( ! empty( $config['menu_item'] ) ) {
			// assign data from db
			$data = $config[$config['menu_item']];

			foreach ( $rl->frontend->get_default_gallery_fields() as $field_name => $field_args ) {
				// replace default values
				if ( array_key_exists( $field_name, $data ) )
					$fields[$field_name] = $data[$field_name];
			}

			// is it default gallery type?
			if ( $config['menu_item'] === 'default' ) {
				// set new gallery type
				$gallery_type = $rl->options['settings']['builder_gallery'];

				// assign gallery settings and defaults
				$gallery_fields = $rl->settings->settings[$gallery_type . '_gallery']['fields'];
				$gallery_defaults = $rl->options[$gallery_type . '_gallery'];
			} else {
				$gallery_type = $config['menu_item'];

				// assign gallery settings and defaults
				$gallery_fields = $rl->settings->settings[$config['menu_item'] . '_gallery']['fields'];
				$gallery_defaults = $rl->defaults[$config['menu_item'] . '_gallery'];
			}

			if ( isset( $gallery_fields, $gallery_defaults ) ) {
				// run through all fields
				foreach ( $gallery_fields as $field_name => $field_args ) {
					if ( $field_args['type'] === 'multiple' ) {
						foreach ( $field_args['fields'] as $subfield_name => $subfield_args ) {
							// field exists in db?
							if ( array_key_exists( $subfield_name, $data ) )
								$fields[$subfield_name] = $data[$subfield_name];
							else
								$fields[$subfield_name] = $gallery_defaults[$subfield_name];
						}
					} else {
						// field exists in db?
						if ( array_key_exists( $field_name, $data ) )
							$fields[$field_name] = $data[$field_name];
						else
							$fields[$field_name] = $gallery_defaults[$field_name];
					}
				}
			}

			// add gallery type
			$fields['type'] = $gallery_type;
		}

		$shortcode = '';

		foreach ( $fields as $arg => $value ) {
			if ( is_array( $value ) )
				$shortcode .= ' ' . $arg . '="' . (string) implode( ',', $value ) . '"';
			else
				$shortcode .= ' ' . $arg . '="' . (string) $value . '"';
		}

		// get design data
		$design = get_post_meta( $args['id'], '_rl_design', true );

		if ( ! empty( $design['menu_item'] ) ) {
			foreach ( $design[$design['menu_item']] as $arg => $value ) {
				$shortcode .= ' ' . $arg . '="' . (string) $value . '"';
			}
		}

		// get lightbox data
		$lightbox = get_post_meta( $args['id'], '_rl_lightbox', true );

		if ( ! empty( $lightbox['menu_item'] ) ) {
			foreach ( $lightbox[$lightbox['menu_item']] as $arg => $value ) {
				$shortcode .= ' ' . $arg . '="' . (string) $value . '"';
			}
		}

		return do_shortcode( '[gallery rl_gallery_id="' . $args['id'] .'" include="' . ( empty( $attachments ) ? '' : implode( ',', $attachments ) ) . '"' . $shortcode . ']' );
	}

	/**
	 * Add a gallery button.
	 *
	 * @param string $editor_id Editor ID
	 * @return void
	 */
	public function add_gallery_button( $editor_id ) {
		if ( get_post_type() === 'rl_gallery' )
			return;

		$this->enqueue_gallery_scripts_styles();

		echo '<button type="button" id="rl-insert-modal-gallery-button" class="button" data-editor="' . $editor_id . '"><span class="wp-media-buttons-icon dashicons dashicons-format-gallery"></span> ' . __( 'Add Gallery', 'responsive-lightbox' ) . '</button>';
	}

	/**
	 * Enqueue scripts and styles needed for gallery modal.
	 *
	 * @return void
	 */
	public function enqueue_gallery_scripts_styles() {
		// assign main instance
		$rl = Responsive_Lightbox();

		wp_enqueue_script( 'responsive-lightbox-admin-gallery', RESPONSIVE_LIGHTBOX_URL . '/js/admin-gallery.js', array( 'jquery' ), $rl->defaults['version'], false );

		wp_localize_script(
			'responsive-lightbox-admin-gallery',
			'rlArgsGallery',
			array(
				'nonce'		=> wp_create_nonce( 'rl-gallery-post' ),
				'post_id'	=> get_the_ID()
			)
		);

		wp_enqueue_style( 'responsive-lightbox-admin-gallery', RESPONSIVE_LIGHTBOX_URL . '/css/admin-gallery.css', array(), $rl->defaults['version'] );
	}

	/**
	 * Modal gallery HTML template.
	 *
	 * @return void
	 */
	public function modal_gallery_template() {
		global $pagenow;

		// display only for post edit pages
		if ( ! ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && get_post_type() !== 'rl_gallery' ) )
			return;

		$rl = Responsive_Lightbox();

		if ( $rl->options['builder']['categories'] ) {
			$terms = get_terms(
				array(
					'taxonomy'		=> 'rl_category',
					'orderby'		=> 'name',
					'order'			=> 'ASC',
					'hide_empty'	=> false,
					'fields'		=> 'id=>name'
				)
			);

			$categories = wp_dropdown_categories(
				array(
					'orderby'			=> 'name',
					'order'				=> 'asc',
					'show_option_none'	=> empty( $terms ) ? __( 'All categories', 'responsive-lightbox' ) : '',
					'show_option_all'	=> __( 'All categories', 'responsive-lightbox' ),
					'show_count'		=> false,
					'hide_empty'		=> false,
					'option_none_value'	=> 0,
					'hierarchical'		=> true,
					'selected'			=> 0,
					'taxonomy'			=> 'rl_category',
					'hide_if_empty'		=> false,
					'echo'				=> false,
					'id'				=> 'rl-media-attachment-categories',
					'class'				=> 'attachment-filters',
					'name'				=> ''
				)
			);
		}

		echo '
		<div id="rl-modal-gallery" style="display: none;">
			<div class="media-modal wp-core-ui">
				<button type="button" class="media-modal-close"><span class="media-modal-icon"><span class="screen-reader-text">' . __( 'Close', 'responsive-lightbox' ) . '</span></span></button>
				<div class="media-modal-content">
					<div class="media-frame mode-select wp-core-ui hide-menu hide-router">
						<div class="media-frame-title">
							<h1 class="wrap">' . __( 'Insert Gallery', 'responsive-lightbox' ) . ' <a class="rl-reload-galleries page-title-action" href="#">' .  __( 'Reload', 'responsive-lightbox' ). '</a><span class="rl-gallery-reload-spinner spinner"></span></h1>
						</div>
						<div class="media-frame-content" data-columns="0">
							<div class="attachments-browser">
								<div class="uploader-inline rl-no-galleries" style="display: none;">
									<div class="uploader-inline-content has-upload-message">
										<h2 class="upload-message">' . esc_html( 'No items found.', 'responsive-lightbox' ) . '</h2>
										<div class="upload-ui">
											<h2 class="upload-instructions">' . esc_html( 'No galleries? Create them first or try another search phrase.', 'responsive-lightbox' ) . '</h2>
										</div>
									</div>
								</div>
								<div class="media-toolbar">' . ( $rl->options['builder']['categories'] ? '
									<div class="media-toolbar-secondary"><label for="rl-media-attachment-categories" class="screen-reader-text">' . esc_html__( 'Filter by category', 'responsive-lightbox' ) . '</label>' . $categories . '</div>' : '' ) . '
									<div class="media-toolbar-primary search-form">
										<label for="rl-media-search-input" class="screen-reader-text">' . __( 'Search galleries', 'responsive-lightbox' ) . '</label><input type="search" placeholder="' . esc_attr__( 'Search galleries', 'responsive-lightbox' ) . '" id="rl-media-search-input" class="search">
									</div>
								</div>
								<ul class="attachments rl-galleries-list ui-sortable ui-sortable-disabled">
								</ul>
								<div class="media-sidebar visible">
									<h2>' . __( 'Select A Gallery', 'responsive-lightbox' ) . '</h2>
									<p>' . __( 'To select a gallery simply click on one of the boxes to the left.', 'responsive-lightbox' ) . '</p>
									<p>' . __( 'To insert your gallery into the editor, click on the "Insert Gallery" button below.', 'responsive-lightbox' ) . '</p>
								</div>
							</div>
						</div>
						<div class="media-frame-toolbar">
							<div class="media-toolbar">
								<div class="media-toolbar-secondary">
									<div class="media-selection empty">
										<div class="selection-info">
											<span class="rl-gallery-count count">' . sprintf( _n( '%s image', '%s images', 0, 'responsive-lightbox' ), 0 ) . '</span>
											<a href="" class="button-link rl-edit-gallery-link">' . __( 'Edit gallery', 'responsive-lightbox' ) . '</a>
										</div>
										<div class="selection-view">
											<span class="rl-gallery-images-spinner spinner" style="display: none;"></span>
											<ul class="attachments rl-attachments-list">
											</ul>
										</div>
									</div>
								</div>
								<div class="media-toolbar-primary search-form">
									<button style="display: none;" type="button" class="button media-button button-primary button-large rl-media-button-insert-gallery" disabled="disabled">' . __( 'Insert gallery into post', 'responsive-lightbox') . '</button>
									<button style="display: none;" type="button" class="button media-button button-primary button-large rl-media-button-select-gallery" disabled="disabled">' . __( 'Select gallery', 'responsive-lightbox') . '</button>
									<button type="button" class="button media-button button-secondary button-large rl-media-button-cancel-gallery">' . __( 'Cancel', 'responsive-lightbox') . '</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="media-modal-backdrop"></div>
		</div>';
	}

	/**
	 * Render gallery field.
	 *
	 * @param string $field Field name
	 * @param string $tab_id Field tab
	 * @param string $menu_item Field parent
	 * @param array $args Field arguments
	 * @param bool $subfield Is this a subfield
	 * @return string Rendered HTML field
	 */
	public function render_field( $field, $tab_id, $menu_item, $args, $gallery_id, $subfield = false ) {
		if ( $subfield ) {
			$template = '%s%s';
			$html = '';
			$subhtml = '';
		} else {
			$template = $args['type'] === 'section' ? '<th colspan="2"><h3>%s</h3></th>' : '<th><label for="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '">%s</label></th><td>%s</td>';
			$html = '<tr class="rl-gallery-field-' . $tab_id . '-' . $menu_item . '-' . $field . ' rl-gallery-field-' . $args['type'] . '" data-field_type="' . $args['type'] . '" data-field_name="' . $field . '">';
			$subhtml = '';
		}

		switch ( $args['type'] ) {
			case 'range':
				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					'<input id="rl_' . $tab_id . '_' . $menu_item . '_' . $field . '" type="range" value="' . $args['value'] . '" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . ']" min="' . ( ! empty( $args['min'] ) ? $args['min'] : 0 ) . '"' . ( ! empty( $args['max'] ) ? ' max="' . $args['max'] . '"' : '' ) . ' step="' . ( ! empty( $args['step'] ) ? $args['step'] : 1 ) . '" oninput="this.form.rl_' . $tab_id . '_' . $menu_item . '_' . $field . '_range.value=this.value" /><output class="rl-gallery-field-output" name="rl_' . $tab_id . '_' . $menu_item . '_' . $field . '_range">' . $args['value'] . '</output>' . ( ! empty( $args['append'] ) ? ' <span>' . esc_html( $args['append'] ) . '</span>' : '' ) . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'radio':
				$subhtml = '';

				foreach ( $args['options'] as $key => $label ) {
					$subhtml .= '<label class="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '" for="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '-' . $key . '"><input id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '-' . $key . '" type="radio" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . ']" value="' . $key . '" ' . checked( $key, $args['value'], false ) . ' />' . esc_html( $label ) . '</label> ';
				}

				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					$subhtml . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'number':
				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					'<input id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '" class="small-text" type="number" value="' . $args['value'] . '" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . ']" min="' . ( ! empty( $args['min'] ) ? $args['min'] : 0 ) . '"' . ( ! empty( $args['max'] ) ? ' max="' . $args['max'] . '"' : '' ) . ' step="' . ( ! empty( $args['step'] ) ? $args['step'] : 1 ) . '" />' . ( ! empty( $args['append'] ) ? ' <span>' . esc_html( $args['append'] ) . '</span>' : '' ) . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'text':
				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					'<input id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '"' . ( ! empty( $args['class'] ) ? ' class="' . $args['class'] . '"' : '' ) . ' type="text" value="' . esc_attr( $args['value'] ) . '" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . ']" />' . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'class':
			case 'textarea':
				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					'<textarea id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '"' . ( ! empty( $args['class'] ) ? ' class="' . $args['class'] . '"' : '' ) . ' name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . ']">' . esc_textarea( $args['value'] ) . '</textarea>' . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'select':
				$subhtml = '<select id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . ']">';

				foreach ( $args['options'] as $key => $label ) {
					$subhtml .= '
					<option value="' . $key . '" ' . selected( $args['value'], $key, false ) . '>' . $label . '</option>';
				}

				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					$subhtml . '</select>' . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'taxonomy':
				if ( taxonomy_exists( $args['taxonomy'] ) ) {
					$subhtml = wp_dropdown_categories(
						array(
							'orderby'			=> 'name',
							'order'				=> 'asc',
							'show_option_none'	=> __( 'None', 'responsive-lightbox' ),
							'show_option_all'	=> false,
							'show_count'		=> false,
							'hide_empty'		=> false,
							'option_none_value'	=> 0,
							'hierarchical'		=> true,
							'selected'			=> $args['value']['id'],
							'taxonomy'			=> $args['taxonomy'],
							'hide_if_empty'		=> false,
							'echo'				=> false,
							'id'				=> 'rl-' . $tab_id . '-' . $menu_item . '-' . $field,
							'name'				=> 'rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . '][id]'
						)
					);
				} else
					$subhtml = '<select><option value="0">' . esc_html__( 'None', 'responsive-lightbox' ) . '</option></select> ';

				if ( isset( $args['include_children'] ) && $args['include_children'] ) {
					$subhtml .= '<label class="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '-include-children" for="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '-include-children"><input id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '-include-children" type="checkbox" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . '][children]" value="true" ' . checked( $args['value']['children'], true, false ) . ' />' . esc_html__( 'Include children.', 'responsive-lightbox' ) . '</label>';
				}

				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					$subhtml . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'multiselect':
				$subhtml = '<select multiple="multiple" class="select2" id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '" data-empty="' . ( (int) empty( $args['value'] ) ) . '" data-type="' . $field . '" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . '][]">';

				if ( $field === 'post_term' ) {
					foreach ( $args['options'] as $taxanomy => $data ) {
						$subhtml .= '<optgroup label="' . esc_attr( $data['label'] ) . '">';

						foreach ( $data['terms'] as $term_id => $name ) {
							$subhtml .= '<option value="' . $term_id . '" ' . selected( in_array( $term_id, $args['value'], false ), true, false ) . '>' . esc_html( $name ) . '</option>';
						}

						$subhtml .= '</optgroup>';
					}
				} else {
					foreach ( $args['options'] as $key => $label ) {
						$subhtml .= '
						<option value="' . $key . '" ' . selected( in_array( $key, $args['value'], false ), true, false ) . '>' . $label . '</option>';
					}
				}

				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					$subhtml . '</select>' . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'boolean':
				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					'<label class="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '" for="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '"><input id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '" type="checkbox" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . ']" value="true" ' . checked( $args['value'], true, false ) . ' />' . $args['label'] . '</label>' . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'checkbox':
				$subhtml = '';

				foreach ( $args['options'] as $key => $label ) {
					$subhtml .= '<label class="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '-' . $key . '" for="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '-' . $key . '"><input id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '-' . $key . '" type="checkbox" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . '][' . $key . ']" value="true" ' . checked( in_array( $key, $args['value'], true ), true, false ) . ' />' . $label . '</label><br />';
				}

				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					$subhtml . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'multiple':
				$subhtml = '';

				foreach ( $args['fields'] as $sub_field => $sub_args ) {
					$subhtml .= $this->render_field( $sub_field, $tab_id, $menu_item, $sub_args, $gallery_id, true ) . '<br />';
				}

				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					$subhtml . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'color_picker':
				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					'<input id="rl-' . $tab_id . '-' . $menu_item . '-' . $field . '" class="color-picker" type="text" value="' . $args['value'] . '" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . ']" data-default-color="' . $args['default'] . '" />' . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
				break;

			case 'media_library':
				$data = get_post_meta( $gallery_id, '_rl_images', true );

				// get images
				if ( ( ! empty( $data['menu_item'] ) && $data['menu_item'] === 'media' ) || ! ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) && $_POST['action'] === 'rl-get-menu-content' ) )
					$images = $this->get_gallery_images( $gallery_id );
				else
					$images = array();

				// get media item template
				$media_item_template = $this->get_media_item_template( $args['preview'] );

				$html .= '
				<td colspan="2" class="rl-colspan">
					<input type="hidden" class="rl-gallery-ids" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . '][ids]" value="' . ( ! empty( $args['value']['ids'] ) ? implode( ',', $args['value']['ids'] ) : '' ) . '">
					<a href="#" class="rl-gallery-select button button-secondary">' . __( 'Select images', 'responsive-lightbox' ) . '</a>
					<div class="rl-gallery-content">
						<ul class="rl-gallery-images rl-gallery-images-media">';

				if ( ! empty( $images ) ) {
					foreach ( $images as $image ) {
						if ( $image['id'] === 0 )
							$excluded_item = $image['url'];
						else
							$excluded_item = $image['id'];

						$image_html = $image['thumbnail_link'];

						// replace ID and URL of an image
						$html .= str_replace( '__IMAGE__', '<input type="hidden" class="rl-gallery-exclude" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . '][exclude][]" value="' . ( in_array( $excluded_item, $args['value']['exclude'], true ) ? $excluded_item : '' ) . '" />' . $image_html, str_replace( '__IMAGE_ID__', $image['id'], str_replace( '__IMAGE_STATUS__', in_array( $excluded_item, $args['value']['exclude'], true ) ? 'rl-status-inactive' : 'rl-status-active', $media_item_template ) ) );
					}
				}

				$html .= '
						</ul>
					</div>
				</td>';
				break;

			case 'media_preview':
				$this->menu_item = $menu_item;

				// get images
				$images = $this->get_gallery_images( $gallery_id );

				// get media item template
				$media_item_template = $this->get_media_item_template( $args['preview'] );

				$html .= '
				<td colspan="2" class="rl-colspan">
					<div class="rl-gallery-preview-inside">
						<a href="#" class="rl-gallery-update-preview button button-secondary">' . __( 'Update preview', 'responsive-lightbox' ) . '</a><span class="spinner" style="display: none;"></span>
					</div>
					<div class="rl-gallery-content">
						<ul class="rl-gallery-images rl-gallery-images-' . $menu_item . '">';

				if ( ! empty( $images ) ) {
					foreach ( $images as $image ) {
						if ( empty( $image['id'] ) ) {
							$excluded_item = $image['url'];
							$image['id'] = 0;
						} else
							$excluded_item = $image['id'];

						$image_html = $image['thumbnail_link'];

						// replace ID and URL of an image
						$html .= str_replace( '__IMAGE__', '<input type="hidden" class="rl-gallery-exclude" name="rl_gallery[' . $tab_id . '][' . $menu_item . '][' . $field . '][exclude][]" value="' . ( in_array( $excluded_item, $args['value']['exclude'], true ) ? $excluded_item : '' ) . '" />' . $image_html, str_replace( '__IMAGE_ID__', $image['id'], str_replace( '__IMAGE_STATUS__', in_array( $excluded_item, $args['value']['exclude'], true ) ? 'rl-status-inactive' : 'rl-status-active', $media_item_template ) ) );
					}
				}

				$html .= '
						</ul>
					</div>';

				if ( ! empty( $args['preview'] ) && isset( $args['preview']['pagination'] )&& $args['preview']['pagination'] )
					$html .= $this->get_preview_pagination( $this->current_page );

				$html .= '
				</td>';
				break;

			case 'hidden':
				// prepare args
				$args['tab_id'] = $tab_id;
				$args['menu_item'] = $menu_item;
				$args['field'] = $field;

				$html .= sprintf(
					$template,
					'',
					call_user_func( $args['callback'], $args )
				);
				break;

			default:
				$html .= sprintf(
					$template,
					! empty( $args['title'] ) ? esc_html( $args['title'] ) : '',
					apply_filters( 'rl_render_gallery_field_' . $args['type'], $subhtml, $field, $tab_id, $menu_item, $args, $subfield ) . ( ! empty ( $args['description'] ) ? '<p class="description">' . $args['description'] . '</p>' : '' )
				);
		}

		if ( ! $subfield )
			$html .= '</tr>';

		return apply_filters( 'rl_render_gallery_field', $html, $field, $tab_id, $menu_item, $args, $subfield );
	}

	/**
	 * Get preview pagination.
	 *
	 * @param int $current_page Page number
	 * @return string Generated HTML
	 */
	public function get_preview_pagination( $current_page ) {
		$items = '';
		$page_links = array();
		$total_pages = $current_page + 1;
		$current = $current_page;
		$disable_first = $disable_last = $disable_prev = $disable_next = false;
		$current_url = 'preview_page';

		if ( $current == 1 ) {
			$disable_first = true;
			$disable_prev  = true;
		} elseif ( $current == 2 )
			$disable_first = true;

		if ( $current == $total_pages ) {
			$disable_last = true;
			$disable_next = true;
		}

		if ( $current == $total_pages - 1 )
			$disable_last = true;

		if ( $disable_first ) 
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
		else {
			$page_links[] = sprintf(
				'<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
				$current_url,
				__( 'First page', 'responsive-lightbox' ),
				'&laquo;'
			);
		}

		if ( $disable_prev )
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
		else {
			$page_links[] = sprintf(
				'<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
				$current_url . '/' . max( 1, $current - 1 ),
				__( 'Previous page', 'responsive-lightbox' ),
				'&lsaquo;'
			);
		}

		$html_current_page = sprintf(
			'%s<input disabled="disabled" class="current-page" id="current-page-selector" type="text" name="paged" value="%s" size="%d" aria-describedby="table-paging" /><span class="tablenav-paging-text">',
			'<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page', 'responsive-lightbox' ) . '</label>',
			$current,
			strlen( $total_pages )
		);

		$html_total_pages = sprintf( '<span class="total-pages">%s</span>', number_format_i18n( $total_pages ) );
		// $page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . '</span></span>';
		$page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s', 'paging' ), $html_current_page, $html_total_pages ) . '</span></span>';

		if ( $disable_next )
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
		else {
			$page_links[] = sprintf(
				'<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
				$current_url . '/' . min( $total_pages, $current + 1 ),
				__( 'Next page', 'responsive-lightbox' ),
				'&rsaquo;'
			);
		}

		if ( $disable_last )
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
		else {
			$page_links[] = sprintf(
				'<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
				$current_url . '/' . $total_pages,
				__( 'Last page', 'responsive-lightbox' ),
				'&raquo;'
			);
		}

		if ( $total_pages )
			$page_class = $total_pages < 2 ? ' one-page' : '';
		else
			$page_class = ' no-pages';

		return '<div class="rl-gallery-preview-pagination tablenav"><div class="tablenav-pages' . $page_class . '">' . $items . '<span class="pagination-links">' . join( "\n", $page_links ) . '</span></div>';
	}

	/**
	 * Sanitize field based on type. Internal use only.
	 *
	 * @param string $field Field name
	 * @param mixed $value Field value
	 * @param array $args Field arguments
	 * @return mixed Sanitized value
	 */
	public function sanitize_field( $field, $value, $args ) {
		switch ( $args['type'] ) {
			case 'radio':
			case 'select':
				$value = array_key_exists( $value, $args['options'] ) ? $value : $args['default'];
				break;

			case 'taxonomy':
				if ( is_array( $value ) ) {
					if ( isset( $value['id'] ) )
						$value['id'] = (int) $value['id'];
					else
						$value['id'] = 0;

					$value['children'] = isset( $value['children'] );
				} else
					$value = $args['default'];

				// get term
				$term = get_term( $value['id'], $args['taxonomy'] );

				// valid term?
				if ( is_a( $term, 'WP_Term' ) )
					$value['id'] = $term->term_id;
				else
					$value['id'] = 0;
				break;

			case 'multiselect':
				if ( is_array( $value ) ) {
					// is it post term field?
					if ( $field === 'post_term' ) {
						$terms = array();

						foreach ( $args['options'] as $data ) {
							$terms += $data['terms'];
						}

						$args['options'] = $terms;
					}

					$values = array();

					foreach ( $value as $subvalue ) {
						if ( array_key_exists( $subvalue, $args['options'] ) )
							$values[] = $subvalue;
					}

					$value = $values;
				} else
					$value = $args['default'];
				break;

			case 'checkbox':
				if ( is_array( $value ) && ! empty( $value ) ) {
					$sort = array();

					foreach ( $value as $sort_key => $bool ) {
						if ( array_key_exists( $sort_key, $args['options'] ) )
							$sort[] = $sort_key;
					}

					$value = $sort;
				} else
					$value = array();
				break;

			case 'boolean':
				$value = $value === 'true';
				break;

			case 'range':
			case 'number':
				$value = (int) $value;

				// is value lower than?
				if ( isset( $args['min'] ) && $value < $args['min'] )
					$value = $args['min'];

				// is value greater than?
				if ( isset( $args['max'] ) && $value > $args['max'] )
					$value = $args['max'];
				break;

			case 'class':
				$value = trim( $value );

				// more than 1 class?
				if ( strpos( $value, ' '  ) !== false ) {
					// get unique valid HTML classes
					$value = array_unique( array_filter( array_map( 'sanitize_html_class', explode( ' ', $value ) ) ) );

					if ( ! empty( $value ) )
						$value = implode( ' ', $value );
					else
						$value = '';
				// single class
				} else
					$value = sanitize_html_class( $value );
				break;

			case 'text':
				$value = trim( sanitize_text_field( $value ) );
				break;

			case 'textarea':
				global $wp_version;

				// WP 4.7+
				if ( version_compare( $wp_version, '4.7', '>=' ) )
					$value = trim( sanitize_textarea_field( $value ) );
				// _sanitize_text_fields
				else {
					$value = wp_check_invalid_utf8( $value );

					if ( strpos( $value, '<' ) !== false ) {
						$value = wp_pre_kses_less_than( $value );

						// this will strip extra whitespace for us.
						$value = wp_strip_all_tags( $value, false );

						// use html entities in a special case to make sure no later newline stripping stage could lead to a functional tag
						$value = str_replace( "<\n", "&lt;\n", $value );
					}

					$value = trim( $value );
					$found = false;

					while ( preg_match('/%[a-f0-9]{2}/i', $value, $match ) ) {
						$value = str_replace( $match[0], '', $value );
						$found = true;
					}

					// strip out the whitespace that may now exist after removing the octets.
					if ( $found )
						$value = trim( preg_replace( '/ +/', ' ', $value ) );
				}
				break;

			case 'color_picker':
				if ( preg_match( '/^#[a-f0-9]{6}$/i', $value ) !== 1 )
					$value = $args['default'];
				break;

			case 'media_library':
				if ( is_array( $value ) ) {
					$data = $args['default'];

					// check IDs
					if ( array_key_exists( 'ids', $value ) ) {
						$ids = (string) trim( $value['ids'] );

						if ( $ids !== '' )
							// get unique and non empty attachment IDs only
							$data['ids'] = $this->check_attachments( array_unique( array_filter( array_map( 'intval', explode( ',', $ids ) ) ) ) );
						else
							$data['ids'] = array();
					}

					// check excluded items
					if ( array_key_exists( 'exclude', $value ) && is_array( $value['exclude'] ) && ! empty( $value['exclude'] ) ) {
						$ids = $strings = array();

						foreach ( $value['exclude'] as $exclude_item ) {
							$item = trim( $exclude_item );

							if ( is_numeric( $item ) )
								$ids[] = (int) $item;
							elseif ( $item !== '' )
								$strings[] = $item;
						}

						if ( ! empty( $ids ) ) {
							// get unique and non empty attachment IDs only
							$ids = $this->check_attachments( array_unique( array_filter( $ids ) ) );
						}

						$data['exclude'] = $ids + $strings;
					}

					$value = $data;
				} else
					$value = $args['default'];
				break;

			case 'media_preview':
				if ( is_array( $value ) ) {
					$data = $args['default'];

					// check excluded items
					if ( array_key_exists( 'exclude', $value ) && is_array( $value['exclude'] ) && ! empty( $value['exclude'] ) ) {
						$ids = $strings = array();

						foreach ( $value['exclude'] as $exclude_item ) {
							$item = trim( $exclude_item );

							if ( is_numeric( $item ) )
								$ids[] = (int) $item;
							elseif ( $item !== '' )
								$strings[] = $item;
						}

						if ( ! empty( $ids ) ) {
							// get unique and non empty attachment IDs only
							$ids = $this->check_attachments( array_unique( array_filter( $ids ) ) );
						}

						$data['exclude'] = $ids + $strings;
					}

					$value = $data;
				} else
					$value = $args['default'];
		}

		return apply_filters( 'rl_sanitize_gallery_field', $value, $args );
	}

	/**
	 * Sanitize set of fields.
	 *
	 * @param array $items Fields
	 * @param array $data POST data
	 * @param string $tab_id Gallery tab
	 * @param string $menu_item Gallery menu item
	 * @return array Sanitize fields
	 */
	public function sanitize_fields( $items, $data, $tab_id, $menu_item ) {
		$safedata = array();

		foreach ( $items as $field => $item ) {
			// skip this field
			if ( isset( $item['save'] ) && ! $item['save'] )
				continue;

			// available field?
			if ( isset( $data[$tab_id], $data[$tab_id][$menu_item], $data[$tab_id][$menu_item][$field] ) )
				$safedata[$tab_id][$menu_item][$field] = $this->sanitize_field( $field, $data[$tab_id][$menu_item][$field], $item );
			// boolean field?
			elseif ( $item['type'] === 'boolean' )
				$safedata[$tab_id][$menu_item][$field] = false;
			// multiple fields?
			elseif ( $item['type'] === 'multiple' ) {
				foreach ( $item['fields'] as $subfield => $subitem ) {
					// available subfield?
					if ( isset( $data[$tab_id], $data[$tab_id][$menu_item], $data[$tab_id][$menu_item][$subfield] ) )
						$safedata[$tab_id][$menu_item][$subfield] = $this->sanitize_field( $subfield, $data[$tab_id][$menu_item][$subfield], $subitem );
					// boolean subfield?
					elseif ( $subitem['type'] === 'boolean' )
						$safedata[$tab_id][$menu_item][$subfield] = false;
					// any other case
					else
						$safedata[$tab_id][$menu_item][$subfield] = $subitem['default'];
				}
			// any other case
			} else
				$safedata[$tab_id][$menu_item][$field] = $item['default'];
		}

		return $safedata;
	}

	/**
	 * Add menu tabs after the post title.
	 *
	 * @param object $post Post object
	 * @return void
	 */
	public function after_title_nav_menu( $post ) {
		if ( $post->post_type !== 'rl_gallery' )
			return;
		
		global $wp_meta_boxes;

		$active_tab = ! empty( $_GET['rl_active_tab'] ) && array_key_exists( $_GET['rl_active_tab'], $this->tabs ) ? $_GET['rl_active_tab'] : 'images';

		// this will be moved to #postbox-container-2 using js to avoid mobile devices problem
		echo '
		<h2 class="nav-tab-wrapper">';

		foreach ( $this->tabs as $key => $data ) {
			echo '
			<a id="rl-gallery-tab-' . $key . '" class="rl-gallery-tab nav-tab' . ( $key === $active_tab ? ' nav-tab-active' : '' ) . '" href="#' . $key . '">' . $data['label'] . '</a>';
		}

		echo '
		</h2>';

		do_meta_boxes( $post->post_type, 'after_title', $post );

		unset( $wp_meta_boxes[$post->post_type]['after_title'] );
	}

	/**
	 * Add class to hide metabox.
	 *
	 * @param array $classes Classes
	 * @return array Classes
	 */
	public function hide_metabox( $classes ) {
		$classes[] = 'rl-metabox-content';
		$classes[] = 'rl-hide-metabox';

		return $classes;
	}

	/**
	 * Add class to display the metabox.
	 *
	 * @param array $classes Classes
	 * @return array Classes
	 */
	function display_metabox( $classes ) {
		$classes[] = 'rl-metabox-content';
		$classes[] = 'rl-display-metabox';

		return $classes;
	}

	/**
	 * Add active tab to post redirect destination URL.
	 *
	 * @param string $location Destination URL
	 * @return string Detination URL with extra arg
	 */
	function add_active_tab( $location ) {
		return add_query_arg( 'rl_active_tab', ! empty( $_POST['rl_active_tab'] ) && array_key_exists( $_POST['rl_active_tab'], $this->tabs ) ? $_POST['rl_active_tab'] : 'images', $location );
	}

	/**
	 * Add metaboxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		$active_tab = ! empty( $_GET['rl_active_tab'] ) && array_key_exists( $_GET['rl_active_tab'], $this->tabs ) ? $_GET['rl_active_tab'] : 'images';

		// normal metaboxes
		foreach ( $this->tabs as $key => $args ) {
			if ( $key === 'images' )
				$new_args = $args + array( 'tab_id' => $key, 'active_tab' => $active_tab );
			else
				$new_args = $args + array( 'tab_id' => $key );

			// handle metabox class
			if ( $active_tab === $key )
				add_filter( 'postbox_classes_rl_gallery_responsive-gallery-' . $key, array( $this, 'display_metabox' ) );
			else
				add_filter( 'postbox_classes_rl_gallery_responsive-gallery-' . $key, array( $this, 'hide_metabox' ) );

			add_meta_box( 'responsive-gallery-' . $key, sprintf( __( 'Gallery %s', 'responsive-lightbox' ), $args['label'] ), array( $this, 'add_metabox' ), 'rl_gallery', 'after_title', 'high', $new_args );
		}

		// side metaboxes
		add_meta_box( 'responsive-gallery-shortcode', __( 'Gallery Code', 'responsive-lightbox' ), array( $this, 'shortcode_metabox' ), 'rl_gallery', 'side', 'core' );
	}

	/**
	 * Add single metabox.
	 *
	 * @param type $post Post object
	 * @param array $callback_args Arguments
	 * @return void
	 */
	public function add_metabox( $post, $callback_args ) {
		$html = $callback_args['args']['tab_id'] === 'images' ? '<input type="hidden" name="rl_active_tab" value="' . $callback_args['args']['active_tab'] . '" />' : '';

		// default menu item
		$menu_item = 'options';

		// get tab data
		$data = get_post_meta( $post->ID, '_rl_' . $callback_args['args']['tab_id'], true );

		if ( ! is_array( $data ) )
			$data = array();

		// maybe add description
		$html .= ! empty( $callback_args['args']['description'] ) ? '<p class="rl-gallery-tab-description">' . esc_html( $callback_args['args']['description'] ) . '</p>' : '';

		// assign main instance
		$rl = Responsive_Lightbox();

		// maybe add menu
		if ( ! empty( $callback_args['args']['menu_items'] ) ) {
			// get selected menu item
			$menu_item = ! empty( $data['menu_item'] ) && in_array( $data['menu_item'], array_keys( $callback_args['args']['menu_items'] ) ) ? $data['menu_item'] : key( $callback_args['args']['menu_items'] );

			$html .= '
			<div class="rl-gallery-tab-menu rl-gallery-tab-menu-' . $callback_args['args']['tab_id'] . '">';

			foreach ( $callback_args['args']['menu_items'] as $menu_key => $menu_label ) {
				// disable select for remote library if needed
				if ( $menu_key === 'remote_library' && ! $rl->options['remote_library']['active'] ) {
					$title = ' title="' . __( 'Remote Library is disabled. Enable it in the settings.', 'responsive-lightbox' ) . '"';
					$disabled = ' disabled="disabled"';
				// disable select for media folders if needed
				} elseif ( $menu_key === 'folders' && ! $rl->options['folders']['active'] ) {
					$title = ' title="' . __( 'Media Folders are disabled. Enable it in the settings.', 'responsive-lightbox' ) . '"';
					$disabled = ' disabled="disabled"';
				// other menu items
				} else {
					$title = '';
					$disabled = '';
				}

				$html .= '
				<label' . $title . '><input type="radio" class="rl-gallery-tab-menu-item" name="rl_gallery[' . $callback_args['args']['tab_id'] . '][menu_item]" value="' . $menu_key . '" ' . checked( $menu_item, $menu_key, false ) . $disabled . ' />' . esc_html( $menu_label ) . ( $callback_args['args']['tab_id'] === 'config' && $menu_key === 'default' ? ' (' . $this->tabs['config']['menu_items'][$rl->options['settings']['builder_gallery']] . ')' : '' ) . '</label>';
			}

			$html .= '
				<span class="spinner" style="display: none;"></span>
			</div>';
		}

		$content = '';

		// disable gallery images content for remote library or media folders if needed
		if ( $callback_args['args']['tab_id'] === 'images' && ( ( $menu_item === 'remote_library' && ! $rl->options['remote_library']['active'] ) || ( $menu_item === 'folders' && ! $rl->options['folders']['active'] ) ) )
			$content = ' rl-loading-content';

		$html .= '
			<div class="rl-gallery-tab-content rl-gallery-tab-content-' . $callback_args['args']['tab_id'] . $content . '">';

		$html .= ! empty( $callback_args['args']['callback'] ) && is_callable( $callback_args['args']['callback'] ) ? call_user_func( $callback_args['args']['callback'], $callback_args['args']['tab_id'], $data, $menu_item, $post->ID ) : $this->get_metabox_content( $callback_args['args']['tab_id'], $data, $menu_item, $post->ID );

		$html .= '
			</div>';

		echo $html;
	}

	/**
	 * Get single metabox content.
	 *
	 * @param string $tab_id Tab ID
	 * @param array $data Metabox data
	 * @param string $menu_item Specified menu item
	 * @param int $gallery_id Gallery ID
	 * @return string
	 */
	public function get_metabox_content( $tab_id, $data, $menu_item, $gallery_id = 0 ) {
		$html = '
			<div class="rl-gallery-tab-inside rl-gallery-tab-inside-' . $tab_id . '-' . $menu_item . '">
				<table class="form-table">';

		switch ( $tab_id ) {
			case 'config':
				// assign main instance
				$rl = Responsive_Lightbox();

				// get default gallery fields
				$default_gallery = $rl->frontend->get_default_gallery_fields();

				// assign settings and defaults
				$settings = $rl->settings->settings;
				$defaults = $rl->defaults;

				if ( ! array_key_exists( 'default_gallery', $settings ) )
					$settings['default_gallery']['fields'] = $default_gallery;

				// assign default values
				foreach ( $default_gallery as $field => $field_args ) {
					$defaults['default_gallery'][$field] = $field_args['default'];
				}

				// valid gallery?
				if ( array_key_exists( $menu_item . '_gallery', $settings ) && array_key_exists( $menu_item . '_gallery', $defaults ) ) {
					if ( $menu_item === 'default' )
						$fields = $settings['default_gallery']['fields'];
					else {
						$fields = $rl->frontend->get_unique_fields( $settings['default_gallery']['fields'], $settings[$menu_item . '_gallery']['fields'] );

						// add default gallery default values
						foreach ( $default_gallery as $field => $field_args ) {
							$defaults[$menu_item . '_gallery'][$field] = $field_args['default'];
						}
					}

					foreach ( $fields as $field => $args ) {
						if ( $args['type'] === 'multiple' ) {
							$new_args = $args;

							foreach ( $args['fields'] as $subfield => $subargs ) {
								$new_args['fields'][$subfield] = $subargs + array(
									'value' => isset( $data[$menu_item], $data[$menu_item][$subfield] ) ? $data[$menu_item][$subfield] : $defaults[$menu_item . '_gallery'][$subfield],
									'default' => $defaults[$menu_item . '_gallery'][$subfield]
								);
							}
						} else {
							$new_args = $args + array(
								'value' => isset( $data[$menu_item], $data[$menu_item][$field] ) ? $data[$menu_item][$field] : $defaults[$menu_item . '_gallery'][$field],
								'default' => $defaults[$menu_item . '_gallery'][$field]
							);
						}

						$html .= $this->render_field( $field, $tab_id, $menu_item, $new_args, $gallery_id );
					}
				// just in case ajax would fail
				} else
					$html .= '<p>' . __( 'No data', 'responsive-lightbox' ) . '</p>';
				break;

			default:
				foreach ( $this->fields[$tab_id][$menu_item] as $field => $args ) {
					// was this field stored in a database?
					if ( isset( $args['save'] ) && ! $args['save'] )
						$new_args = $args;
					elseif ( $args['type'] === 'multiple' ) {
						$new_args = $args;

						foreach ( $args['fields'] as $subfield => $subargs ) {
							$new_args['fields'][$subfield] = $subargs + array( 'value' => isset( $data[$menu_item], $data[$menu_item][$subfield] ) ? $data[$menu_item][$subfield] : $subargs['default'] );
						}
					} else
						$new_args = $args + array( 'value' => isset( $data[$menu_item], $data[$menu_item][$field] ) ? $data[$menu_item][$field] : $args['default'] );

					// media preview?
					// if ( $tab_id === 'images' && $menu_item === 'featured' && $field === 'attachments' && $args['type'] === 'media_preview' )
						// $new_args['subfields'] = $data['featured'];

					$html .= $this->render_field( $field, $tab_id, $menu_item, $new_args, $gallery_id );
				}
		}

		$html .= '
				</table>
			</div>';

		return apply_filters( 'rl_gallery_tab_content', $html, $tab_id, $data, $menu_item );
	}

	/**
	 * Get gallery images.
	 *
	 * @param int $gallery_id Gallery ID
	 * @param array $args Gallery arguments
	 * @return array
	 */
	public function get_gallery_images( $gallery_id = 0, $args = array() ) {
		$images = array();
		$excluded = array();

		// assign main instance
		$rl = Responsive_Lightbox();

		// get args
		$defaults = array(
			'exclude'				=> false,
			'posts_per_page'		=> -1,
			'images_per_page'		=> 0,
			'page'					=> 1,
			'nopaging'				=> true,
			'image_size'			=> 'large',
			'thumbnail_size'		=> 'thumbnail',
			'pagination_type'		=> 'paged',
			'pagination_position'	=> 'bottom',
			'orderby'				=> 'menu_order',
			'order'					=> 'asc',
			'preview'				=> is_admin(),
			'preview_type'			=> 'update',
			'preview_page'			=> 1,
			'preview_per_page'		=> 20,
			'taxonomy'				=> $rl->options['folders']['media_taxonomy'],
			'folder'				=> array(
				'id'		=> 0,
				'children'	=> null
			)
		);

		// parse arguments
		$args = wp_parse_args( apply_filters( 'rl_get_gallery_images_args', $args, $gallery_id ), $defaults );

		// sanitize args
		$args['exclude'] = (bool) ! empty( $args['exclude'] );
		$args['posts_per_page'] = ! empty( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : -1;
		$args['nopaging'] = (bool) ! empty( $args['nopaging'] );

		// is it rl_gallery?
		if ( $valid_gallery_type = ( get_post_type( $gallery_id ) === 'rl_gallery' ) ) {
			$paging = get_post_meta( $gallery_id, '_rl_paging', true );

			if ( isset( $paging['menu_item'] ) ) {
				$pagination = $paging[$paging['menu_item']];

				if ( $pagination['pagination'] ) {
					$args['nopaging'] = false;
					$args['images_per_page'] = $pagination['images_per_page'];
					$args['pagination_type'] = $pagination['pagination_type'];

					// infinite type?
					if ( $args['pagination_type'] === 'infinite' )
						$args['pagination_position'] = 'bottom';
					else
						$args['pagination_position'] = $pagination['pagination_position'];
				} else
					$args['nopaging'] = true;
			}
		}

		global $pagenow;

		// is it preview?
		if ( ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) && $gallery_id ) || ( isset( $_POST['action'] ) && $_POST['action'] === 'rl-get-preview-content' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) && ( $_POST['action'] === 'rl-post-gallery-preview' || $_POST['action'] === 'rl-get-menu-content' ) ) )
			$args['images_per_page'] = 0;

		if ( isset( $_GET['rl_page'] ) )
			$args['page'] = (int) $_GET['rl_page'];
		else
			$args['page'] = (int) $args['page'];

		// is it rl_gallery?
		if ( $valid_gallery_type ) {
			// get config metadata
			$config_meta = get_post_meta( $gallery_id, '_rl_config', true );

			// config order
			if ( isset( $config_meta['menu_item'] ) ) {
				$config = $config_meta[$config_meta['menu_item']];

				$args['orderby'] = $config['orderby'];
				$args['order'] = $config['order'];
			}

			// get images metadata
			$data = get_post_meta( $gallery_id, '_rl_images', true );

			// array?
			if ( ! is_array( $data ) )
				$data = array();

			// get menu item
			if ( ! empty( $this->menu_item ) )
				$menu_item = $this->menu_item;
			elseif ( array_key_exists( 'menu_item', $data ) )
				$menu_item = $data['menu_item'];
			else
				$menu_item = 'media';

			// valid data?
			if ( ! array_key_exists( $menu_item, $data ) )
				$data[$menu_item] = array();

			if ( $args['preview'] && $this->fields['images'][$menu_item]['attachments']['preview']['pagination'] ) {
				if ( isset( $args['preview_page'] ) )
					$args['preview_page'] = (int) $args['preview_page'];
				else
					$args['preview_page'] = 1;

				$args['preview_per_page'] = (int) $args['preview_per_page'];
			}

			switch ( $menu_item ) {
				case 'media':
					// get attachment ids
					$attachments = ! empty( $data[$menu_item]['attachments']['ids'] ) ? array_map( 'absint', $data[$menu_item]['attachments']['ids'] ) : array();

					if ( $attachments ) {
						if ( $args['exclude'] )
							$excluded = ! empty( $data[$menu_item]['attachments']['exclude'] ) ? $data[$menu_item]['attachments']['exclude'] : array();

						foreach ( $attachments as $attachment_id ) {
							// real attachment?
							if ( ! wp_attachment_is_image( $attachment_id ) )
								continue;

							// get attachment image data
							if ( ! in_array( $attachment_id, $excluded ) )
								$images[] = $this->get_gallery_image_src( $attachment_id, $args['image_size'], $args['thumbnail_size'] );
						}
					}
					break;

				case 'featured':
					// copy arguments
					$query_args = $args;

					// prevent duplicating images order (config tab) with posts order (images tab), query will handle empty strings
					if ( array_key_exists( 'post_orderby', $args ) )
						$query_args['orderby'] = $args['post_orderby'];
					elseif ( array_key_exists( 'orderby', $data[$menu_item] ) )
						$query_args['orderby'] = $data[$menu_item]['orderby'];
					else
						$query_args['orderby'] = '';

					if ( array_key_exists( 'post_order', $args ) )
						$query_args['order'] = $args['post_order'];
					elseif ( array_key_exists( 'order', $data[$menu_item] ) )
						$query_args['order'] = $data[$menu_item]['order'];
					else
						$query_args['order'] = '';

					// get attachment ids
					$attachments = $this->gallery_query( array_merge( $data[$menu_item], $query_args ) );

					if ( $attachments ) {
						if ( $args['exclude'] )
							$excluded = ! empty( $data[$menu_item]['attachments']['exclude'] ) ? $data[$menu_item]['attachments']['exclude'] : array();

						foreach ( $attachments as $attachment_id ) {
							// get attachment image data
							if ( ! in_array( $attachment_id, $excluded ) )
								$images[] = $this->get_gallery_image_src( $attachment_id, $args['image_size'], $args['thumbnail_size'] );
						}
					}
					break;

				case 'folders':
					if ( ! array_key_exists( 'folder', $data[$menu_item] ) )
						$data[$menu_item]['folder'] = $defaults['folder'];

					// AJAX requests
					if ( is_string( $args['folder']['id'] ) )
						$args['folder']['id'] = (int) $args['folder']['id'];

					// not empty folder term ID?
					if ( ! empty( $args['folder']['id'] ) ) {
						// get term
						$term = get_term( $args['folder']['id'], $args['taxonomy'] );

						// valid term?
						if ( is_a( $term, 'WP_Term' ) )
							$folder_id = (int) $term->term_id;
						else
							$folder_id = (int) $data[$menu_item]['folder']['id'];
					} else {
						if ( isset( $_POST['action'] ) && $_POST['action'] === 'rl-get-preview-content' )
							$folder_id = $args['folder']['id'];
						else
							$folder_id = (int) $data[$menu_item]['folder']['id'];
					}

					if ( $folder_id > 0 ) {
						$include_children = false;

						// null means folder was not changed
						if ( $args['folder']['children'] === null ) {
							if ( array_key_exists( 'children', $data[$menu_item]['folder'] ) && $data[$menu_item]['folder']['children'] === true )
								$include_children = true;
						// overwritten by args
						} else {
							if ( is_string( $args['folder']['children'] ) ) {
								if ( $args['folder']['children'] === 'true' )
									$include_children = true;
							} elseif ( is_bool( $args['folder']['children'] ) ) {
								if ( $args['folder']['children'] )
									$include_children = true;
							}
						}

						// prepare query arguments
						$wp_query_args = array(
							'post_type'			=> 'attachment',
							'post_status'		=> 'inherit',
							'nopaging'			=> true,
							'posts_per_page'	=> -1,
							'fields'			=> 'ids',
							'tax_query'			=> array(
								array(
									'taxonomy'			=> $args['taxonomy'],
									'field'				=> 'term_id',
									'terms'				=> $folder_id,
									'include_children'	=> $include_children,
									'operator'			=> 'IN'
								)
							)
						);

						// is it preview?
						if ( $args['preview'] ) {
							$wp_query_args['posts_per_page'] = $args['preview_per_page'];
							$wp_query_args['offset'] = ( $args['preview_page'] - 1 ) * $args['preview_per_page'];
							$wp_query_args['nopaging'] = false;
						}

						// run query
						$query = new WP_Query( $wp_query_args );

						// get attachment IDs
						$attachments = $query->get_posts();

						// valid attachments?
						if ( ! is_wp_error( $attachments ) ) {
							// cast IDs to int
							$attachments = array_map( 'intval', $attachments );

							// make sure to skip duplicates
							$attachments = array_unique( $attachments );

							if ( $args['exclude'] )
								$excluded = ! empty( $data[$menu_item]['attachments']['exclude'] ) ? $data[$menu_item]['attachments']['exclude'] : array();

							foreach ( $attachments as $attachment_id ) {
								// get attachment image data
								if ( ! in_array( $attachment_id, $excluded ) )
									$images[] = $this->get_gallery_image_src( $attachment_id, $args['image_size'], $args['thumbnail_size'] );
							}
						}
					}
					break;

				case 'remote_library':
					// is remote library active?
					if ( ! $rl->options['remote_library']['active'] )
						break;

					// check for
					if ( ! isset( $args['media_search'] ) )
						$args['media_search'] = isset( $data[$menu_item]['media_search'] ) ? $data[$menu_item]['media_search'] : '';

					if ( ! isset( $args['media_provider'] ) )
						$args['media_provider'] = isset( $data[$menu_item]['media_provider'] ) ? $data[$menu_item]['media_provider'] : 'all';

					$images = $rl->remote_library->get_remote_library_images( $args );
					break;
			}
		}

		// config sort order
		switch ( $args['orderby'] ) {
			case 'id':
				$sort = array();

				foreach ( $images as $key => $image ) {
					// set sorting value
					$sort[$key] = $image['id'];
				}

				// sort
				array_multisort( $sort, $args['order'] === 'asc' ? SORT_ASC : SORT_DESC, SORT_NUMERIC, $images );
				break;

			case 'title':
				$sort = array();

				if ( $valid_gallery_type ) {
					// get lightbox data
					$lightbox_meta = get_post_meta( $gallery_id, '_rl_lightbox', true );

					// valid data?
					if ( isset( $lightbox_meta['menu_item'] ) )
						$title_arg = $lightbox_meta[$lightbox_meta['menu_item']]['lightbox_image_title'];
					else
						$title_arg = $rl->options['settings']['gallery_image_title'];
				} else
					$title_arg = $rl->options['settings']['gallery_image_title'];

				$images_copy = $images;

				foreach ( $images_copy as $key => $image ) {
					if ( $title_arg === 'global' )
						$images[$key]['title'] = $rl->frontend->get_attachment_title( $image['id'], $rl->options['settings']['gallery_image_title'] );
					elseif ( $title_arg === 'default' )
						$images[$key]['title'] = '';
					else
						$images[$key]['title'] = $rl->frontend->get_attachment_title( $image['id'], $title_arg );

					// set sorting value
					$sort[$key] = function_exists( 'mb_strtolower' ) ? mb_strtolower( $images[$key]['title'] ) : strtolower( $images[$key]['title'] );
				}

				// sort
				array_multisort( $sort, $args['order'] === 'asc' ? SORT_ASC : SORT_DESC, SORT_STRING, $images );
				break;

			case 'post_date':
				$sort = array();

				foreach ( $images as $key => $image ) {
					// set sorting value
					$sort[$key] = $image['date'];
				}

				// sort
				array_multisort( $sort, $args['order'] === 'asc' ? SORT_ASC : SORT_DESC, $images );
				break;

			case 'menu_order':
				// do nothing
				break;

			case 'rand':
				shuffle( $images );
				break;
		}

		$images = apply_filters( 'rl_get_gallery_images_array', $images, $gallery_id, $args );

		// save images count
		$images_count = count( $images );

		if ( metadata_exists( 'post', $gallery_id, '_rl_images_count' ) ) {
			$images_count_prev = get_post_meta( $gallery_id, '_rl_images_count', true );

			update_post_meta( $gallery_id, '_rl_images_count', $images_count, $images_count_prev );
		} else
			add_post_meta( $gallery_id, '_rl_images_count', $images_count );

		if ( $images && ! $args['nopaging'] && $args['images_per_page'] > 0 ) {
			// get part of images
			$images = array_slice( $images, ( $args['page'] - 1 ) * $args['images_per_page'], $args['images_per_page'], true );

			// pass gallery args
			$this->gallery_args = $args;
			$this->gallery_args['total'] = (int) ceil( $images_count / $args['images_per_page'] );

			// pagination position
			if ( $args['pagination_position'] === 'top' )
				add_action( 'rl_before_gallery', array( $this, 'do_pagination' ), 10, 2 );
			elseif ( $args['pagination_position'] === 'bottom' )
				add_action( 'rl_after_gallery', array( $this, 'do_pagination' ), 10, 2 );
			else {
				add_action( 'rl_before_gallery', array( $this, 'do_pagination' ), 10, 2 );
				add_action( 'rl_after_gallery', array( $this, 'do_pagination' ), 10, 2 );
			}
		}

		return apply_filters( 'rl_get_gallery_images', array_values( $images ), $gallery_id, $args );
	}

	/**
     * Create gallery pagination.
	 *
	 * @param array $args Arguments
	 * @param int $gallery_id Arguments
	 * @return string Rendered HTML
     */
    public function do_pagination( $args, $gallery_id ) {
		global $wp;

		// get current action
		$current_action = current_action();

		if ( $current_action === 'rl_before_gallery' )
			$class = ' rl-pagination-top';
		elseif ( $current_action === 'rl_after_gallery' )
			$class = ' rl-pagination-bottom';
		else
			$class = '';

		echo
		'<div class="rl-pagination' . $class . '"' . ( $args['pagination_type'] === 'infinite' ? ' data-button="' . $args['load_more'] . '"' : '' ) .'>' . 
			paginate_links(
				array(
					'format' => '?rl_page=%#%',
					'base' => add_query_arg( array( 'rl_gallery_no' => Responsive_Lightbox()->frontend->gallery_no, 'rl_page' => '%#%' ), $args['pagination_type'] !== 'paged' ? get_permalink( $gallery_id ) : home_url( $wp->request ) ),
					'total' => $this->gallery_args['total'],
					'current' => $this->gallery_args['page'],
					'show_all' => false,
					'end_size' => 1,
					'mid_size' => 2,
					'prev_next' => true,
					'prev_text' => __( '&laquo; Previous', 'responsive-lightbox' ),
					'next_text' => __( 'Next &raquo;', 'responsive-lightbox' ),
					'type' => 'plain',
					'add_args' => '',
					'add_fragment' => '',
					'before_page_number' => '',
					'after_page_number' => ''
				)
			) . 
		'</div>' . ( $args['pagination_type'] === 'infinite' && $args['load_more'] === 'manually' ? '<div class="rl-gallery-button"><button class="rl-button rl-load-more">' . esc_html__( 'Load more', 'responsive-lightbox' ) . '</button></div>' : '' );
	}

	/**
     * Get gallery page.
     *
	 * @param array $args Arguments
     * @return void
     */
    public function get_gallery_page( $args ) {
		if ( isset( $_POST['gallery_id'], $_POST['page'], $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'rl_nonce' ) ) {
			$_GET['rl_page'] = (int) $_POST['page'];

			echo $this->gallery_shortcode( array( 'id' => (int) $_POST['gallery_id'] ) );
		}

		exit;
    }

	/**
     * Generate gallery preview.
     *
     * @return void
     */
    public function post_gallery_preview() {
		if ( ! isset( $_POST['post_id'], $_POST['gallery_id'], $_POST['nonce'] ) || ! check_ajax_referer( 'rl-gallery-post', 'nonce', false ) || ! current_user_can( 'edit_post', (int) $_POST['post_id'] ) )
			wp_send_json_error();

		// parse gallery ID
		$gallery_id = (int) $_POST['gallery_id'];

		// get gallery data
		$data = get_post_meta( $gallery_id, '_rl_images', true );

		// prepare data
		$attachments = $exclude = array();
		$html = '';

		$images = $this->get_gallery_images( $gallery_id, array( 'exclude' => true ) );

		// count attachments
		$images_count = (int) get_post_meta( $gallery_id, '_rl_images_count', true );

		if ( ! empty( $images ) ) {
			foreach ( $images as $image ) {
				$html .= '
				<li role="checkbox" aria-label="" aria-checked="false" data-id="' . $image['id'] . '" class="attachment save-ready rl-status-active' . '">
					<div class="attachment-preview js--select-attachment type-image landscape">
						<div class="thumbnail">
							<div class="centered">
								<img src="' . $image['thumbnail_url'] . '" draggable="false" alt="" />
							</div>
						</div>
					</div>
				</li>';
			}
		}

		// send attachments content
		wp_send_json_success(
			array(
				'attachments'	=> $html,
				'count'			=> sprintf( _n( '%s image', '%s images', $images_count, 'responsive-lightbox' ), $images_count ),
				'edit_url'		=> current_user_can( 'edit_post', $gallery_id ) ? admin_url( 'post.php?post=' . $gallery_id . '&action=edit' ): ''
			)
		);
    }

	/**
     * Get all galleries.
     *
     * @return void
     */
    public function post_get_galleries() {
		if ( ! isset( $_POST['post_id'], $_POST['search'], $_POST['nonce'] ) || ! check_ajax_referer( 'rl-gallery-post', 'nonce', false ) || ! current_user_can( 'edit_post', $post_id = (int) $_POST['post_id'] ) )
			wp_send_json_error();

		$args = array(
			'post_type'			=> 'rl_gallery',
			'post_status'		=> 'publish',
			'nopaging'			=> true,
			'posts_per_page'	=> -1,
			'orderby'			=> 'title',
			'order'				=> 'ASC',
			'suppress_filters'	=> false,
			'no_found_rows'		=> true,
			'cache_results'		=> false
		);

		// specific category?
		if ( ! empty( $_POST['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'			=> 'rl_category',
					'field'				=> 'term_id',
					'operator'			=> 'IN',
					'include_children'	=> false,
					'terms'				=> (int) $_POST['category']
				)
			);
		}

		$search = wp_unslash( trim( $_POST['search'] ) );

		if ( $search !== '' )
			$args['s'] = $search;

		// get galleries
		$query = new WP_Query( $args );

		$html = '';

		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $gallery ) {
				$featured = $this->get_featured_image_src( $gallery->ID );

				if ( is_array( $featured ) && array_key_exists( 'url', $featured ) )
					$featured = $featured['url'];
				else
					$featured = '';

				// get title
				$title = $gallery->post_title !== '' ? $gallery->post_title : __( '(no title)', 'responsive-gallery' );

				$html .= '
				<li role="checkbox" aria-label="' . esc_attr( $title ) . '" aria-checked="false" data-id="' . $gallery->ID . '" class="attachment save-ready">
					<div class="attachment-preview js--select-attachment type-image landscape">
						<div class="thumbnail">
							<div class="centered" data-full-src="' . $featured . '">
								' . $this->get_featured_image( $gallery->ID ) . '
							</div>
							<div class="filename">
								<div>' . esc_html( $title ) . '</div>
							</div>
						</div>
					</div>
					<button type="button" class="button-link check"><span class="media-modal-icon"></span><span class="screen-reader-text">' . __( 'Deselect', 'responsive-lightbox' ) . '</span></button>
				</li>';
			}
		}

		// send galleries content
		wp_send_json_success( $html );
    }

	/**
     * Get gallery content based on request.
     *
     * @return void
     */
    public function get_menu_content() {
		if ( ! isset( $_POST['post_id'], $_POST['tab'], $_POST['menu_item'], $_POST['nonce'] ) || ! check_ajax_referer( 'rl-gallery', 'nonce', false ) || ! current_user_can( 'edit_post', $post_id = (int) $_POST['post_id'] ) || ! array_key_exists( $_POST['tab'], $this->tabs ) )
			wp_send_json_error();

		// get selected menu item
		$menu_item = ! empty( $_POST['menu_item'] ) && in_array( $_POST['menu_item'], array_keys( $this->tabs[$_POST['tab']]['menu_items'] ) ) ? esc_attr( $_POST['menu_item'] ) : key( $this->tabs[$_POST['tab']]['menu_items'] );

		// get tab content
		wp_send_json_success( $this->get_metabox_content( $_POST['tab'], get_post_meta( $post_id, '_rl_' . $_POST['tab'], true ), $menu_item, $post_id ) );
    }

	/**
     * Get gallery preview content based on request.
     *
     * @return void
     */
    public function get_gallery_preview_content() {
		// initial checks
		if ( ! isset( $_POST['post_id'], $_POST['menu_item'], $_POST['nonce'], $_POST['preview_type'] ) || ! check_ajax_referer( 'rl-gallery', 'nonce', false ) )
			wp_send_json_error();

		// cast gallery ID
		$post_id = (int) $_POST['post_id'];

		// check user privileges
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) )
			wp_send_json_error();

		// get query args
		$args = ! empty( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : array();

		// check orderby
		if ( array_key_exists( 'orderby', $args ) ) {
			$args['post_orderby'] = $args['orderby'];

			unset( $args['orderby'] );
		}

		// check order
		if ( array_key_exists( 'order', $args ) ) {
			$args['post_order'] = $args['order'];

			unset( $args['order'] );
		}

		// check preview type
		if ( ! in_array( $_POST['preview_type'], array( 'page', 'update' ), true ) )
			$args['preview_type'] = 'page';
		else
			$args['preview_type'] = $_POST['preview_type'];

		// check for POST menu item
		$menu_item = ! empty( $_POST['menu_item'] ) && is_string( $_POST['menu_item'] ) ? esc_attr( $_POST['menu_item'] ) : '';

		// set images menu item
		$menu_item = $this->menu_item = ! empty( $menu_item ) && array_key_exists( $menu_item, $this->tabs['images']['menu_items'] ) ? $menu_item : 'media';

		if ( $this->fields['images'][$menu_item]['attachments']['preview']['pagination'] ) {
			if ( isset( $args['preview_page'] ) )
				$args['preview_page'] = (int) $args['preview_page'];
			else
				$args['preview_page'] = 1;
		}

		// get images
		$images = $this->get_gallery_images( $post_id, $args );

		$data = array();

		if ( $menu_item === 'remote_library' ) {
			// get main instance
			$rl = Responsive_Lightbox();

			$response_data = array();

			// single provider?
			if ( $args['media_provider'] !== 'all' ) {
				// get provider
				$provider = $rl->providers[$args['media_provider']];

				// add response data arguments if needed
				if ( ! empty( $provider['response_args'] ) ) {
					$response = $provider['instance']->get_response_data();

					foreach ( $provider['response_args'] as $arg ) {
						if ( array_key_exists( $arg, $response ) )
							$response_data[$provider['slug']][$arg] = base64_encode( json_encode( $response[$arg] ) );
					}
				}
			} else {
				// get active providers
				$providers = $this->get_active_providers();

				if ( ! empty( $providers ) ) {
					foreach ( $providers as $provider ) {
						// get provider
						$provider = $rl->providers[$provider];

						// add response data arguments if needed
						if ( ! empty( $provider['response_args'] ) ) {
							$response = $provider['instance']->get_response_data();

							foreach ( $provider['response_args'] as $arg ) {
								if ( array_key_exists( $arg, $response ) )
									$response_data[$provider['slug']][$arg] = base64_encode( json_encode( $response[$arg] ) );
							}
						}
					}
				}
			}

			$data['data'] = $response_data;
		}

		// parse excluded images
		$excluded = ! empty( $_POST['excluded'] ) ? $_POST['excluded'] : array();

		// get excluded images
		if ( $excluded )
			$excluded = array_unique( array_filter( array_map( 'intval', $excluded ) ) );

		// get media item template
		$media_item_template = $this->get_media_item_template( $this->fields['images'][$menu_item]['attachments']['preview'] );

		// build html
		$html = '';

		// any images?
		if ( ! empty( $images ) ) {
			foreach ( $images as $image ) {
				$image_html = $image['thumbnail_link'];

				// replace ID and URL of an image
				$html .= str_replace( '__IMAGE__', '<input type="hidden" class="rl-gallery-exclude" name="rl_gallery[images][' . $menu_item . '][attachments][exclude][]" value="' . ( in_array( $image['id'], $excluded, true ) ? $image['id'] : '' ) . '" />' . $image_html, str_replace( '__IMAGE_ID__', $image['id'], str_replace( '__IMAGE_STATUS__', in_array( $image['id'], $excluded, true ) ? 'rl-status-inactive' : 'rl-status-active', $media_item_template ) ) );
			}
		}

		$data['images'] = $html;

		if ( $this->fields['images'][$menu_item]['attachments']['preview']['pagination'] )
			$data['pagination'] = $this->get_preview_pagination( $args['preview_page'] );

		// send JSON
		wp_send_json_success( $data );
    }

	/**
	 * Get gallery image link.
	 *
	 * @param array $image Image data
	 * @param mixed $size Image size
	 * @param array $attr Image attributes
	 * @return string
	 */
	public function get_gallery_image_link( $image, $size = 'thumbnail', $attr = array() ) {
		$link = '';

		if ( $size === 'thumbnail' ) {
			$url = $image['thumbnail_url'];
			$width = $image['thumbnail_width'];
			$height = $image['thumbnail_height'];
		} else {
			$url = $image['url'];
			$width = $image['width'];
			$height = $image['height'];
		}

		if ( ! empty( $image['url'] ) ) {
			$size_class = $size;

			if ( is_array( $size_class ) )
				$size_class = join( 'x', $size_class );

			// combine attributes
			$attr = wp_parse_args(
				$attr,
				array(
					'src'	=> $url,
					'class'	=> 'attachment-' . $size_class . ' size-' . $size_class . ' format-' . ( $height > $width ? 'portrait' : 'landscape' ),
					'alt'	=> $image['alt']
				)
			);

			// apply filters if any
			$attr = apply_filters( 'rl_get_gallery_image_attributes', $attr, $image, $size );

			// escape every attribute
			$attr = array_map( 'esc_attr', $attr );

			// start link output
			$link = rtrim( '<img ' . image_hwstring( $width, $height ) );

			// add attributes
			foreach ( $attr as $name => $value ) {
				$link .= ' ' . $name . '="' . $value . '"';
			}

			// end link output
			$link .= ' />';
		}

		return apply_filters( 'rl_get_gallery_image_link', $link, $image, $size );
	}

	/**
	 * Get attachment image source.
	 *
	 * @param int|string|array $image Attachment ID, image URL or array of image data
	 * @param string $image_size Image size
	 * @param string $thumbnail_size Thumbnail size
	 * @return array
	 */
	public function get_gallery_image_src( $image, $image_size = 'large', $thumbnail_size = 'thumbnail' ) {
		$imagedata = array();
		$diff_sizes = $thumbnail_size !== $image_size;

		// attachment ID?
		if ( is_int( $image ) ) {
			$image_src = wp_get_attachment_image_src( $image, $image_size, false );

			if ( $image && wp_attachment_is_image( $image ) ) {
				if ( $diff_sizes )
					$thumbnail_src = wp_get_attachment_image_src( $image, $thumbnail_size, false );
				else
					$thumbnail_src = $image_src;

				$imagedata = array(
					'id'				=> $image,
					'title'				=> get_the_title( $image ),
					'date'				=> get_the_date( 'Y-m-d H:i:s', $image ),
					'caption'			=> '',
					'alt'				=> '',
					'url'				=> $image_src[0],
					'width'				=> $image_src[1],
					'height'			=> $image_src[2],
					'thumbnail_url'		=> $thumbnail_src[0],
					'thumbnail_width'	=> $thumbnail_src[1],
					'thumbnail_height'	=> $thumbnail_src[2]
				);
			}
		// image url
		} elseif ( is_string( $image ) ) {
			$imagedata['url'] = $image;

			@list( $imagedata['width'], $imagedata['height'] ) = rl_get_image_size_by_url( $imagedata['url'] );

			$imagedata = array(
				'id'				=> 0,
				'title'				=> '',
				'caption'			=> '',
				'alt'				=> '',
				'url'				=> $imagedata['url'],
				'width'				=> $imagedata['width'],
				'height'			=> $imagedata['height'],
				'thumbnail_url'		=> $imagedata['url'],
				'thumbnail_width'	=> $imagedata['width'],
				'thumbnail_height'	=> $imagedata['height']
			);
		} elseif ( is_array( $image ) ) {
			// set width and height from url, if not available
			if ( empty( $image['width'] ) || empty( $image['height'] ) )
				@list( $image['width'], $image['height'] ) = rl_get_image_size_by_url( $image['url'] );

			// set thumbnail data, if not available
			if ( empty( $image['thumbnail_url'] ) ) {
				$image['thumbnail_url'] = $image['url'];
				$image['thumbnail_width'] = $image['width'];
				$image['thumbnail_height'] = $image['height'];
			} else {
				// set thumbnail width and height from url, if not available
				if ( empty( $image['thumbnail_width'] ) || empty( $image['thumbnail_height'] ) )
					@list( $image['thumbnail_width'], $image['thumbnail_height'] ) = rl_get_image_size_by_url( $image['thumbnail_url'] );
			}

			$imagedata = array(
				'id'				=> ! empty( $image['id'] ) ? (int) $image['id'] : 0,
				'link'				=> ! empty( $image['link'] ) ? esc_url( $image['link'] ) : '',
				'thumbnail_link'	=> ! empty( $image['thumbnail_link'] ) ? esc_url( $image['thumbnail_link'] ) : '',
				'title'				=> ! empty( $image['title'] ) ? esc_html( $image['title'] ) : '',
				'caption'			=> ! empty( $image['caption'] ) ? esc_html( $image['caption'] ) : '',
				'alt'				=> ! empty( $image['alt'] ) ? esc_html( $image['alt'] ) : '',
				'url'				=> ! empty( $image['url'] ) ? esc_url( $image['url'] ) : '',
				'width'				=> ! empty( $image['width'] ) ? (int) $image['width'] : 0,
				'height'			=> ! empty( $image['height'] ) ? (int) $image['height'] : 0,
				'thumbnail_url'		=> ! empty( $image['thumbnail_url'] ) ? esc_url( $image['thumbnail_url'] ) : '',
				'thumbnail_width'	=> ! empty( $image['thumbnail_width'] ) ? (int) $image['thumbnail_width'] : 0,
				'thumbnail_height'	=> ! empty( $image['thumbnail_height'] ) ? (int) $image['thumbnail_height'] : 0,
			);
		}

		// link does not exist?
		if ( empty( $imagedata['link'] ) )
			$imagedata['link'] = $this->get_gallery_image_link( $imagedata, $image_size );

		// thumbnail link does not exist?
		if ( empty( $imagedata['thumbnail_link'] ) ) {
			if ( $diff_sizes )
				$imagedata['thumbnail_link'] = $this->get_gallery_image_link( $imagedata, $thumbnail_size );
			else
				$imagedata['thumbnail_link'] = $imagedata['link'];
		}

		return apply_filters( 'rl_get_gallery_image_src', $imagedata, $image, $image_size, $thumbnail_size );
	}

	/**
	 * Get gallery featured image.
	 *
	 * @param int $gallery_id Gallery ID
	 * @param array $size Image size
	 * @param array $attr Image attributes
	 * @return string
	 */
	public function get_featured_image( $gallery_id, $size = 'thumbnail', $attr = array() ) {
		$image = $this->get_featured_image_src( $gallery_id );
		$html = '';

		if ( $image )
			$html = $this->get_gallery_image_link( $this->get_gallery_image_src( $image, 'large', $size ), $size, $attr );

		return apply_filters( 'rl_get_featured_image', $html, $gallery_id, $size );
	}

	/**
	 * Get gallery featured image data.
	 *
	 * @param int $gallery_id Gallery ID
	 * @return array
	 */
	public function get_featured_image_src( $gallery_id ) {
		// get featured image data
		$featured_image_type = get_post_meta( $gallery_id, '_rl_featured_image_type', true );
		$featured_image = get_post_meta( $gallery_id, '_rl_featured_image', true );

		switch ( $featured_image_type ) {
			case 'url':
				$image = esc_url( $featured_image );
				break;

			case 'id':
				$featured_image = (int) $featured_image;
				$image = wp_attachment_is_image( $featured_image ) ? $featured_image : $this->maybe_generate_thumbnail();
				break;

			case 'image':
			default:
				// get gallery images
				$images = $this->get_gallery_images( $gallery_id, array( 'exclude' => true ) );

				// set image data
				if ( $images )
					$image = reset( $images );
				else
					$image = 0;
		}

		// return only the first image
		return apply_filters( 'rl_get_featured_image_src', $image, $gallery_id, $featured_image_type, $featured_image );
	}

	/**
	 * Get featured gallery attachments.
	 *
	 * @param array $args Query arguments
	 * @return array Attachment IDs
	 */
	public function gallery_query( $args ) {
		$attachments = array();

		// get fields
		$fields = $this->fields['images']['featured'];

		// force these settings
		$args['fields'] = 'ids';
		$args['tax_query'] = array();
		$args['meta_query'] = array();
		$args['author__in'] = array();
		$args['post_parent__in'] = array();

		// get image source
		$args['image_source'] = isset( $args['image_source'] ) && array_key_exists( $args['image_source'], $fields['image_source']['options'] ) ? $args['image_source'] : $fields['image_source']['default'];
		// get images per post
		$args['images_per_post'] = isset( $args['images_per_post'] ) ? absint( $args['images_per_post'] ) : $fields['images_per_post']['default'];

		$args['number_of_posts'] = isset( $args['number_of_posts'] ) ? (int) $args['number_of_posts'] : $fields['number_of_posts']['default'];

		// get all posts?
		if ( $args['number_of_posts'] <= 0 )
			$args['number_of_posts'] = -1;

		// convert to query arg
		$args['posts_per_page'] = $args['number_of_posts'];

		$args['order'] = isset( $args['order'] ) && array_key_exists( $args['order'], $fields['order']['options'] ) ? $args['order'] : $fields['order']['default'];
		$args['orderby'] = isset( $args['orderby'] ) && array_key_exists( $args['orderby'], $fields['orderby']['options'] ) ? $args['orderby'] : $fields['orderby']['default'];
		$args['offset'] = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$tax_queries = array(
			'post_format'	=> array(),
			'post_term'		=> array()
		);

		$meta_queries = array(
			'page_template'	=> array(),
			'image_source'	=> array()
		);

		// post type
		if ( ! empty( $args['post_type'] ) ) {
			// assign post types
			$post_types = $args['post_type'];

			// clear post types
			$args['post_type'] = array();

			foreach ( $post_types as $post_type ) {
				if ( array_key_exists( $post_type, $fields['post_type']['options'] ) )
					$args['post_type'][] = $post_type;
			}
		} else
			$args['post_type'] = 'any';

		// post status
		if ( ! empty( $args['post_status'] ) ) {
			// assign post statuses
			$post_statuses = $args['post_status'];

			// clear post statuses
			$args['post_status'] = array();

			foreach ( $post_statuses as $post_status ) {
				if ( array_key_exists( $post_status, $fields['post_status']['options'] ) )
					$args['post_status'][] = $post_status;
			}
		}

		// post format
		if ( ! empty( $args['post_format'] ) ) {
			// assign post formats
			$post_formats = $args['post_format'];

			foreach ( $post_formats as $post_format ) {
				if ( array_key_exists( $post_format, $fields['post_format']['options'] ) ) {
					// standard format?
					if ( $post_format === 'standard' ) {
						$tax_queries['post_format'][] = array(
							'relation' => 'OR',
							array(
								'taxonomy' => 'post_format',
								'field' => 'slug',
								'terms' => array( 'post-format-standard' )
							),
							array(
								'taxonomy' => 'post_format',
								'field' => 'slug',
								'operator' => 'NOT EXISTS'
							)
						);
					} else {
						$tax_queries['post_format'][] = array(
							'taxonomy' => 'post_format',
							'field' => 'slug',
							'terms' => array( 'post-format-' . $post_format )
						);
					}
				}
			}
			
			unset( $args['post_format'] );
		}

		// page template
		if ( ! empty( $args['page_template'] ) ) {
			foreach ( $args['page_template'] as $page_template ) {
				if ( array_key_exists( $page_template, $fields['page_template']['options'] ) ) {
					if ( $page_template === 'default' ) {
						$meta_queries['page_template'][] = array(
							'relation' => 'OR',
							array(
								'key' => '_wp_page_template',
								'value' => 'default'
							),
							array(
								'key' => '_wp_page_template',
								'value' => ''
							),
							array(
								'key' => '_wp_page_template',
								'compare' => 'NOT EXISTS'
							)
						);
					} else {
						$meta_queries['page_template'][] = array(
							'key' => '_wp_page_template', 
							'value' => $page_template
						);
					}
				}
			}
		}

		// post author
		if ( ! empty( $args['post_author'] ) ) {
			foreach ( $args['post_author'] as $post_author ) {
				if ( array_key_exists( $post_author, $fields['post_author']['options'] ) )
					$args['author__in'][] = $post_author;
			}
		}

		// page parent
		if ( ! empty( $args['page_parent'] ) ) {
			foreach ( $args['page_parent'] as $page_parent ) {
				if ( array_key_exists( $page_parent, $fields['page_parent']['options'] ) )
					$args['post_parent__in'][] = $page_parent;
			}
		}

		// post term
		if ( ! empty( $args['post_term'] ) ) {
			foreach ( $args['post_term'] as $post_term ) {
				if ( array_key_exists( $post_term, $fields['post_term']['options'] ) ) {
					$term = get_term( $post_term );

					$tax_queries['post_term'][] = array(
						'taxonomy' => $term->taxonomy,
						'field' => 'term_id',
						'terms' => (int) $post_term
					);
				}
			}
		}

		switch ( $args['image_source'] ) {
			case 'thumbnails':
				$meta_queries['image_source'][] = array(
					'relation' => 'OR',
					array(
						'key' => '_thumbnail_id',
						'compare' => 'EXISTS'
					)
				);
		}

		// any tax queries?
		if ( ! empty( $tax_queries['post_term'] ) || ! empty( $tax_queries['post_format'] ) ) {
			$args['tax_query'] = array( 'relation' => 'AND' );

			if ( ! empty( $tax_queries['post_term'] ) )
				$args['tax_query'][] = array( 'relation' => 'OR' ) + $tax_queries['post_term'];

			if ( ! empty( $tax_queries['post_format'] ) )
				$args['tax_query'][] = array( 'relation' => 'OR' ) + $tax_queries['post_format'];
		}

		// any tax queries?
		if ( ! empty( $meta_queries['page_template'] ) || ! empty( $meta_queries['image_source'] ) ) {
			$args['meta_query'] = array( 'relation' => 'AND' );

			if ( ! empty( $meta_queries['page_template'] ) )
				$args['meta_query'][] = array( 'relation' => 'OR', $meta_queries['page_template'] );

			if ( ! empty( $meta_queries['image_source'] ) )
				$args['meta_query'][] = array( 'relation' => 'OR', $meta_queries['image_source'] );
		}

		// get posts
		$query = new WP_Query( apply_filters( 'rl_gallery_query_args', $args ) );

		// get attachments
		if ( $query->have_posts() )
			$attachments = $this->get_gallery_query_attachments( $query->posts, $args );

		return $attachments;
	}

	/**
	 * Get query attachments.
	 *
	 * @param array $posts Post ids, array or objects
	 * @param array $args Additional arguments
	 * @return array
	 */
	public function get_gallery_query_attachments( $posts, $args ) {
		$attachments = array();

		// any posts?
		if ( ! empty( $posts ) ) {
			switch ( $args['image_source'] ) {
				case 'thumbnails':
					$nop = count( $posts ) - 1;

					foreach ( $posts as $number => $post_id ) {
						$attachment_id = (int) get_post_thumbnail_id( $post_id );
						
						if ( ! empty( $attachment_id ) )
							$attachments[] = $attachment_id;

						if ( $args['preview'] ) {
							$attachments = array_unique( $attachments );
							$noa = count( $attachments );

							if ( ( $noa >= ( $args['preview_per_page'] * $args['preview_page'] ) ) || $nop === $number ) {
								$attachments = array_slice( $attachments, ( $args['preview_page'] - 1 ) * $args['preview_per_page'], $args['preview_per_page'], false );

								break;
							}
						}
					}
					break;

				case 'attached_images':
					$nop = count( $posts ) - 1;

					foreach ( $posts as $number => $post_id ) {
						// get attached images, do not use get_attached_media here!
						$attachment_ids = (array) get_children(
							array(
								'post_parent' => $post_id,
								'post_status' => 'inherit',
								'post_type' => 'attachment',
								'post_mime_type' => 'image',
								'posts_per_page' => $args['images_per_post'],
								'order' => 'ASC',
								'orderby' => 'menu_order',
								'nopaging' => false,
								'page' => 1,
								'fields' => 'ids'
							)
						);

						if ( $attachment_ids ) {
							foreach ( $attachment_ids as $attachment_id ) {
								if ( ! empty( $attachment_id ) ) {
									$attachments[] = $attachment_id;
								}
							}
						}

						if ( $args['preview'] ) {
							$attachments = array_unique( $attachments );
							$noa = count( $attachments );

							if ( ( $noa >= ( $args['preview_per_page'] * $args['preview_page'] ) ) || $nop === $number ) {
								$attachments = array_slice( $attachments, ( $args['preview_page'] - 1 ) * $args['preview_per_page'], $args['preview_per_page'], false );

								break;
							}
						}
					}
			}
		}

		return apply_filters( 'rl_get_gallery_query_attachments', array_unique( $attachments ), $posts, $args );
	}

	/**
	 * Load featured content query args.
	 *
	 * @return void
	 */
	public function init_admin() {
		global $pagenow;

		// prepare query arguments if needed
		if ( ( $pagenow === 'post.php' && ( ( isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'rl_gallery' ) || ( isset( $_POST['post_ID'] ) && get_post_type( $_POST['post_ID'] ) === 'rl_gallery' ) ) ) || ( in_array( $pagenow, array( 'edit.php', 'post-new.php'), true ) && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'rl_gallery' ) || ( $pagenow === 'admin-ajax.php' && isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'rl-get-preview-content', 'rl-post-gallery-preview', 'rl-get-menu-content' ), true ) ) ) {
			foreach ( array( 'post_type', 'post_status', 'post_format', 'post_term', 'post_author', 'page_parent', 'page_template' ) as $option ) {
				$this->fields['images']['featured'][$option]['options'] = $this->prepare_query_args( $option );
			}
		}

		// add default thumbnail image if needed
		if ( Responsive_Lightbox()->options['builder']['gallery_builder'] && $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'rl_gallery' ) 
			$this->maybe_generate_thumbnail();
	}

	/**
	 * Generate post thumbnail replacement.
	 *
	 * @return int Thumbnail ID
	 */
	public function maybe_generate_thumbnail() {
		// get attachment
		$thumbnail_id = get_posts(
			array(
				'name'			 => 'responsive-lightbox-thumbnail',
				'post_type'		 => 'attachment',
				'post_status'	 => 'inherit',
				'numberposts'	 => 1,
				'fields'		 => 'ids'
			)
		);

		// no attachment?
		if ( empty( $thumbnail_id ) ) {
			// get upload directory data
			$wp_upload_dir = wp_upload_dir();

			// get file path
			$filepath = str_replace( '\\', '/', RESPONSIVE_LIGHTBOX_PATH . 'images/responsive-lightbox-thumbnail.png' );

			// get file name
			$filename = basename( $filepath );

			// new filepath in upload dir
			$new_filepath = $wp_upload_dir['path'] . '/' . $filename;

			// copty file to upload dir
			copy( $filepath, $new_filepath );

			// get type of file
			$filetype = wp_check_filetype( $filename );

			// insert attachment
			$thumbnail_id = wp_insert_attachment(
				array(
					'guid'				=> $wp_upload_dir['url'] . '/' . $filename, 
					'post_mime_type'	=> $filetype['type'],
					'post_title'		=> preg_replace( '/\.[^.]+$/', '', $filename ),
					'post_content'		=> '',
					'post_parent'		=> 0,
					'post_status'		=> 'inherit'
				),
				$new_filepath,
				0
			);

			// success?
			if ( $thumbnail_id ) {
				// make sure that this file is included
				require_once( ABSPATH . 'wp-admin/includes/image.php' );

				// update database with generated metadata for the attachment
				wp_update_attachment_metadata( $thumbnail_id, wp_generate_attachment_metadata( $thumbnail_id, $new_filepath ) );
			}
		} else
			$thumbnail_id = $thumbnail_id[0];

		return (int) $thumbnail_id;
	}

	/**
	 * Prepare values option list.
	 *
	 * @param string $type Value type
	 * @return string Generated HTML
	 */
	public function prepare_query_args( $type = '' ) {
		$html = '';

		switch( $type ) {
			case 'post_type':
				$data = $this->get_post_types();
				break;

			case 'post_status':
				$data = $this->get_post_statuses();
				break;

			case 'post_format':
				$data = $this->get_post_formats();
				break;

			case 'post_term':
				$taxonomies = $this->get_taxonomies();
				$new_terms = array();

				if ( ! empty( $taxonomies ) ) {
					foreach ( $taxonomies as $tax_id => $label ) {
						$terms = get_terms(
							array(
								'taxonomy'		=> $tax_id,
								'orderby'		=> 'name',
								'order'			=> 'ASC',
								'hide_empty'	=> false,
								'fields'		=> 'id=>name'
							)
						);

						if ( ! empty( $terms ) )
							$new_terms[$tax_id] = array(
								'label'	=> $label,
								'terms'	=> $terms
							);
					}
				}

				$data = $new_terms;
				break;

			case 'post_author':
				$data = $this->get_users();
				break;

			case 'page_parent':
				$parents = array();
				$hierarchical = get_post_types(
					array(
						'public'		=> true,
						'hierarchical'	=> true
					),
					'objects',
					'and'
				);

				if ( ! empty( $hierarchical ) ) {
					foreach ( $hierarchical as $post_type => $object ) {
						// get top level hierarchical posts
						$query = new WP_Query(
							array(
								'post_type'			=> $post_type,
								'post_status'		=> 'publish',
								'nopaging'			=> true,
								'posts_per_page'	=> -1,
								'orderby'			=> 'title',
								'order'				=> 'ASC',
								'suppress_filters'	=> false,
								'no_found_rows'		=> true,
								'cache_results'		=> false,
								'post_parent'		=> 0
							)
						);

						if ( ! empty( $query->posts ) ) {
							foreach ( $query->posts as $post ) {
								$parents[$post->ID] = trim( $post->post_title ) === '' ? __( 'Untitled' ) : $post->post_title;
							}
						}
					}
				}

				$data = $parents;
				break;

			case 'page_template':
				$data = $this->get_page_templates();
				break;

			default:
				$data = array();
		}

		return apply_filters( 'rl_galleries_prepare_query_args', $data, $type );
	}

	/**
	 * Get public post types.
	 *
	 * @return array Post types
	 */
	public function get_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true
			),
			'objects',
			'and'
		);

		$data = array();

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type => $cpt ) {
				// skip gallery and attachment post types
				if ( $post_type === 'rl_gallery' || $post_type === 'attachment' )
					continue;

				$data[$post_type] = $cpt->labels->singular_name;
			}
		}

		asort( $data );

		return $data;
	}

	/**
	 * Get post statuses.
	 *
	 * @return array Post statuses
	 */
	public function get_post_statuses() {
		$post_statuses = get_post_stati();

		asort( $post_statuses );

		// remove inherit post status
		if ( isset( $post_statuses['inherit'] ) )
			unset( $post_statuses['inherit'] );

		return $post_statuses;
	}

	/**
	 * Get post formats.
	 *
	 * @return array Post formats
	 */
	public function get_post_formats() {
		$post_formats = array(
			'aside'		=> __( 'Aside' ),
			'audio'		=> __( 'Audio' ),
			'chat'		=> __( 'Chat' ),
			'gallery'	=> __( 'Gallery' ),
			'link'		=> __( 'Link' ),
			'photo'		=> __( 'Photo' ),
			'quote'		=> __( 'Quote' ),
			'standard'	=> __( 'Standard' ),
			'status'	=> __( 'Status' ),
			'video'		=> __( 'Video' )
		);

		asort( $post_formats );

		return $post_formats;
	}

	/**
	 * Get taxonomies.
	 *
	 * @return array Taxonomies
	 */
	public function get_taxonomies() {
		$taxonomies = get_taxonomies(
			array(
				'public' => true
			),
			'objects',
			'and'
		);

		// remove post format
		if ( array_key_exists( 'post_format', $taxonomies ) )
			unset( $taxonomies['post_format'] );

		$data = array();

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax_id => $taxonomy ) {
				$data[$tax_id] = $taxonomy->labels->singular_name;
			}
		}

		asort( $data );

		return $data;
	}

	/**
	 * Get users.
	 *
	 * @return array Users
	 */
	public function get_users() {
		$users = get_users(
			array(
				'fields' => array( 'ID', 'user_login' )
			)
		);

		$data = array();

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$data[(int) $user->ID] = $user->user_login;
			}
		}

		asort( $data );

		return $data;
	}

	/**
	 * Get page templates.
	 *
	 * @return array Page templates
	 */
	public function get_page_templates() {
		$data = array();
		$page_templates = wp_get_theme()->get_page_templates();

		if ( ! empty( $page_templates ) ) {
			$page_templates = array_flip( $page_templates );

			asort( $page_templates );
		}

		$data = array_merge( array( 'default' => apply_filters( 'default_page_template_title', __( 'Default Template' ) ) ), $page_templates );

		return $data;
	}

	/**
	 * Save gallery metadata.
	 *
	 * @param int $post_id Post ID
	 * @param object $post Post object
	 * @param bool $update Whether this is an existing post being updated or not
	 * @return void
	 */
	public function save_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! $update || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'untrash' ) || empty( $_POST['rl_gallery'] ) )
			return;

		$this->save_gallery( wp_unslash( $_POST ), $post_id );
	}

	/**
	 * Save gallery preview metadata.
	 *
	 * @param array $post_data Gallery data
	 * @param int $post_id Post ID
	 * @param bool $preview Whether is it preview
	 * @return void
	 */
	public function save_gallery( $post_data, $post_id, $preview = false ) {
		$data = $post_data['rl_gallery'];

		// prepare sanitized data
		$safedata = array();

		// sanitize all fields
		foreach ( $this->fields as $tab_id => $menu_items ) {
			switch ( $tab_id ) {
				case 'config':
					// add menu item
					$menu_item = isset( $data[$tab_id], $data[$tab_id]['menu_item'] ) && array_key_exists( $data[$tab_id]['menu_item'], $this->tabs[$tab_id]['menu_items'] ) ? $data[$tab_id]['menu_item'] : reset( $this->tabs[$tab_id]['menu_items'] );

					// get default gallery fields
					$default_gallery_fields = Responsive_Lightbox()->frontend->get_default_gallery_fields();

					// prepare fields
					if ( $menu_item === 'default' )
						$items = $default_gallery_fields;
					else {
						// assign settings and defaults
						$fields = Responsive_Lightbox()->settings->settings[$menu_item . '_gallery']['fields'];
						$defaults = Responsive_Lightbox()->defaults[$menu_item . '_gallery'];

						// make a copy
						$fields_copy = $fields;

						foreach ( $fields_copy as $field_id => $field ) {
							if ( $field['type'] === 'multiple' ) {
								foreach ( $field['fields'] as $subfield_id => $subfield ) {
									$fields[$field_id]['fields'][$subfield_id]['default'] = $defaults[$subfield_id];
								}
							} else
								$fields[$field_id]['default'] = $defaults[$field_id];
						}

						$items = Responsive_Lightbox()->frontend->get_unique_fields( $default_gallery_fields, $fields );
					}

					// sanitize fields
					$safedata = $this->sanitize_fields( $items, $data, $tab_id, $menu_item );

					// add menu item
					$safedata[$tab_id]['menu_item'] = $menu_item;
					break;

				default:
					// add menu item
					$menu_item = isset( $data[$tab_id], $data[$tab_id]['menu_item'] ) && array_key_exists( $data[$tab_id]['menu_item'], $this->tabs[$tab_id]['menu_items'] ) ? $data[$tab_id]['menu_item'] : 'options';

					// prepare fields
					$items = $menu_items[$menu_item];

					// sanitize fields
					$safedata = $this->sanitize_fields( $items, $data, $tab_id, $menu_item );

					// add menu item
					$safedata[$tab_id]['menu_item'] = $menu_item;
			}

			$safedata[$tab_id] = apply_filters( 'rl_gallery_tab_metadata', $safedata[$tab_id], $tab_id );

			// preview?
			if ( $preview )
				update_metadata( 'post', $post_id, '_rl_' . $tab_id, $safedata[$tab_id] );
			else
				update_post_meta( $post_id, '_rl_' . $tab_id, $safedata[$tab_id] );
		}

		$featured_image_type = ! empty( $post_data['rl_gallery_featured_image'] ) && in_array( $post_data['rl_gallery_featured_image'], array( 'id', 'url', 'image' ), true ) ? $post_data['rl_gallery_featured_image'] : 'id';

		switch ( $featured_image_type ) {
			case 'url':
				$thumbnail_id = $this->maybe_generate_thumbnail();

				$featured_image = isset( $post_data['_rl_thumbnail_url'] ) ? esc_url( $post_data['_rl_thumbnail_url'] ) : '';
				break;

			case 'image':
				$thumbnail_id = $this->maybe_generate_thumbnail();

				$featured_image = '';
				break;

			case 'id':
			default:
				$featured_image = $thumbnail_id = isset( $post_data['_thumbnail_id'] ) ? (int) $post_data['_thumbnail_id'] : 0;
		}

		// preview?
		if ( $preview ) {
			update_metadata( 'post', $post_id, '_rl_featured_image_type', $featured_image_type );
			update_metadata( 'post', $post_id, '_rl_featured_image', $featured_image );
			update_metadata( 'post', $post_id, '_thumbnail_id', $thumbnail_id );
		} else {
			// update featured image
			update_post_meta( $post_id, '_rl_featured_image_type', $featured_image_type );
			update_post_meta( $post_id, '_rl_featured_image', $featured_image );
			update_post_meta( $post_id, '_thumbnail_id', $thumbnail_id );
		}

		// update post excerpt
		if ( isset( $safedata['misc']['options']['gallery_description'] ) ) {
			remove_action( 'save_post_rl_gallery', array( $this, 'save_post' ), 10, 3 );

			$postdata = array(
				'ID'			 => $post_id,
				'post_excerpt'	 => wp_kses_post( $safedata['misc']['options']['gallery_description'] ),
			);

			wp_update_post( $postdata );

			add_action( 'save_post_rl_gallery', array( $this, 'save_post' ), 10, 3 );
		}
	}

	/**
	 * Check attachments IDs.
	 *
	 * @param array $attachments Attachment ID's
	 * @return array Existing image attachment ID's
	 */
	public function check_attachments( $attachments ) {
		if ( empty( $attachments ) || ! is_array( $attachments ) )
			return array();

		$copy = array_map( 'intval', $attachments );

		// check attachments
		foreach ( $attachments as $key => $attachment_id ) {
			// is it an image?
			if ( ! wp_attachment_is_image( $attachment_id ) )
				unset( $copy[$key] );
		}

		return $copy;
	}

	/**
	 * Display shortcode metabox.
	 *
	 * @param object $post Post object
	 * @return void
	 */
	public function shortcode_metabox( $post ) {
		echo '
		<p>' . __( 'You can place this gallery anywhere into your posts, pages, custom post types or widgets by using the shortcode below', 'responsive-lightbox' ) . ':</p>
		<code class="rl-shortcode">[rl_gallery id=&quot;' . $post->ID . '&quot;]</code>
		<p>' . __( 'You can also place this gallery into your template files by using the template tag below', 'responsive-lightbox' ) . ':</p>
		<code class="rl-shortcode">if ( function_exists( \'rl_gallery\' ) ) { rl_gallery( \'' . $post->ID . '\' ); }</code>';
	}

	/**
	 * Add new gallery listing columns.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function gallery_columns( $columns ) {
		// find title position
		$offset = array_search( 'title', array_keys( $columns ) );

		// put image column before title
		$columns = array_merge(
			array_slice( $columns, 0, $offset ),
			array(
				'image' => __( 'Gallery', 'responsive-lightbox' )
			),
			array_slice( $columns, $offset )
		);

		// put new columns after title
		$columns = array_merge(
			array_slice( $columns, 0, $offset + 2 ),
			array(
				'shortcode'	=> __( 'Shortcode', 'responsive-lightbox' ),
				'type'		=> __( 'Type', 'responsive-lightbox' ),
				'source'	=> __( 'Source', 'responsive-lightbox' )
			),
			array_slice( $columns, $offset + 2 )
		);

		return $columns;
	}

	/**
	 * Add new gallery listing columns content.
	 *
	 * @param string $column_name Column name
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function gallery_columns_content( $column_name, $post_id ) {
		global $pagenow;

		if ( $pagenow === 'edit.php' ) {
			switch ( $column_name ) {
				case 'image':
					// get image data, based on gallery source type
					$image = $this->get_featured_image( $post_id, 'thumbnail' );
					$images_count = (int) get_post_meta( $post_id, '_rl_images_count', true );

					// display count
					if ( ! empty( $image ) )
						echo '<span class="media-icon image-icon">' . $image . '</span><span>' . sprintf( _n( '%s image', '%s images', $images_count, 'responsive-lightbox' ), $images_count ) . '</span>';
					else
						echo '<span class="media-icon image-icon">' . wp_get_attachment_image( 0, array( 60, 60 ), true, array( 'alt' => '' ) ) . '</span>';
					break;

				case 'shortcode':
					echo '<code>[rl_gallery id="' . $post_id . '"]</code>';
					break;

				case 'type':
					$config = get_post_meta( $post_id, '_rl_config', true );

					if ( ! empty( $config['menu_item'] ) && array_key_exists( $config['menu_item'], $this->tabs['config']['menu_items'] ) ) {
						echo $this->tabs['config']['menu_items'][$config['menu_item']];

						if ( $config['menu_item'] === 'default' )
							echo ' (' . $this->tabs['config']['menu_items'][Responsive_Lightbox()->options['settings']['builder_gallery']] . ')';
					} else
						echo '-';
					break;

				case 'source':
					$images = get_post_meta( $post_id, '_rl_images', true );

					if ( ! empty( $images['menu_item'] ) && array_key_exists( $images['menu_item'], $this->tabs['images']['menu_items'] ) )
						echo $this->tabs['images']['menu_items'][$images['menu_item']];
					else
						echo '-';
					break;
			}
		}
	}
	
	/**
	 * Get size information for all currently-registered image sizes.
	 *
	 * @global $_wp_additional_image_sizes
	 * @uses get_intermediate_image_sizes()
	 * @return array $sizes Data for all currently-registered image sizes.
	 */
	public function get_image_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = array();

		foreach ( get_intermediate_image_sizes() as $_size ) {
			if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
				$sizes[$_size]['width'] = get_option( "{$_size}_size_w" );
				$sizes[$_size]['height'] = get_option( "{$_size}_size_h" );
				$sizes[$_size]['crop'] = (bool) get_option( "{$_size}_crop" );
			} elseif ( isset( $_wp_additional_image_sizes[$_size] ) ) {
				$sizes[$_size] = array(
					'width'	 => $_wp_additional_image_sizes[$_size]['width'],
					'height' => $_wp_additional_image_sizes[$_size]['height'],
					'crop'	 => $_wp_additional_image_sizes[$_size]['crop'],
				);
			}
		}

		return $sizes;
	}

	/**
	 * Get size information for a specific image size.
	 *
	 * @uses get_image_sizes()
	 * @param string $size The image size for which to retrieve data.
	 * @return bool|array $size Size data about an image size or false if the size doesn't exist.
	 */
	public function get_image_size( $size ) {
		if ( isset( $this->sizes[$size] ) )
			return $this->sizes[$size];
		else
			return false;
	}

	/**
	 * Filter the admin post thumbnail HTML markup
	 * 
	 * @param mixed $content HTML
	 * @param int $post_id Post ID
	 * @return string
	 */
	public function admin_post_thumbnail_html( $content, $post_id ) {
		if ( get_post_type( $post_id ) === 'rl_gallery' ) {
			$value = get_post_meta( $post_id, '_rl_featured_image', true );
			$type = get_post_meta( $post_id, '_rl_featured_image_type', true );
			$type = ! empty( $type ) && in_array( $type, array( 'image', 'id', 'url' ) ) ? $type : 'image';

			if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) && $this->maybe_generate_thumbnail() === (int) get_post_meta( $post_id, '_thumbnail_id', true ) ) {
				remove_filter( 'admin_post_thumbnail_html', array( $this, 'admin_post_thumbnail_html' ), 10 );

				$content = _wp_post_thumbnail_html( 0, $post_id );
			}

			$content = '
				<div class="rl-gallery-featured-image-options">
					<p class="howto">' . __( 'Select gallery featured image source:', 'responsive-lightbox' ) . '</p>
					<label for="rl-gallery-featured-image"><input id="rl-gallery-featured-image" type="radio" name="rl_gallery_featured_image" value="image" ' . checked( $type, 'image', false ) . ' />' . __( 'First gallery image', 'responsive-lightbox' ) . '</label><br />
					<label for="rl-gallery-featured-id"><input id="rl-gallery-featured-id" type="radio" name="rl_gallery_featured_image" value="id" ' . checked( $type, 'id', false ) . ' />' . __( 'Media Library', 'responsive-lightbox' ) . '</label><br />
					<label for="rl-gallery-featured-url"><input id="rl-gallery-featured-url" type="radio" name="rl_gallery_featured_image" value="url" ' . checked( $type, 'url', false ) . ' />' . __( 'Custom URL', 'responsive-lightbox' ) . '</label>
				</div>
				<div class="rl-gallery-featured-image-select">
					<div class="rl-gallery-featured-image-select-id"' . ( $type === 'id' ? '' : ' style="display: none;"') . '>' . $content . '</div>
					<div class="rl-gallery-featured-image-select-url"' . ( $type === 'url' ? '' : ' style="display: none;"') . '>
						<p><input id="_rl_thumbnail_url" class="large-text" name="_rl_thumbnail_url" value="' . ( $type === 'url' ? esc_url( $value ) : '' ) . '" type="text" /></p>
						<p class="howto">' . __( 'Custom featured image URL', 'responsive-lightbox' ) . '</p>
					</div>
					<div class="rl-gallery-featured-image-select-image"' . ( $type === 'image' ? '' : ' style="display: none;"') . '><p class="howto">' . __( 'Dynamically generated first gallery image', 'responsive-lightbox' ) . '</p></div>
				</div>
			';
		}
		
		return $content;
	}

	/**
	 * Modify the resulting HTML so that the feature image is set as a background property.
	 *
	 * @param string $html The HTML image tag.
	 * @param integer $post_id The post whose featured image is to be printed.
	 * @param string $post_thumbnail_id The post thumbnail ID.
	 * @param array|string $size The size of the featured image.
	 * @param array $attr Additional attributes.
	 * @return string The HTML image tag, with a CSS background property set (when required).
	 */
	public function post_thumbnail_html( $html, $post_id = 0, $post_thumbnail_id = '', $size = false, $attr = array() ) {
		if ( get_post_type( $post_id ) === 'rl_gallery' ) {
			// break if featured image type is media library
			$image_type = get_post_meta( $post_id, '_rl_featured_image_type', true );
			
			if ( ! $image_type || $image_type == 'id' )
				return $html;
			
			$image_src = $this->get_gallery_image_src( $this->get_featured_image_src( $post_id ) );
			$image_url = $image_src['url'];

			// add featured image as background in style tag.
			$quote = '"';
			$style = "style=${quote}background:url( $image_url ) no-repeat center center;-webkit-background-size:cover;-moz-background-size:cover;-o-background-size:cover;background-size: cover;${quote}";

			$html = str_replace( 'src=', $style . ' src=', $html );

			// fix the alt tag (if possible).
			$alt = $image_src['alt'];

			if ( isset( $attr['alt'] ) )
				$alt = $attr['alt'];

			if ( $alt ) {
				$html = str_replace( '/(alt=\'[^\']+\'\|alt="[^"]+")/', '', $html );
				$html = str_replace( 'src=', ' alt="' . esc_attr( $alt ) . '" src=', $html );
			}
		}

		return $html;
	}

	/**
	 * Save the revision meta data.
	 *
	 * @param int $revision_id
	 * @return void
	 */
	public function save_revision( $revision_id ) {
		// get revision
		$revision = get_post( $revision_id );

		// get gallery ID
		$post_id  = $revision->post_parent;

		// is it rl gallery?
		if ( get_post_type( $post_id ) !== 'rl_gallery' )
			return;

		$this->revision_id = $revision_id;

		// save revisioned meta data
		$this->save_gallery( wp_unslash( $_POST ), $revision_id, true );
	}

	/**
	 * Update preview link.
	 *
	 * @param string $link Preview link
	 * @return string
	 */
	public function preview_post_link( $link ) {
		// add rl gallery revision ID
		if ( property_exists( $this, 'revision_id' ) && ! is_null( $this->revision_id ) ) {
			$post_id = wp_get_post_parent_id( $this->revision_id );

			// is it valid rl_gallery post?
			if ( $post_id && get_post_type( $post_id ) === 'rl_gallery' )
				return add_query_arg( 'rl_gallery_revision_id', $this->revision_id, $link );
		}

		return $link;
	}

	/**
	 * Delete gallery revision at shutdown.
	 *
	 * @return void
	 */
	public function shutdown_preview() {
		// is it a frontend preview?
		if ( is_preview() && isset( $_GET['rl_gallery_revision_id'] ) ) {
			global $post;

			// cast revision ID
			$revision_id = (int) $_GET['rl_gallery_revision_id'];

			// is it a valid revision?
			if ( get_post_type( $post->ID ) === 'rl_gallery' && wp_is_post_revision( $revision_id ) === (int) $post->ID )
				wp_delete_post_revision( $revision_id );
		}
	}

	/**
	 * Filter gallery meta data needed for frontend gallery preview.
	 *
	 * @param mixed $value Meta value to filter
	 * @param int $object_id Object ID
	 * @param string $meta_key Meta key to filter a value for
	 * @param bool $single Whether to return a single value
	 * @return mixed
	 */
	public function filter_preview_metadata( $value, $object_id, $meta_key, $single ) {
		// ignore other post types
		if ( get_post_type( $object_id ) !== 'rl_gallery' )
			return $value;

		// get current post
		$post = get_post();

		// prepare keys
		$keys = array( '_rl_featured_image_type', '_rl_featured_image', '_rl_images_count', '_thumbnail_id' );

		// add other metakeys
		foreach ( array_keys( $this->tabs ) as $key ) {
			$keys[] = '_rl_' . $key;
		}

		// restrict only to specified data
		if ( empty( $post ) || (int) $post->ID !== (int) $object_id || ! in_array( $meta_key, $keys, true ) || $post->post_type === 'revision' )
			return $value;

		// grab the last autosave
		$preview = wp_get_post_autosave( $post->ID );

		// invalid revision?
		if ( ! is_object( $preview ) )
			return $value;

		// finally replace metadata
		return array( get_post_meta( $preview->ID, $meta_key, $single ) );
	}
}
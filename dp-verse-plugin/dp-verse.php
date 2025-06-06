<?php
/**
 * Plugin Name: Daily Dhammapada
 * Plugin URI:  https://example.com/daily-dhammapada
 * Description: Displays a daily Dhammapada verse with commentary and image generated by Gemini AI.
 * Version:     1.0.0
 * Author:      Cline
 * Author URI:  https://example.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: daily-dhammapada
 * Domain Path: /languages
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Basic security check
if ( ! function_exists( 'add_action' ) ) {
    echo 'Not allowed!';
	exit;
}

// Define plugin constants
define( 'DP_VERSE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DP_VERSE_LOG_DIR', trailingslashit( wp_upload_dir()['basedir'] ) . 'dp-verse-logs' );

/**
 * Logs messages if debugging is enabled.
 *
 * @param string $message The message to log.
 */
function dp_verse_log( $message ) {
	$options = get_option( 'dp_verse_settings' );
	if ( ! empty( $options['debug_logging'] ) ) {
		if ( ! file_exists( DP_VERSE_LOG_DIR ) ) {
			wp_mkdir_p( DP_VERSE_LOG_DIR );
		}
		$log_file = DP_VERSE_LOG_DIR . '/debug.log';
		$timestamp = current_time( 'mysql' );
		$formatted_message = sprintf( "[%s] %s\n", $timestamp, $message );
		error_log( $formatted_message, 3, $log_file );
	}
}

/**
 * Registers the custom post type 'verse'.
 */
function dp_verse_register_post_type() {
	$labels = array(
		'name'                  => _x( 'Verses', 'Post type general name', 'daily-dhammapada' ),
		'singular_name'         => _x( 'Verse', 'Post type singular name', 'daily-dhammapada' ),
		'menu_name'             => _x( 'Verses', 'Admin Menu text', 'daily-dhammapada' ),
		'name_admin_bar'        => _x( 'Verse', 'Add New on Toolbar', 'daily-dhammapada' ),
		'add_new'               => __( 'Add New', 'daily-dhammapada' ),
		'add_new_item'          => __( 'Add New Verse', 'daily-dhammapada' ),
		'new_item'              => __( 'New Verse', 'daily-dhammapada' ),
		'edit_item'             => __( 'Edit Verse', 'daily-dhammapada' ),
		'view_item'             => __( 'View Verse', 'daily-dhammapada' ),
		'all_items'             => __( 'All Verses', 'daily-dhammapada' ),
		'search_items'          => __( 'Search Verses', 'daily-dhammapada' ),
		'parent_item_colon'     => __( 'Parent Verses:', 'daily-dhammapada' ),
		'not_found'             => __( 'No verses found.', 'daily-dhammapada' ),
		'not_found_in_trash'    => __( 'No verses found in Trash.', 'daily-dhammapada' ),
		'featured_image'        => _x( 'Verse Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'daily-dhammapada' ),
		'set_featured_image'    => _x( 'Set verse image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'daily-dhammapada' ),
		'remove_featured_image' => _x( 'Remove verse image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'daily-dhammapada' ),
		'use_featured_image'    => _x( 'Use as verse image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'daily-dhammapada' ),
		'archives'              => _x( 'Verse archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'daily-dhammapada' ),
		'insert_into_item'      => _x( 'Insert into verse', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'daily-dhammapada' ),
		'uploaded_to_this_item' => _x( 'Uploaded to this verse', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'daily-dhammapada' ),
		'filter_items_list'     => _x( 'Filter verses list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'daily-dhammapada' ),
		'items_list_navigation' => _x( 'Verses list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'daily-dhammapada' ),
		'items_list'            => _x( 'Verses list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'daily-dhammapada' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'verses' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields' ),
		'taxonomies'         => array( 'category', 'post_tag' ),
		'show_in_rest'       => true, // Needed for Gutenberg editor and REST API access
	);

	register_post_type( 'verse', $args );
}
add_action( 'init', 'dp_verse_register_post_type' );

/**
 * Adds the options page to the WordPress admin menu.
 */
function dp_verse_add_options_page() {
	add_options_page(
		__( 'Daily Dhammapada Settings', 'daily-dhammapada' ),
		__( 'Daily Dhammapada', 'daily-dhammapada' ),
		'manage_options',
		'dp_verse_settings',
		'dp_verse_render_options_page'
	);
}
add_action( 'admin_menu', 'dp_verse_add_options_page' );

/**
 * Renders the options page HTML structure.
 */
function dp_verse_render_options_page() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'dp_verse_settings_group' );
			do_settings_sections( 'dp_verse_settings' );
			submit_button( __( 'Save Settings', 'daily-dhammapada' ) );
			?>
		</form> <?php // End of settings form ?>

		<?php // Separate form for manual actions to avoid submitting with settings ?>
		<hr>
		<h2><?php _e( 'Manual Actions', 'daily-dhammapada' ); ?></h2>
		<p><?php _e( 'Use these buttons for manual control and debugging.', 'daily-dhammapada' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-right: 10px;">
			<input type="hidden" name="action" value="dp_verse_manual_publish_action">
			<?php wp_nonce_field( 'dp_verse_manual_publish_nonce', 'dp_verse_manual_publish_nonce_field' ); ?>
			<?php submit_button( __( 'Publish Verse Now', 'daily-dhammapada' ), 'secondary', 'dp_verse_manual_publish_button', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
			<input type="hidden" name="action" value="dp_verse_view_logs_action">
			<?php wp_nonce_field( 'dp_verse_view_logs_nonce', 'dp_verse_view_logs_nonce_field' ); ?>
			<?php submit_button( __( 'View Logs', 'daily-dhammapada' ), 'secondary', 'dp_verse_view_logs_button', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
			<input type="hidden" name="action" value="dp_verse_clear_logs_action">
			<?php wp_nonce_field( 'dp_verse_clear_logs_nonce', 'dp_verse_clear_logs_nonce_field' ); ?>
			<?php submit_button( __( 'Clear Log File', 'daily-dhammapada' ), 'delete', 'dp_verse_clear_logs_button', false, array( 'onclick' => 'return confirm("' . __( 'Are you sure you want to delete the debug log file?', 'daily-dhammapada' ) . '");' ) ); ?>
		</form>

		<?php // Display log content if requested
			// Display admin notices (like success/error messages from button actions)
			settings_errors('dp_verse_settings'); // Use the setting group name

			if ( isset( $_GET['view_logs'] ) && $_GET['view_logs'] === 'true' && current_user_can( 'manage_options' ) ) {
				echo '<h2 style="margin-top: 20px;">' . __( 'Debug Log (Last 100 lines)', 'daily-dhammapada' ) . '</h2>';
				$log_file = DP_VERSE_LOG_DIR . '/debug.log';
				if ( file_exists( $log_file ) && is_readable( $log_file ) ) {
					// Read last N lines (e.g., 100)
					$lines = file( $log_file );
					$log_content = implode( '', array_slice( $lines, -100 ) ); // Get last 100 lines
					echo '<pre style="white-space: pre-wrap; word-wrap: break-word; background: #f1f1f1; border: 1px solid #ccc; padding: 10px; max-height: 400px; overflow-y: scroll;">';
					echo esc_textarea( $log_content );
					echo '</pre>';
					// Add a clear log button (optional enhancement)
					// echo '<form method="post" action="'.esc_url( admin_url( 'admin-post.php' ) ).'"><input type="hidden" name="action" value="dp_verse_clear_logs_action">';
					// wp_nonce_field( 'dp_verse_clear_logs_nonce', 'dp_verse_clear_logs_nonce_field' );
					// submit_button( __( 'Clear Log File', 'daily-dhammapada' ), 'delete', 'dp_verse_clear_logs_button', false );
					// echo '</form>';

				} else {
					echo '<p>' . __( 'Log file not found or not readable.', 'daily-dhammapada' ) . '</p>';
				}
			}
		?>
	</div>
	<?php
}

/**
 * Registers settings, sections, and fields for the options page.
 */
function dp_verse_register_settings() {
	register_setting( 'dp_verse_settings_group', 'dp_verse_settings', 'dp_verse_sanitize_settings' );

	// API Settings Section
	add_settings_section(
		'dp_verse_api_settings_section',
		__( 'API Settings', 'daily-dhammapada' ),
		'dp_verse_api_settings_section_callback',
		'dp_verse_settings'
	);

	add_settings_field(
		'gemini_api_key',
		__( 'Gemini API Key', 'daily-dhammapada' ),
		'dp_verse_gemini_api_key_render',
		'dp_verse_settings',
		'dp_verse_api_settings_section'
	);

	// Prompt Templates Section
	add_settings_section(
		'dp_verse_prompt_templates_section',
		__( 'Prompt Templates', 'daily-dhammapada' ),
		'dp_verse_prompt_templates_section_callback',
		'dp_verse_settings'
	);

	add_settings_field(
		'text_prompt_template',
		__( 'Text Prompt Template', 'daily-dhammapada' ),
		'dp_verse_text_prompt_template_render',
		'dp_verse_settings',
		'dp_verse_prompt_templates_section'
	);

	add_settings_field(
		'summary_prompt_template',
		__( 'Summary Prompt Template', 'daily-dhammapada' ),
		'dp_verse_summary_prompt_template_render',
		'dp_verse_settings',
		'dp_verse_prompt_templates_section'
	);

	add_settings_field(
		'image_prompt_template',
		__( 'Image Prompt Template', 'daily-dhammapada' ),
		'dp_verse_image_prompt_template_render',
		'dp_verse_settings',
		'dp_verse_prompt_templates_section'
	);

	// HTML Templates Section
	add_settings_section(
		'dp_verse_html_templates_section',
		__( 'HTML Content Templates', 'daily-dhammapada' ),
		'dp_verse_html_templates_section_callback',
		'dp_verse_settings'
	);

	add_settings_field(
		'html_prepend',
		__( 'HTML Prepend', 'daily-dhammapada' ),
		'dp_verse_html_prepend_render',
		'dp_verse_settings',
		'dp_verse_html_templates_section'
	);

	add_settings_field(
		'html_append',
		__( 'HTML Append', 'daily-dhammapada' ),
		'dp_verse_html_append_render',
		'dp_verse_settings',
		'dp_verse_html_templates_section'
	);

	// Debugging Section
	add_settings_section(
		'dp_verse_debugging_section',
		__( 'Debugging', 'daily-dhammapada' ),
		'dp_verse_debugging_section_callback',
		'dp_verse_settings'
	);

	add_settings_field(
		'debug_logging',
		__( 'Enable Debug Logging', 'daily-dhammapada' ),
		'dp_verse_debug_logging_render',
		'dp_verse_settings',
		'dp_verse_debugging_section'
	);

}
add_action( 'admin_init', 'dp_verse_register_settings' );

// --- Section Callback Functions ---

function dp_verse_api_settings_section_callback() {
	echo '<p>' . __( 'Enter your Google Gemini API key.', 'daily-dhammapada' ) . '</p>';
}

function dp_verse_prompt_templates_section_callback() {
	echo '<p>' . __( 'Define the templates used to generate content via the Gemini API.', 'daily-dhammapada' ) . '</p>';
	echo '<p>' . __( 'Available variables for all templates: <code>{number}</code>, <code>{pali_verse}</code>, <code>{english_verse}</code>.', 'daily-dhammapada' ) . '</p>';
	echo '<p>' . __( 'The Summary Prompt Template also gets <code>{response}</code> (the text generated from the Text Prompt).', 'daily-dhammapada' ) . '</p>';
	echo '<p>' . __( 'The Image Prompt Template gets <code>{response}</code> and <code>{summary}</code> (the text generated from the Summary Prompt).', 'daily-dhammapada' ) . '</p>';
}

function dp_verse_html_templates_section_callback() {
	echo '<p>' . __( 'Define HTML to prepend and append to the Gemini-generated text response.', 'daily-dhammapada' ) . '</p>';
	echo '<p>' . __( 'Available variables: <code>{number}</code>, <code>{pali_verse}</code>, <code>{english_verse}</code>.', 'daily-dhammapada' ) . '</p>';
}

function dp_verse_debugging_section_callback() {
	echo '<p>' . __( 'Settings for debugging the plugin.', 'daily-dhammapada' ) . '</p>';
}

// --- Field Rendering Functions ---

function dp_verse_gemini_api_key_render() {
	$options = get_option( 'dp_verse_settings' );
	?>
	<input type='password' name='dp_verse_settings[gemini_api_key]' value='<?php echo isset( $options['gemini_api_key'] ) ? esc_attr( $options['gemini_api_key'] ) : ''; ?>' class='regular-text'>
	<?php
}

function dp_verse_text_prompt_template_render() {
	$options = get_option( 'dp_verse_settings' );
	?>
	<textarea name='dp_verse_settings[text_prompt_template]' rows='5' cols='50' class='large-text code'><?php echo isset( $options['text_prompt_template'] ) ? esc_textarea( $options['text_prompt_template'] ) : ''; ?></textarea>
	<?php
}

function dp_verse_summary_prompt_template_render() {
	$options = get_option( 'dp_verse_settings' );
	?>
	<textarea name='dp_verse_settings[summary_prompt_template]' rows='5' cols='50' class='large-text code'><?php echo isset( $options['summary_prompt_template'] ) ? esc_textarea( $options['summary_prompt_template'] ) : ''; ?></textarea>
	<?php
}

function dp_verse_image_prompt_template_render() {
	$options = get_option( 'dp_verse_settings' );
	?>
	<textarea name='dp_verse_settings[image_prompt_template]' rows='5' cols='50' class='large-text code'><?php echo isset( $options['image_prompt_template'] ) ? esc_textarea( $options['image_prompt_template'] ) : ''; ?></textarea>
	<?php
}

function dp_verse_html_prepend_render() {
	$options = get_option( 'dp_verse_settings' );
	?>
	<textarea name='dp_verse_settings[html_prepend]' rows='5' cols='50' class='large-text code'><?php echo isset( $options['html_prepend'] ) ? esc_textarea( $options['html_prepend'] ) : ''; ?></textarea>
	<?php
}

function dp_verse_html_append_render() {
	$options = get_option( 'dp_verse_settings' );
	?>
	<textarea name='dp_verse_settings[html_append]' rows='5' cols='50' class='large-text code'><?php echo isset( $options['html_append'] ) ? esc_textarea( $options['html_append'] ) : ''; ?></textarea>
	<?php
}

function dp_verse_debug_logging_render() {
	$options = get_option( 'dp_verse_settings' );
	?>
	<input type='checkbox' name='dp_verse_settings[debug_logging]' <?php checked( isset( $options['debug_logging'] ) ? $options['debug_logging'] : 0, 1 ); ?> value='1'>
	<label for='dp_verse_settings[debug_logging]'><?php _e( 'Enable logging to', 'daily-dhammapada' ); ?> <code><?php echo esc_html( DP_VERSE_LOG_DIR . '/debug.log' ); ?></code></label>
	<?php
}

/**
 * Sanitizes the settings array before saving.
 *
 * @param array $input The input array from the settings form.
 * @return array The sanitized array.
 */
function dp_verse_sanitize_settings( $input ) {
	$sanitized_input = array();
	$options = get_option( 'dp_verse_settings' ); // Get existing options to merge

	if ( isset( $input['gemini_api_key'] ) ) {
		$sanitized_input['gemini_api_key'] = sanitize_text_field( $input['gemini_api_key'] );
	} elseif ( isset( $options['gemini_api_key'] ) ) {
        // Keep existing value if not submitted (e.g., password field might be empty on submit if unchanged)
        $sanitized_input['gemini_api_key'] = $options['gemini_api_key'];
    }

	if ( isset( $input['text_prompt_template'] ) ) {
		// Allow some basic HTML and template tags
		$sanitized_input['text_prompt_template'] = wp_kses_post( $input['text_prompt_template'] ); // Allows basic HTML, good for templates
	}

	if ( isset( $input['summary_prompt_template'] ) ) {
		$sanitized_input['summary_prompt_template'] = wp_kses_post( $input['summary_prompt_template'] );
	}

	if ( isset( $input['image_prompt_template'] ) ) {
		$sanitized_input['image_prompt_template'] = wp_kses_post( $input['image_prompt_template'] );
	}

	if ( isset( $input['html_prepend'] ) ) {
		$sanitized_input['html_prepend'] = wp_kses_post( $input['html_prepend'] );
	}

	if ( isset( $input['html_append'] ) ) {
		$sanitized_input['html_append'] = wp_kses_post( $input['html_append'] );
	}

	// Checkbox: Ensure it's either 1 or 0
	$sanitized_input['debug_logging'] = isset( $input['debug_logging'] ) ? 1 : 0;


	return $sanitized_input; // Return sanitized array
}

/**
 * Core function to fetch the next verse, generate content/image, and publish the post.
 *
 * @return bool True on success, false on failure.
 */
function dp_verse_publish_next() {
	dp_verse_log( 'Starting dp_verse_publish_next function.' );

	$options = get_option( 'dp_verse_settings' );
	$api_key = isset( $options['gemini_api_key'] ) ? $options['gemini_api_key'] : '';
	// Add other options retrieval here as needed...

	if ( empty( $api_key ) ) {
		dp_verse_log( 'Error: Gemini API Key is not set.' );
		return false;
	}

	// --- 1. Determine the next verse number ---
	$last_verse_number = 0;
	$args = array(
		'post_type'      => 'verse',
		'posts_per_page' => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'fields'         => 'ids', // Only get post IDs
	);
	$latest_verses = get_posts( $args );

	if ( ! empty( $latest_verses ) ) {
		$latest_post_id = $latest_verses[0];
		$tags = wp_get_post_tags( $latest_post_id, array( 'fields' => 'names' ) );
		// Find the numeric tag which should be the verse number
		foreach ( $tags as $tag ) {
			if ( is_numeric( $tag ) ) {
				$last_verse_number = intval( $tag );
				break;
			}
		}
		dp_verse_log( "Last published verse number found: {$last_verse_number}" );
	} else {
		dp_verse_log( 'No published verses found. Starting from verse 1.' );
	}

	$next_verse_number = $last_verse_number >= 423 ? 1 : $last_verse_number + 1;
	dp_verse_log( "Next verse number determined: {$next_verse_number}" );

	// --- 2. Load JSON data ---
	$pali_file_path = DP_VERSE_PLUGIN_DIR . 'pali_dp.json';
	$translation_file_path = DP_VERSE_PLUGIN_DIR . 'translation_buddharakkhita.json';

	if ( ! file_exists( $pali_file_path ) || ! file_exists( $translation_file_path ) ) {
		dp_verse_log( 'Error: JSON data files not found.' );
		return false;
	}

	$pali_json_content = file_get_contents( $pali_file_path );
	$translation_json_content = file_get_contents( $translation_file_path );

	$pali_data = json_decode( $pali_json_content, true );
	$translation_data = json_decode( $translation_json_content, true );

	if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $pali_data ) || ! is_array( $translation_data ) ) {
		dp_verse_log( 'Error decoding JSON data: ' . json_last_error_msg() );
		return false;
	}
	dp_verse_log( 'JSON data loaded successfully.' );

	// --- 3. Fetch Texts ---
	$verse_key = (string) $next_verse_number; // JSON keys are strings
	if ( ! isset( $pali_data[ $verse_key ] ) || ! isset( $translation_data[ $verse_key ] ) ) {
		dp_verse_log( "Error: Verse data not found for verse number {$next_verse_number}." );
		return false;
	}

	$pali_verse = isset( $pali_data[ $verse_key ]['text'] ) ? $pali_data[ $verse_key ]['text'] : '';
	$english_verse = $translation_data[ $verse_key ]; // Assuming direct key-value for translation

	if ( empty( $pali_verse ) || empty( $english_verse ) ) {
		dp_verse_log( "Error: Empty verse text found for verse number {$next_verse_number}." );
		return false;
	}
	dp_verse_log( "Fetched texts for verse {$next_verse_number}." );

	// --- 4. Prepare API Calls ---
	$text_prompt_template = isset( $options['text_prompt_template'] ) ? $options['text_prompt_template'] : '';
	$image_prompt_template = isset( $options['image_prompt_template'] ) ? $options['image_prompt_template'] : '';
	$html_prepend = isset( $options['html_prepend'] ) ? $options['html_prepend'] : '';
	$html_append = isset( $options['html_append'] ) ? $options['html_append'] : '';

	if ( empty( $text_prompt_template ) || empty( $image_prompt_template ) ) {
		dp_verse_log( 'Error: Text or Image prompt template is empty in settings.' );
		return false;
	}

	$replacements = array(
		'{number}'        => $next_verse_number,
		'{pali_verse}'    => $pali_verse,
		'{english_verse}' => $english_verse,
	);

	$text_prompt = str_replace( array_keys( $replacements ), array_values( $replacements ), $text_prompt_template );
	dp_verse_log( "Prepared Text Prompt: " . substr( $text_prompt, 0, 200 ) . '...' ); // Log truncated prompt

	// --- 5. Gemini Text API Call ---
	$text_api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro-exp-03-25:generateContent?key={$api_key}"; // Using 1.5 Flash as example
	$text_api_body = wp_json_encode( array(
		'contents' => array(
			array(
				'parts' => array(
					array( 'text' => $text_prompt )
				)
			)
		)
	) );

	$text_api_args = array(
		'method'  => 'POST',
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => $text_api_body,
		'timeout' => 60, // Increase timeout for API calls
	);

	dp_verse_log( "Making Text API call to: {$text_api_url}" );
	dp_verse_log( "Text API Request Body: " . $text_api_body ); // Log request body
	$text_response = wp_remote_post( $text_api_url, $text_api_args );

	if ( is_wp_error( $text_response ) ) {
		dp_verse_log( 'Error calling Text API (WP_Error): ' . $text_response->get_error_message() );
		return false;
	}

	$text_response_code = wp_remote_retrieve_response_code( $text_response );
	$text_response_body = wp_remote_retrieve_body( $text_response );
	dp_verse_log( "Text API Raw Response (HTTP {$text_response_code}): " . $text_response_body ); // Log raw response body
	$text_data = json_decode( $text_response_body, true );

	// Check specifically for the expected structure after logging the raw response
	if ( $text_response_code !== 200 || ! isset( $text_data['candidates'][0]['content']['parts'][0]['text'] ) ) {
		dp_verse_log( "Error processing Text API response. Code: {$text_response_code}. See raw response above." );
		// Check for specific error messages from Gemini if available
        if (isset($text_data['error']['message'])) {
            dp_verse_log("Gemini API Error Message: " . $text_data['error']['message']);
        }
		return false;
	}

	$gemini_response_text = $text_data['candidates'][0]['content']['parts'][0]['text'];
	dp_verse_log( "Text API call successful. Response received: " . substr( $gemini_response_text, 0, 200 ) . '...' );

	// --- 6. Prepare and Call Summary API ---
	$summary_prompt_template = isset( $options['summary_prompt_template'] ) ? $options['summary_prompt_template'] : '';
	
	// Skip summary generation if template is empty
	$gemini_summary_text = '';
	if ( ! empty( $summary_prompt_template ) ) {
		// Add the text response to the replacements array for the summary prompt
		$replacements['{response}'] = $gemini_response_text;
		
		$summary_prompt = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$summary_prompt_template
		);
		dp_verse_log( "Prepared Summary Prompt: " . substr( $summary_prompt, 0, 200 ) . '...' );
		
		// Make the Summary API call
		$summary_api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro-exp-03-25:generateContent?key={$api_key}";
		$summary_api_body = wp_json_encode( array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $summary_prompt )
					)
				)
			)
		) );
		
		$summary_api_args = array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => $summary_api_body,
			'timeout' => 60,
		);
		
		dp_verse_log( "Making Summary API call to: {$summary_api_url}" );
		dp_verse_log( "Summary API Request Body: " . $summary_api_body );
		$summary_response = wp_remote_post( $summary_api_url, $summary_api_args );
		
		if ( is_wp_error( $summary_response ) ) {
			dp_verse_log( 'Error calling Summary API (WP_Error): ' . $summary_response->get_error_message() );
			// Continue without summary, will use image prompt without {summary} variable
		} else {
			$summary_response_code = wp_remote_retrieve_response_code( $summary_response );
			$summary_response_body = wp_remote_retrieve_body( $summary_response );
			dp_verse_log( "Summary API Raw Response (HTTP {$summary_response_code}): " . $summary_response_body );
			$summary_data = json_decode( $summary_response_body, true );
			
			if ( $summary_response_code === 200 && isset( $summary_data['candidates'][0]['content']['parts'][0]['text'] ) ) {
				$gemini_summary_text = $summary_data['candidates'][0]['content']['parts'][0]['text'];
				dp_verse_log( "Summary API call successful. Response received: " . substr( $gemini_summary_text, 0, 200 ) . '...' );
			} else {
				dp_verse_log( "Error processing Summary API response. Code: {$summary_response_code}. See raw response above." );
				if ( isset( $summary_data['error']['message'] ) ) {
					dp_verse_log( "Gemini API Error Message: " . $summary_data['error']['message'] );
				}
				// Continue without summary
			}
		}
	} else {
		dp_verse_log( "Summary prompt template is empty. Skipping summary generation." );
	}

	// --- 7. Prepare and Call Image API ---
	// Add the text response and summary to the replacements array for the image prompt
	$replacements['{response}'] = $gemini_response_text;
	$replacements['{summary}'] = $gemini_summary_text;

	$image_prompt = str_replace(
		array_keys( $replacements ),
		array_values( $replacements ),
		$image_prompt_template
	);
	dp_verse_log( "Prepared Image Prompt: " . substr( $image_prompt, 0, 200 ) . '...' );

	// Using the generateContent endpoint for image generation as per the new example.
	// Model name might need adjustment based on availability/updates.
	$image_api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp-image-generation:generateContent?key={$api_key}"; // Updated Endpoint
	$image_api_body = wp_json_encode( array(
		'contents' => array(
			array(
				'parts' => array(
					array( 'text' => $image_prompt ) // Send the prepared image prompt
				)
			)
		),
		'generationConfig' => array(
			'responseModalities' => array('Text', 'Image') // Exact format required for image generation
		)
	) );

	$image_api_args = array(
		'method'  => 'POST',
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => $image_api_body,
		'timeout' => 90, // Image generation might take longer
	);

	dp_verse_log( "Making Image API call to: {$image_api_url}" );
	dp_verse_log( "Image API Request Body: " . $image_api_body ); // Log request body
	$image_response = wp_remote_post( $image_api_url, $image_api_args );
	$attachment_id = null;

	if ( is_wp_error( $image_response ) ) {
		dp_verse_log( 'Error calling Image API (WP_Error): ' . $image_response->get_error_message() );
		// Proceed without image
	} else {
		$image_response_code = wp_remote_retrieve_response_code( $image_response );
		$image_response_body = wp_remote_retrieve_body( $image_response );
		dp_verse_log( "Image API Raw Response (HTTP {$image_response_code}): " . $image_response_body ); // Log raw response body
		$image_data = json_decode( $image_response_body, true );

		// --- 7. Handle Image Response ---
		// The new endpoint returns Base64 data directly in candidates[0].content.parts[0].inlineData.data
		if ( $image_response_code === 200 && ! empty( $image_data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ) ) {
			dp_verse_log( "Image API call successful. Received Base64 image data." );
			$image_base64 = $image_data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
			$image_mime_type = isset( $image_data['candidates'][0]['content']['parts'][0]['inlineData']['mimeType'] ) ? $image_data['candidates'][0]['content']['parts'][0]['inlineData']['mimeType'] : 'image/png'; // Default to png
            $image_extension = strpos($image_mime_type, 'jpeg') !== false ? 'jpg' : 'png'; // Determine extension

            $image_data_decoded = base64_decode( $image_base64 );

			if ( $image_data_decoded === false ) {
				dp_verse_log( "Error decoding Base64 image data." );
			} else {
				// Need to save the decoded data to a temporary file to use media_handle_sideload
				$upload_dir = wp_upload_dir();
                $filename = 'gemini-image-' . $next_verse_number . '-' . time() . '.' . $image_extension; // Unique filename
                $tmp_path = trailingslashit( $upload_dir['path'] ) . $filename; // Save in uploads temporarily

                if ( file_put_contents( $tmp_path, $image_data_decoded ) === false ) {
                    dp_verse_log( "Error saving decoded image data to temporary file: {$tmp_path}" );
                } else {
                    dp_verse_log( "Decoded image saved temporarily to: {$tmp_path}" );
                    // Prepare file array for media_handle_sideload
                    $file_array = array(
                        'name'     => $filename,
                        'tmp_name' => $tmp_path,
                    );

                    require_once( ABSPATH . 'wp-admin/includes/media.php' );
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );

                    $image_desc = "Dhammapada Verse " . $next_verse_number;
                    // media_handle_sideload expects a $_FILES-like array, moves the file
                    $attachment_id = media_handle_sideload( $file_array, 0, $image_desc );

                    // Check if media_handle_sideload failed (it deletes the tmp file on success)
                    if ( is_wp_error( $attachment_id ) ) {
                        dp_verse_log( "Error handling sideloaded Base64 image (WP_Error): " . $attachment_id->get_error_message() );
                        $attachment_id = null;
                        // Clean up temp file if sideload failed and file still exists
                        if ( file_exists( $tmp_path ) ) {
                            unlink( $tmp_path );
                        }
                    } else {
                        dp_verse_log( "Base64 Image successfully handled. Attachment ID: {$attachment_id}" );
                        // Temp file is automatically removed by media_handle_sideload on success
                    }
                }
			}
		} else {
			dp_verse_log( "Error processing Image API response. Code: {$image_response_code}. See raw response above." );
			// Check for specific error messages from Gemini if available
            if (isset($image_data['error']['message'])) {
                dp_verse_log("Gemini API Error Message: " . $image_data['error']['message']);
            }
			// Proceed without image
		}
	}

	// --- 8. Construct Post Content ---
	$final_content = str_replace(
		array_keys( $replacements ),
		array_values( $replacements ),
		$html_prepend
	);
	$final_content .= "\n\n" . $gemini_response_text . "\n\n"; // Add the main response
	$final_content .= str_replace(
		array_keys( $replacements ),
		array_values( $replacements ),
		$html_append
	);

	dp_verse_log( 'Final post content constructed.' );

	// --- 9. Insert Post ---
	$post_data = array(
		'post_title'   => sprintf( __( 'Dhammapada Verse %d', 'daily-dhammapada' ), $next_verse_number ),
		'post_content' => $final_content, // Use the combined content
		'post_status'  => 'publish',
		'post_type'    => 'verse',
		'post_author'  => 1, // Default to admin user 1, consider making this configurable?
		// Add categories or other taxonomies if needed
	);

	$new_post_id = wp_insert_post( $post_data, true ); // Pass true to return WP_Error on failure

	if ( is_wp_error( $new_post_id ) ) {
		dp_verse_log( 'Error inserting post: ' . $new_post_id->get_error_message() );
		return false;
	}

	dp_verse_log( "Successfully inserted post with ID: {$new_post_id}" );

	// --- 10. Set Featured Image ---
	if ( $attachment_id ) {
		if ( set_post_thumbnail( $new_post_id, $attachment_id ) ) {
			dp_verse_log( "Successfully set featured image (Attachment ID: {$attachment_id}) for post ID: {$new_post_id}" );
		} else {
			dp_verse_log( "Error setting featured image (Attachment ID: {$attachment_id}) for post ID: {$new_post_id}" );
			// Continue even if featured image fails? Yes, post is already created.
		}
	} else {
		dp_verse_log( "No valid attachment ID found, skipping featured image for post ID: {$new_post_id}" );
	}

	// --- 11. Add Verse Number Tag ---
	$tag_result = wp_set_post_tags( $new_post_id, (string) $next_verse_number, false ); // Add only this tag, don't append

	if ( is_wp_error( $tag_result ) ) {
		dp_verse_log( "Error setting tag '{$next_verse_number}' for post ID {$new_post_id}: " . $tag_result->get_error_message() );
	} elseif ( empty( $tag_result ) ) {
        dp_verse_log( "Failed to set tag '{$next_verse_number}' for post ID {$new_post_id} (unknown error)." );
    } else {
		dp_verse_log( "Successfully set tag '{$next_verse_number}' for post ID {$new_post_id}" );
	}

	dp_verse_log( "Finished dp_verse_publish_next successfully for verse {$next_verse_number}." );
	return true; // Indicate success
}

// --- Cron Job Setup ---

// Hook for the scheduled event
add_action( 'dp_verse_daily_cron_hook', 'dp_verse_publish_next' );

/**
 * Schedules the daily cron event upon plugin activation.
 */
function dp_verse_activate() {
	dp_verse_log( 'Plugin activated. Scheduling daily cron job.' );
	if ( ! wp_next_scheduled( 'dp_verse_daily_cron_hook' ) ) {
		// Schedule to run daily, approximately 24 hours from now
		wp_schedule_event( time() + 5, 'daily', 'dp_verse_daily_cron_hook' ); // Add 5 sec buffer
		dp_verse_log( 'Daily cron job scheduled.' );
	} else {
		dp_verse_log( 'Daily cron job already scheduled.' );
	}
}
register_activation_hook( __FILE__, 'dp_verse_activate' );

/**
 * Clears the scheduled cron event upon plugin deactivation.
 */
function dp_verse_deactivate() {
	dp_verse_log( 'Plugin deactivated. Clearing scheduled cron job.' );
	$timestamp = wp_next_scheduled( 'dp_verse_daily_cron_hook' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'dp_verse_daily_cron_hook' );
		dp_verse_log( 'Daily cron job unscheduled.' );
	} else {
		dp_verse_log( 'Daily cron job was not scheduled.' );
	}
}
register_deactivation_hook( __FILE__, 'dp_verse_deactivate' );


// --- Manual Action Handlers ---

/**
 * Handles the 'Publish Verse Now' button submission.
 */
function dp_verse_handle_manual_publish() {
	// Verify nonce
	if ( ! isset( $_POST['dp_verse_manual_publish_nonce_field'] ) || ! wp_verify_nonce( $_POST['dp_verse_manual_publish_nonce_field'], 'dp_verse_manual_publish_nonce' ) ) {
		wp_die( __( 'Security check failed!', 'daily-dhammapada' ) );
	}

	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to perform this action.', 'daily-dhammapada' ) );
	}

	dp_verse_log( 'Manual publish triggered by user.' );
	$result = dp_verse_publish_next();

	// Add admin notice based on result
	if ( $result ) {
		add_settings_error(
			'dp_verse_settings',
			'dp_verse_manual_publish_success',
			__( 'Successfully published the next verse.', 'daily-dhammapada' ),
			'success' // 'success', 'error', 'warning', 'info'
		);
	} else {
		add_settings_error(
			'dp_verse_settings',
			'dp_verse_manual_publish_error',
			__( 'Failed to publish the next verse. Check debug logs for details.', 'daily-dhammapada' ),
			'error'
		);
	}

	// Store the notices so they survive the redirect
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	// Redirect back to the settings page
	wp_safe_redirect( admin_url( 'options-general.php?page=dp_verse_settings' ) );
	exit;
}
add_action( 'admin_post_dp_verse_manual_publish_action', 'dp_verse_handle_manual_publish' );


/**
 * Handles the 'View Logs' button submission.
 */
function dp_verse_handle_view_logs() {
	// Verify nonce
	if ( ! isset( $_POST['dp_verse_view_logs_nonce_field'] ) || ! wp_verify_nonce( $_POST['dp_verse_view_logs_nonce_field'], 'dp_verse_view_logs_nonce' ) ) {
		wp_die( __( 'Security check failed!', 'daily-dhammapada' ) );
	}

	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to perform this action.', 'daily-dhammapada' ) );
	}

	// Redirect back to settings page with a query arg to trigger log display
	wp_safe_redirect( admin_url( 'options-general.php?page=dp_verse_settings&view_logs=true' ) );
	exit;
}
add_action( 'admin_post_dp_verse_view_logs_action', 'dp_verse_handle_view_logs' );

/**
 * Handles the 'Clear Log File' button submission.
 */
function dp_verse_handle_clear_logs() {
	// Verify nonce
	if ( ! isset( $_POST['dp_verse_clear_logs_nonce_field'] ) || ! wp_verify_nonce( $_POST['dp_verse_clear_logs_nonce_field'], 'dp_verse_clear_logs_nonce' ) ) {
		wp_die( __( 'Security check failed!', 'daily-dhammapada' ) );
	}

	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to perform this action.', 'daily-dhammapada' ) );
	}

	$log_file = DP_VERSE_LOG_DIR . '/debug.log';

	if ( file_exists( $log_file ) ) {
		if ( unlink( $log_file ) ) {
			// Success
			add_settings_error(
				'dp_verse_settings',
				'dp_verse_clear_logs_success',
				__( 'Debug log file cleared successfully.', 'daily-dhammapada' ),
				'success'
			);
			dp_verse_log( 'Debug log file cleared by user.' ); // Log the action itself (will create a new file)
		} else {
			// Failure
			add_settings_error(
				'dp_verse_settings',
				'dp_verse_clear_logs_error',
				__( 'Failed to clear the debug log file. Check file permissions.', 'daily-dhammapada' ),
				'error'
			);
		}
	} else {
		// Log file doesn't exist
		add_settings_error(
			'dp_verse_settings',
			'dp_verse_clear_logs_notice',
			__( 'Debug log file does not exist.', 'daily-dhammapada' ),
			'info'
		);
	}

	// Store the notices so they survive the redirect
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	// Redirect back to the settings page (without the view_logs param)
	wp_safe_redirect( admin_url( 'options-general.php?page=dp_verse_settings' ) );
	exit;
}
add_action( 'admin_post_dp_verse_clear_logs_action', 'dp_verse_handle_clear_logs' );


?>

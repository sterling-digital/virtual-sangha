<?php
/**
 * Plugin Name:       Auto Dhammapada Verse
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Fetches a random Dhammapada verse from a remote JS file and publishes it as a 'verse' custom post type based on a schedule. Avoids frequent repeats.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Cline (AI Assistant)
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       auto-dhammapada-verse
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ADV_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ADV_VERSION', '1.0.0' );
define( 'ADV_DATA_URL', 'https://raw.githubusercontent.com/jackiewoodall/dhammapada/refs/heads/master/dp-data.js' );
define( 'ADV_LOG_OPTION_NAME', 'dhammapada_verse_publish_log' );
define( 'ADV_SCHEDULE_OPTION_NAME', 'adv_schedule_frequency' );
define( 'ADV_API_KEY_OPTION_NAME', 'adv_api_key' ); // New
define( 'ADV_PROMPT_TEMPLATE_OPTION_NAME', 'adv_prompt_template' ); // New
define( 'ADV_HTML_FOOTER_OPTION_NAME', 'adv_html_footer' ); // New
define( 'ADV_CRON_HOOK', 'adv_publish_verse_event' );


/**
 * Fetches the remote JS file and parses the Dhammapada verses.
 *
 * Uses WP HTTP API and regex to extract verses into a PHP array.
 * Caches the result for 12 hours using transients to avoid frequent requests.
 *
 * @return array|WP_Error An array of verses [number => text] or WP_Error on failure.
 */
function adv_get_parsed_verses() {
    $transient_key = 'adv_parsed_verses_cache';
    $cached_verses = get_transient( $transient_key );

    if ( false !== $cached_verses ) {
        return $cached_verses;
    }

    $response = wp_remote_get( ADV_DATA_URL );

    if ( is_wp_error( $response ) ) {
        // Log error or handle it appropriately
        error_log( 'ADV Plugin Error: Failed to fetch remote data. ' . $response->get_error_message() );
        return $response; // Return the WP_Error object
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        $error = new WP_Error( 'adv_empty_response', 'Remote file content is empty.' );
        error_log( 'ADV Plugin Error: ' . $error->get_error_message() );
        return $error;
    }

    // Extract the dammapada_verses object content using regex
    if ( ! preg_match( '/dammapada_verses\s*=\s*\{([\s\S]*?)\};/s', $body, $matches ) ) {
         $error = new WP_Error( 'adv_parsing_error', 'Could not find dammapada_verses object in the remote file.' );
         error_log( 'ADV Plugin Error: ' . $error->get_error_message() );
         return $error;
    }

    $verses_content = $matches[1];
    $verses = [];

    // Regex to match each verse line: number: 'text',
    // Handles potential comments and variations in spacing
    if ( preg_match_all( '/^\s*(\d+)\s*:\s*\'(.*?)\'\s*,?\s*$/m', $verses_content, $verse_matches, PREG_SET_ORDER ) ) {
        foreach ( $verse_matches as $match ) {
            $verse_number = intval( $match[1] );
            $verse_text = $match[2];
            // Basic cleanup (more robust cleaning might be needed depending on actual content)
            $verse_text = str_replace( ["\\'", '\\"'], ["'", '"'], $verse_text ); // Handle escaped quotes if any
            $verses[ $verse_number ] = $verse_text;
        }
    } else {
        $error = new WP_Error( 'adv_parsing_error', 'Could not parse individual verses from the object.' );
        error_log( 'ADV Plugin Error: ' . $error->get_error_message() );
        return $error;
    }

    if ( empty( $verses ) ) {
        $error = new WP_Error( 'adv_no_verses_found', 'No verses were successfully parsed.' );
        error_log( 'ADV Plugin Error: ' . $error->get_error_message() );
        return $error;
    }

    // Cache the result for 12 hours
    set_transient( $transient_key, $verses, 12 * HOUR_IN_SECONDS );

    return $verses;
}


/**
 * Selects a verse to publish based on the publication log.
 *
 * Fetches all verses, reads the log, finds the minimum publication count,
 * identifies candidate verses (those published minimum times), and randomly selects one.
 *
 * @return array|null An array ['number' => verse_number, 'text' => verse_text] or null if no verse can be selected.
 */
function adv_select_verse_to_publish() {
    $verses = adv_get_parsed_verses();
    if ( is_wp_error( $verses ) || empty( $verses ) ) {
        error_log( 'ADV Plugin Error: Cannot select verse, problem fetching/parsing verses.' );
        return null; // Cannot proceed if verses aren't available
    }

    $publish_log = get_option( ADV_LOG_OPTION_NAME, [] ); // Get log or default to empty array
    $all_verse_numbers = array_keys( $verses );

    // Calculate publication counts for all verses, defaulting to 0 if not in log
    $counts = [];
    foreach ( $all_verse_numbers as $num ) {
        $counts[ $num ] = isset( $publish_log[ $num ] ) ? intval( $publish_log[ $num ] ) : 0;
    }

    if ( empty( $counts ) ) {
        error_log( 'ADV Plugin Error: No verse counts could be determined.' );
        return null; // Should not happen if $verses was populated
    }

    // Find the minimum publication count
    $min_count = min( $counts );

    // Find all verses published $min_count times
    $candidate_numbers = [];
    foreach ( $counts as $num => $count ) {
        if ( $count === $min_count ) {
            $candidate_numbers[] = $num;
        }
    }

    if ( empty( $candidate_numbers ) ) {
        error_log( 'ADV Plugin Error: No candidate verses found for publishing.' );
        return null; // Should not happen if $counts was populated
    }

    // Select a random verse number from the candidates
    $selected_verse_number = $candidate_numbers[ array_rand( $candidate_numbers ) ];
    $selected_verse_text = $verses[ $selected_verse_number ];

    return [
        'number' => $selected_verse_number,
        'text'   => $selected_verse_text,
    ];
}


/**
 * Publishes the selected verse as a 'verse' post and updates the log.
 * Optionally uses Gemini API to generate content if configured.
 *
 * Calls adv_select_verse_to_publish(), potentially calls Gemini API,
 * creates the post using wp_insert_post(), and updates the publication count.
 */
function adv_publish_selected_verse() {
    error_log( 'ADV Plugin: Starting adv_publish_selected_verse...' ); // Log: Start function

    $selected_verse = adv_select_verse_to_publish();

    if ( ! $selected_verse ) {
        error_log( 'ADV Plugin Error: Could not publish verse, selection failed.' );
        return; // Stop if no verse was selected
    }

    $verse_number = $selected_verse['number'];
    $verse_text   = $selected_verse['text'];
    error_log( "ADV Plugin: Selected verse #{$verse_number}." ); // Log: Verse selected

    // --- Attempt to use LLM for content ---
    $api_key = get_option( ADV_API_KEY_OPTION_NAME, '' );
    $prompt_template = get_option( ADV_PROMPT_TEMPLATE_OPTION_NAME, '' );
    $final_content = $verse_text; // Default to original verse text

    if ( ! empty( $api_key ) && ! empty( $prompt_template ) ) {
        error_log( 'ADV Plugin: API Key and Prompt Template found. Attempting Gemini API call.' ); // Log: API configured

        // Prepare the prompt
        $final_prompt = str_replace(
            ['{number}', '{verse}'],
            [$verse_number, $verse_text],
            $prompt_template
        );
        error_log( "ADV Plugin: Constructed prompt: {$final_prompt}" ); // Log: Prompt constructed

        // Prepare API request
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $api_key;
        $request_body = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $final_prompt]
                    ]
                ]
            ]
        ]);

        $args = [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $request_body,
            'timeout' => 60, // Increase timeout for potentially long API calls
        ];

        error_log( 'ADV Plugin: Sending request to Gemini API at ' . $api_url ); // Log: Sending request
        $response = wp_remote_post( $api_url, $args );

        // Handle API response
        if ( is_wp_error( $response ) ) {
            error_log( 'ADV Plugin Error: WP_Error during Gemini API call: ' . $response->get_error_message() ); // Log: WP Error
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            error_log( "ADV Plugin: Gemini API response code: {$response_code}" ); // Log: Response code
            // error_log( "ADV Plugin: Gemini API response body: {$response_body}" ); // Log: Full response body (optional, can be verbose)

            if ( $response_code === 200 ) {
                $response_data = json_decode( $response_body );

                // Attempt to extract text based on assumed structure
                $generated_text = null;
                if ( isset( $response_data->candidates[0]->content->parts[0]->text ) ) {
                    $generated_text = $response_data->candidates[0]->content->parts[0]->text;
                }

                if ( ! empty( $generated_text ) ) {
                    $final_content = $generated_text;
                    error_log( 'ADV Plugin: Successfully extracted generated text from Gemini API.' ); // Log: Success
                    // error_log( "ADV Plugin: Generated text: {$final_content}" ); // Log: Generated text (optional)
                } else {
                    error_log( 'ADV Plugin Warning: Gemini API response code 200, but could not extract valid text. Falling back to original verse.' ); // Log: Extraction failure
                    error_log( "ADV Plugin Debug: Response Body: " . $response_body ); // Log: Body on failure
                }
            } else {
                error_log( "ADV Plugin Error: Gemini API returned non-200 status code ({$response_code}). Falling back to original verse." ); // Log: Non-200 code
                error_log( "ADV Plugin Debug: Response Body: " . $response_body ); // Log: Body on failure
            }
        }
    } else {
        error_log( 'ADV Plugin: API Key or Prompt Template not set. Using original verse text.' ); // Log: API not configured
    }
    // --- End LLM content attempt ---

	
    // --- Append HTML Footer ---
    $html_footer = get_option( ADV_HTML_FOOTER_OPTION_NAME, '' );
    if ( ! empty( $html_footer ) ) {
        $final_content .= "\n\n" . $html_footer; // Append with line breaks for separation
        error_log( 'ADV Plugin: Appended HTML footer to content.' ); // Log: Footer appended
    }
    // --- End HTML Footer append ---

    $post_data = [
        'post_title'   => strval( $verse_number ),
        'post_content' => $final_content, // Use the determined content (LLM or original + footer)
        'post_type'    => 'verse',
        'post_status'  => 'publish',
        'post_author'  => 1,
    ];

    error_log( "ADV Plugin: Preparing to insert post for verse #{$verse_number}." ); // Log: Preparing insert
    // Insert the post into the database
    $post_id = wp_insert_post( $post_data, true );

    if ( is_wp_error( $post_id ) ) {
        // Handle error
        error_log( 'ADV Plugin Error: Failed to insert verse post. ' . $post_id->get_error_message() );
    } else {
        // Post was created successfully, update the log
        $publish_log = get_option( ADV_LOG_OPTION_NAME, [] );
        $current_count = isset( $publish_log[ $verse_number ] ) ? intval( $publish_log[ $verse_number ] ) : 0;
        $publish_log[ $verse_number ] = $current_count + 1;

        // Update the option in the database
        update_option( ADV_LOG_OPTION_NAME, $publish_log );
        error_log( "ADV Plugin: Successfully published verse #{$verse_number} (Post ID: {$post_id})." ); // Optional success log
    }
}


// Hook the publishing function to the custom WP-Cron action
add_action( ADV_CRON_HOOK, 'adv_publish_selected_verse' );


/**
 * Adds the options page to the admin menu.
 */
function adv_add_options_page() {
    add_options_page(
        __( 'Auto Dhammapada Verse Settings', 'auto-dhammapada-verse' ), // Page title
        __( 'Auto Dhammapada Verse', 'auto-dhammapada-verse' ),          // Menu title
        'manage_options',                                               // Capability required
        'adv-settings',                                                 // Menu slug
        'adv_render_options_page'                                       // Callback function to render the page
    );
}
add_action( 'admin_menu', 'adv_add_options_page' );

/**
 * Registers the settings, sections, and fields for the options page.
 */
function adv_register_settings() {
    // --- Scheduling Settings ---
    register_setting(
        'adv_options_group',
        ADV_SCHEDULE_OPTION_NAME,
        'adv_sanitize_frequency'
    );

    add_settings_section(
        'adv_scheduling_section', // Changed ID slightly for clarity
        __( 'Scheduling Settings', 'auto-dhammapada-verse' ),
        null,
        'adv-settings'
    );

    add_settings_field(
        'adv_frequency_field',
        __( 'Publish Frequency', 'auto-dhammapada-verse' ),
        'adv_render_frequency_field',
        'adv-settings',
        'adv_scheduling_section' // Use updated section ID
    );

    // --- LLM Integration Settings ---
    register_setting(
        'adv_options_group',
        ADV_API_KEY_OPTION_NAME,
        'adv_sanitize_api_key' // New sanitize callback
    );

    register_setting(
        'adv_options_group',
        ADV_PROMPT_TEMPLATE_OPTION_NAME,
        'adv_sanitize_prompt_template' // New sanitize callback
    );

    register_setting(
        'adv_options_group',
        ADV_HTML_FOOTER_OPTION_NAME,
        'adv_sanitize_html_footer' // New sanitize callback
    );

    add_settings_section(
        'adv_llm_section', // New section ID
        __( 'LLM Content Generation (Optional)', 'auto-dhammapada-verse' ),
        'adv_render_llm_section_description', // Callback for description
        'adv-settings'
    );

    add_settings_field(
        'adv_api_key_field', // New field ID
        __( 'Gemini API Key', 'auto-dhammapada-verse' ),
        'adv_render_api_key_field', // New render callback
        'adv-settings',
        'adv_llm_section' // Use new section ID
    );

    add_settings_field(
        'adv_prompt_template_field', // New field ID
        __( 'Prompt Template', 'auto-dhammapada-verse' ),
        'adv_render_prompt_template_field', // New render callback
        'adv-settings',
        'adv_llm_section' // Use new section ID
    );


    add_settings_field(
        'adv_html_footer_field', // New field ID
        __( 'HTML Footer', 'auto-dhammapada-verse' ),
        'adv_render_html_footer_field', // New render callback
        'adv-settings',
        'adv_llm_section' // Use new section ID
    );

}
add_action( 'admin_init', 'adv_register_settings' );

/**
 * Renders the description for the LLM settings section.
 */
function adv_render_llm_section_description() {
    echo '<p>' . esc_html__( 'Optionally provide a Google Gemini API key and a prompt template to generate post content using the LLM. If left blank, the original verse text will be used.', 'auto-dhammapada-verse' ) . '</p>';
}

/**
 * Sanitizes the API Key.
 *
 * @param string $input Raw input.
 * @return string Sanitized input.
 */
function adv_sanitize_api_key( $input ) {
    // Basic sanitization, consider if more specific validation is needed
    return sanitize_text_field( $input );
}

/**
 * Sanitizes the Prompt Template. Allows basic HTML and placeholders.
 *
 * @param string $input Raw input.
 * @return string Sanitized input.
 */
function adv_sanitize_prompt_template( $input ) {
    // Allow some basic HTML, adjust as needed. Using wp_kses_post for flexibility.
    // Alternatively, use sanitize_textarea_field if no HTML is desired.
    return wp_kses_post( $input ); // Allows basic HTML tags suitable for post content
}

/**
 * Sanitizes the HTML Footer. Allows a limited set of HTML tags.
 *
 * @param string $input Raw input.
 * @return string Sanitized input.
 */
function adv_sanitize_html_footer( $input ) {
    // Allow a limited set of HTML tags and attributes
    $allowed_html = array(
        'a' => array(
            'href' => array(),
            'title' => array(),
            'target' => array(),
            'rel' => array(),
        ),
        'br' => array(),
        'em' => array(),
        'strong' => array(),
        'p' => array(),
        'div' => array(
            'class' => array(),
            'id' => array(),
            'style' => array(),
        ),
        'span' => array(
            'class' => array(),
            'style' => array(),
        ),
    );

	return wp_kses( $input, $allowed_html );
}

/**
 * Sanitizes the selected frequency value before saving.
 * Also reschedules the cron job if the frequency changes.
 *
 * @param string $input The raw input value from the form.
 * @return string The sanitized frequency ('hourly', 'twicedaily', 'daily').
 */
function adv_sanitize_frequency( $input ) {
    $valid_frequencies = ['hourly', 'twicedaily', 'daily'];
    $new_frequency = sanitize_text_field( $input );

    if ( ! in_array( $new_frequency, $valid_frequencies ) ) {
        // If invalid, default to daily and add an admin notice
        add_settings_error(
            ADV_SCHEDULE_OPTION_NAME,
            'invalid_frequency',
            __( 'Invalid frequency selected. Defaulting to daily.', 'auto-dhammapada-verse' ),
            'error'
        );
        $new_frequency = 'daily';
    }

    // Reschedule cron job if frequency changed
    $old_frequency = get_option( ADV_SCHEDULE_OPTION_NAME, 'daily' );
    if ( $old_frequency !== $new_frequency ) {
        // Clear existing schedule
        $timestamp = wp_next_scheduled( ADV_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, ADV_CRON_HOOK );
        }
        // Schedule with new frequency
        wp_schedule_event( time(), $new_frequency, ADV_CRON_HOOK );
        error_log( "ADV Plugin: Rescheduled cron job to '{$new_frequency}'." ); // Optional log
    }

    return $new_frequency;
}


/**
 * Renders the HTML for the API Key field.
 */
function adv_render_api_key_field() {
    $api_key = get_option( ADV_API_KEY_OPTION_NAME, '' );
    ?>
    <input type="password" name="<?php echo esc_attr( ADV_API_KEY_OPTION_NAME ); ?>" id="adv_api_key_field" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
    <p class="description"><?php esc_html_e( 'Enter your Google Gemini API Key.', 'auto-dhammapada-verse' ); ?></p>
    <?php
}


/**
 * Renders the HTML for the HTML Footer field.
 */
function adv_render_html_footer_field() {
    $html_footer = get_option( ADV_HTML_FOOTER_OPTION_NAME, '' );
    ?>
    <textarea name="<?php echo esc_attr( ADV_HTML_FOOTER_OPTION_NAME ); ?>" id="adv_html_footer_field" rows="3" class="large-text"><?php echo esc_textarea( $html_footer ); ?></textarea>
    <p class="description">
        <?php esc_html_e( 'Enter the HTML code to append to the end of each verse post.  Limited HTML tags are allowed.', 'auto-dhammapada-verse' ); ?>
    </p>
	<?php
}

/**
 * Renders the HTML for the Prompt Template field.
 */
function adv_render_prompt_template_field() {
    $prompt_template = get_option( ADV_PROMPT_TEMPLATE_OPTION_NAME, '' );
    ?>
    <textarea name="<?php echo esc_attr( ADV_PROMPT_TEMPLATE_OPTION_NAME ); ?>" id="adv_prompt_template_field" rows="5" class="large-text"><?php echo esc_textarea( $prompt_template ); ?></textarea>
    <p class="description">
        <?php esc_html_e( 'Enter the prompt template. Use {number} for the verse number and {verse} for the verse text.', 'auto-dhammapada-verse' ); ?>
        <br>
        <?php esc_html_e( 'Example: "Explain the meaning of Dhammapada verse {number}: \'{verse}\'"', 'auto-dhammapada-verse' ); ?>
    </p>
    <?php
}

/**
 * Renders the HTML for the frequency dropdown field.
 */
function adv_render_frequency_field() {
    $current_frequency = get_option( ADV_SCHEDULE_OPTION_NAME, 'daily' ); // Default to daily
    $schedules = wp_get_schedules(); // Get available schedules

    // Filter to only include standard WP schedules we want to offer
    $allowed_schedules = [
        'hourly'     => $schedules['hourly']['display'],
        'twicedaily' => $schedules['twicedaily']['display'],
        'daily'      => $schedules['daily']['display'],
    ];

    ?>
    <select name="<?php echo esc_attr( ADV_SCHEDULE_OPTION_NAME ); ?>" id="adv_frequency_field">
        <?php foreach ( $allowed_schedules as $value => $display ) : ?>
            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_frequency, $value ); ?>>
                <?php echo esc_html( $display ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e( 'Select how often a new verse should be published.', 'auto-dhammapada-verse' ); ?></p>
    <?php
}

/**
 * Renders the main options page HTML structure.
 */
function adv_render_options_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php settings_errors(); // Display settings errors/notices ?>

        <h2><?php esc_html_e( 'Plugin Settings', 'auto-dhammapada-verse' ); ?></h2>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'adv_options_group' );
            do_settings_sections( 'adv-settings' );
            submit_button( __( 'Save Settings', 'auto-dhammapada-verse' ) );
            ?>
        </form>

        <hr>

        <h2><?php esc_html_e( 'Manual Trigger', 'auto-dhammapada-verse' ); ?></h2>
        <p><?php esc_html_e( 'Click the button below to manually publish a new verse immediately, regardless of the schedule.', 'auto-dhammapada-verse' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="adv_manual_trigger">
            <?php wp_nonce_field( 'adv_manual_trigger_nonce', 'adv_manual_trigger_nonce_field' ); ?>
            <?php submit_button( __( 'Publish Verse Now', 'auto-dhammapada-verse' ), 'secondary', 'adv_manual_trigger_submit' ); ?>
        </form>

    </div>
    <?php
}

/**
 * Handles the manual trigger button submission.
 */
function adv_handle_manual_trigger() {
    // 1. Verify nonce
    if ( ! isset( $_POST['adv_manual_trigger_nonce_field'] ) || ! wp_verify_nonce( $_POST['adv_manual_trigger_nonce_field'], 'adv_manual_trigger_nonce' ) ) {
        wp_die( __( 'Security check failed.', 'auto-dhammapada-verse' ) );
    }

    // 2. Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to perform this action.', 'auto-dhammapada-verse' ) );
    }

    // 3. Trigger the publishing function
    error_log( 'ADV Plugin: Manual trigger initiated by user.' );
    // Note: adv_publish_selected_verse handles its own detailed logging internally
    adv_publish_selected_verse();

    // 4. Add a success notice (errors are logged internally by the function)
    // We assume success if the function runs without dying, errors are logged.
    // A more robust check might involve modifying adv_publish_selected_verse to return true/false/WP_Error
    add_settings_error(
        'adv-manual-trigger', // Slug for the notice group
        'adv_manual_trigger_success', // Code for the notice
        __( 'Manual verse publishing triggered. Check server error logs for details if issues occurred.', 'auto-dhammapada-verse' ),
        'updated' // 'updated' for success, 'error' for failure
    );

    // 5. Redirect back to the settings page
    // Store the notice transient so it displays after redirect
    set_transient( 'settings_errors', get_settings_errors(), 30 );

    $redirect_url = add_query_arg(
        [
            'page' => 'adv-settings', // The slug of our settings page
            'settings-updated' => 'true' // This helps ensure the notice displays
        ],
        admin_url( 'options-general.php' ) // Base URL for settings pages
    );

    wp_safe_redirect( $redirect_url );
    exit; // Important: stop script execution after redirect
}
// Hook the handler to the admin_post action defined in the form
add_action( 'admin_post_adv_manual_trigger', 'adv_handle_manual_trigger' );


/**
 * Plugin activation hook.
 * Schedules the verse publishing event if it's not already scheduled.
 */
function adv_activate() {
    // Get the saved frequency, default to 'daily' if not set
    $frequency = get_option( ADV_SCHEDULE_OPTION_NAME, 'daily' );

    // Make sure the frequency is valid before scheduling
    $schedules = wp_get_schedules();
    if ( ! isset( $schedules[ $frequency ] ) ) {
        $frequency = 'daily'; // Fallback to daily if saved value is invalid
    }

	if ( ! wp_next_scheduled( ADV_CRON_HOOK ) ) {
		wp_schedule_event( time(), $frequency, ADV_CRON_HOOK );
	}
}

/**
 * Plugin deactivation hook.
 * Clears the scheduled verse publishing event.
 */
function adv_deactivate() {
	$timestamp = wp_next_scheduled( ADV_CRON_HOOK );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, ADV_CRON_HOOK );
	}
    // Optionally clear the transient cache on deactivation
    // delete_transient( 'adv_parsed_verses_cache' );
    // Optionally clear the log on deactivation (maybe not desirable)
    // delete_option( ADV_LOG_OPTION_NAME );
}

// Register activation and deactivation hooks
register_activation_hook( __FILE__, 'adv_activate' );
register_deactivation_hook( __FILE__, 'adv_deactivate' );

?>

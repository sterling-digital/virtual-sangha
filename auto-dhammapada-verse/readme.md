# Auto Dhammapada Verse

A WordPress plugin that automatically fetches a random verse from the Dhammapada and publishes it as a custom post type. It also has the option to use an LLM to generate content for the post.

## Description

This plugin fetches verses from a remote JavaScript file, parses them, and publishes them as posts of the custom post type 'verse'. It is designed to automatically create content for your WordPress site on a scheduled basis. The plugin includes options to control the publishing frequency and to use a Large Language Model (LLM) like Google Gemini to generate the post content.

## Installation

1.  Upload the `auto-dhammapada-verse` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

1.  After activating the plugin, navigate to **Settings > Auto Dhammapada Verse** to configure the plugin.
2.  Configure the following settings:

    *   **Scheduling Settings:**
        *   **Publish Frequency:** Select how often a new verse should be published (Hourly, Twice Daily, Daily).
    *   **LLM Content Generation (Optional):**
        *   **Gemini API Key:** Enter your Google Gemini API key.
        *   **Prompt Template:** Enter the prompt template to use for generating content with the LLM. Use `{number}` for the verse number and `{verse}` for the verse text. Example: "Explain the meaning of Dhammapada verse {number}: '{verse}'"
        *   **HTML Footer:** Enter HTML code to append to the end of each verse post. Limited HTML tags are allowed (a, br, em, strong, p, div, span).
3.  Click the **Save Settings** button to save your changes.
4.  To manually trigger the plugin, click the **Publish Verse Now** button.

## Settings

*   **Publish Frequency:**
    *   Controls how often the plugin automatically publishes a new verse.
    *   Available options: Hourly, Twice Daily, Daily.
*   **Gemini API Key:**
    *   Your API key for accessing the Google Gemini LLM.
    *   Required to use the LLM content generation feature.
*   **Prompt Template:**
    *   A template used to generate a prompt for the LLM.
    *   Use `{number}` as a placeholder for the verse number.
    *   Use `{verse}` as a placeholder for the verse text.
*   **HTML Footer:**
    *   HTML code that will be appended to the end of each published verse post.
    *   Allows a limited set of HTML tags for basic formatting and links.

## Notes

*   The plugin uses WP-Cron for scheduled publishing. Ensure that WP-Cron is properly configured on your server.
*   The plugin caches the fetched verses for 12 hours to reduce the number of requests to the remote server.
*   The plugin logs errors and other information to the server's error log. Check the error log for any issues.

## Contributing

Contributions are welcome! Please submit pull requests with any bug fixes, improvements, or new features.

## License

GPL v2 or later

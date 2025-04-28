<?php
/**
 * Plugin Name: Newsletter Subscription
 * Description: A custom plugin for managing newsletter subscriptions.
 * Version: 1.0
 * Author: Dhanushka Iddamaldeniya
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Create custom DB table on plugin activation
function ns_create_subscription_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'newsletter_subscribers';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        subscribed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'ns_create_subscription_table' );

// Enqueue scripts and styles
function ns_enqueue_scripts() {
    wp_enqueue_script( 'newsletter-js', plugin_dir_url( __FILE__ ) . 'src/js/newsletter.js', array('jquery'), null, true );
    wp_enqueue_style( 'newsletter-css', plugin_dir_url( __FILE__ ) . 'src/css/newsletter.css' );
    wp_localize_script( 'newsletter-js', 'ns_ajax_obj', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'wp_enqueue_scripts', 'ns_enqueue_scripts' );

// AJAX subscription handling
function ns_handle_subscription() {
    if ( ! isset( $_POST['email'] ) || ! is_email( $_POST['email'] ) ) {
        wp_send_json_error( 'Invalid email address' );
    }

    global $wpdb;
    $email = sanitize_email( $_POST['email'] );

    $table_name = $wpdb->prefix . 'newsletter_subscribers';
    $existing_subscriber = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE email = %s", $email ) );

    if ( $existing_subscriber ) {
        wp_send_json_error( 'This email is already subscribed' );
    }

    $wpdb->insert(
        $table_name,
        array( 'email' => $email ),
        array( '%s' )
    );

    // Send admin notification
    $admin_email = get_option( 'admin_email' );
    wp_mail( $admin_email, 'New Subscriber', "New subscriber: $email" );

    wp_send_json_success( 'Subscription successful' );
}
add_action( 'wp_ajax_ns_subscribe', 'ns_handle_subscription' );
add_action( 'wp_ajax_nopriv_ns_subscribe', 'ns_handle_subscription' );

// Admin menu for viewing subscribers
function ns_add_admin_menu() {
    add_menu_page( 'Newsletter Subscribers', 'Newsletter', 'manage_options', 'newsletter-subscribers', 'ns_subscribers_page' );
}
add_action( 'admin_menu', 'ns_add_admin_menu' );

// Display subscribers in admin page
function ns_subscribers_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'newsletter_subscribers';
    $subscribers = $wpdb->get_results( "SELECT * FROM $table_name" );
    ?>
    <div class="wrap">
        <h1>Newsletter Subscribers</h1>
        <a href="<?php echo admin_url('admin.php?page=newsletter-subscribers&export_csv=1'); ?>" class="button button-primary">Export to CSV</a>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Subscribed At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $subscribers as $subscriber ) : ?>
                    <tr>
                        <td><?php echo esc_html( $subscriber->email ); ?></td>
                        <td><?php echo esc_html( $subscriber->subscribed_at ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Export subscribers to CSV
function ns_export_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'newsletter_subscribers';
    $subscribers = $wpdb->get_results( "SELECT * FROM $table_name" );

    if (ob_get_length()) {
        ob_end_clean();
    }

    // Dynamic filename with date
    $filename = 'subscribers-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('Email', 'Subscribed At'));

    foreach ($subscribers as $subscriber) {
        fputcsv($output, array($subscriber->email, $subscriber->subscribed_at));
    }

    fclose($output);

    exit;
}


// Handle CSV export on admin_init
add_action('admin_init', 'ns_handle_export_csv');

function ns_handle_export_csv() {
    if (isset($_GET['page']) && $_GET['page'] === 'newsletter-subscribers' && isset($_GET['export_csv'])) {
        ns_export_csv();
    }
}

// Shortcode to display the newsletter form
function ns_newsletter_form_shortcode() {
    ob_start();
    ?>
    <form id="newsletter-form" class="newsletter-plugin-form">
      <div class="site-footer__email">
        <input type="email" id="newsletter-email" name="email" class="site-footer__email-input newsletter-plugin-form-input" placeholder="rakabir@gmail.com" />
        <button type="submit" class="site-footer__submit-btn"><i class="fa-solid fa-arrow-right"></i></button>
        <!-- <button type="submit">Subscribe</button> -->
      </div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'newsletter_form', 'ns_newsletter_form_shortcode' );
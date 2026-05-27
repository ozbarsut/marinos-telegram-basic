<?php
/**
 * Plugin Name: Marinos Chatbot
 * Plugin URI:  https://marinosajans.com.tr
 * Description: Website ziyaretçisi mesaj yazar → Telegram'a düşer → operatör Telegram'dan Yanıtla ile cevap verir → widget'a yansır.
 * Version:     3.1.0
 * Author:      Marinos Ajans
 * Author URI:  https://marinosajans.com.tr
 * License:     GPL2
 * Text Domain: marinos-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MARINOS_CHATBOT_VERSION', '3.1.0' );
define( 'MARINOS_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'MARINOS_CHATBOT_URL',  plugin_dir_url( __FILE__ ) );

require_once MARINOS_CHATBOT_PATH . 'includes/class-admin.php';
require_once MARINOS_CHATBOT_PATH . 'includes/class-widget.php';
require_once MARINOS_CHATBOT_PATH . 'includes/class-api.php';
require_once MARINOS_CHATBOT_PATH . 'includes/class-webhook.php';
require_once MARINOS_CHATBOT_PATH . 'includes/class-logger.php';
require_once MARINOS_CHATBOT_PATH . 'includes/class-mailer.php';
require_once MARINOS_CHATBOT_PATH . 'includes/class-visitor.php';

function marinos_chatbot_init() {
    new Marinos_Chatbot_Admin();
    new Marinos_Chatbot_Widget();
    new Marinos_Chatbot_Api();
    new Marinos_Chatbot_Visitor();
}
add_action( 'plugins_loaded', 'marinos_chatbot_init' );

// ─── REST API: Telegram Webhook endpoint ───────────────────────────────────
// URL: https://siteniz.com/wp-json/marinos-chatbot/v1/telegram
add_action( 'rest_api_init', function () {
    register_rest_route( 'marinos-chatbot/v1', '/telegram', [
        'methods'             => 'POST',
        'callback'            => [ 'Marinos_Chatbot_Webhook', 'handle' ],
        'permission_callback' => '__return_true',
    ] );
} );

// ─── Zamanlanmış e-posta ────────────────────────────────────────────────────
add_action( 'marinos_send_conversation_email', 'marinos_chatbot_send_scheduled_email', 10, 3 );
function marinos_chatbot_send_scheduled_email( $session_id, $page_url, $ip ) {
    if ( ! class_exists( 'Marinos_Chatbot_Mailer' ) ) {
        require_once MARINOS_CHATBOT_PATH . 'includes/class-mailer.php';
        require_once MARINOS_CHATBOT_PATH . 'includes/class-logger.php';
    }
    ( new Marinos_Chatbot_Mailer() )->notify( $session_id, $page_url, $ip );
}

// ─── Aktivasyon: DB tabloları ───────────────────────────────────────────────
register_activation_hook( __FILE__, 'marinos_chatbot_activate' );
function marinos_chatbot_activate() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}marinos_chatbot_logs (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id   VARCHAR(64)         NOT NULL,
        visitor_ip   VARCHAR(45)         NOT NULL DEFAULT '',
        visitor_page VARCHAR(255)        NOT NULL DEFAULT '',
        role         VARCHAR(16)         NOT NULL,
        message      LONGTEXT            NOT NULL,
        created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY session_id (session_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}marinos_tg_messages (
        id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id     VARCHAR(128)        NOT NULL,
        visitor_msg    TEXT                NOT NULL,
        tg_message_id  BIGINT(20)          NOT NULL DEFAULT 0,
        reply          TEXT                DEFAULT NULL,
        replied_at     DATETIME            DEFAULT NULL,
        created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY    (id),
        KEY idx_session (session_id),
        KEY idx_tg_id   (tg_message_id)
    ) $charset;" );
}

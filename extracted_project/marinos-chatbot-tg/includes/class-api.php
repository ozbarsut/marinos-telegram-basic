<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Marinos_Chatbot_Api {

    public function __construct() {
        add_action( 'wp_ajax_marinos_chat',             [ $this, 'handle_chat' ] );
        add_action( 'wp_ajax_nopriv_marinos_chat',      [ $this, 'handle_chat' ] );
        add_action( 'wp_ajax_marinos_chat_poll',        [ $this, 'handle_poll' ] );
        add_action( 'wp_ajax_nopriv_marinos_chat_poll', [ $this, 'handle_poll' ] );
    }

    // ─── Ziyaretçi mesajını Telegram'a gönder ────────────────────────────────
    public function handle_chat() {
        check_ajax_referer( 'marinos_chatbot_nonce', 'nonce' );

        $user_msg   = isset( $_POST['message'] )    ? sanitize_textarea_field( $_POST['message'] ) : '';
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] )  : '';
        $page_url   = isset( $_POST['page_url'] )   ? esc_url_raw( $_POST['page_url'] )            : '';

        if ( empty( $user_msg ) || empty( $session_id ) ) {
            wp_send_json_error( 'Geçersiz istek.' );
        }

        $bot_token = get_option( 'marinos_chatbot_tg_token', '' );
        $chat_id   = get_option( 'marinos_chatbot_tg_chat_id', '' );

        if ( empty( $bot_token ) || empty( $chat_id ) ) {
            wp_send_json_error( 'Telegram yapılandırması eksik. Yönetici panelini kontrol edin.' );
        }

        $ip     = $this->get_ip();
        $logger = new Marinos_Chatbot_Logger();
        $logger->log( $session_id, 'user', $user_msg, $ip, $page_url );

        $tg_result = $this->send_to_telegram( $bot_token, $chat_id, $user_msg, $page_url, $session_id );

        if ( is_wp_error( $tg_result ) ) {
            wp_send_json_error( 'Telegram hatası: ' . $tg_result->get_error_message() );
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'marinos_tg_messages',
            [
                'session_id'    => $session_id,
                'visitor_msg'   => $user_msg,
                'tg_message_id' => (int) $tg_result,
                'created_at'    => current_time( 'mysql' ),
            ]
        );

        $this->schedule_email( $session_id, $page_url, $ip );

        wp_send_json_success( [
            'reply'   => get_option( 'marinos_chatbot_pending_message', 'Mesajınız ekibimize iletildi. Yanıt bekleniyor...' ),
            'pending' => true,
        ] );
    }

    // ─── Polling: Telegram'dan cevap geldi mi? ────────────────────────────────
    public function handle_poll() {
        check_ajax_referer( 'marinos_chatbot_nonce', 'nonce' );

        $session_id      = isset( $_POST['session_id'] )      ? sanitize_text_field( $_POST['session_id'] ) : '';
        $last_replied_id = isset( $_POST['last_replied_id'] ) ? absint( $_POST['last_replied_id'] )         : 0;

        if ( empty( $session_id ) ) {
            wp_send_json_error( 'Geçersiz istek.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'marinos_tg_messages';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, reply FROM $table
             WHERE session_id = %s AND id > %d AND reply IS NOT NULL
             ORDER BY replied_at ASC LIMIT 1",
            $session_id, $last_replied_id
        ) );

        if ( $row ) {
            ( new Marinos_Chatbot_Logger() )->log( $session_id, 'model', $row->reply, '', '' );
            wp_send_json_success( [
                'reply'           => $row->reply,
                'last_replied_id' => (int) $row->id,
            ] );
        } else {
            wp_send_json_success( [ 'reply' => null ] );
        }
    }

    // ─── Telegram Bot API — mesaj gönder ─────────────────────────────────────
    private function send_to_telegram( $token, $chat_id, $message, $page_url, $session_id ) {
        $short = strtoupper( substr( $session_id, 3, 6 ) );

        // Telegram MarkdownV2 yerine düz metin kullanıyoruz (escaping derdi yok)
        $text  = "Yeni web sitesi mesajı\n";
        $text .= "Oturum: {$short}\n";
        if ( $page_url ) $text .= "Sayfa: {$page_url}\n";
        $text .= str_repeat( '─', 26 ) . "\n";
        $text .= $message;

        $response = wp_remote_post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'body'    => [
                    'chat_id' => $chat_id,
                    'text'    => $text,
                ],
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) return $response;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['ok'] ) && ! empty( $data['result']['message_id'] ) ) {
            return $data['result']['message_id']; // integer
        }

        $desc = $data['description'] ?? 'Bilinmeyen hata.';
        return new WP_Error( 'tg_error', $desc );
    }

    private function schedule_email( $session_id, $page_url, $ip ) {
        $hook = 'marinos_send_conversation_email';
        $args = [ $session_id, $page_url, $ip ];
        $ts   = wp_next_scheduled( $hook, $args );
        if ( $ts ) wp_unschedule_event( $ts, $hook, $args );
        wp_schedule_single_event( time() + 60, $hook, $args );
    }

    private function get_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) )        return sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )  return sanitize_text_field( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
        return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    }
}

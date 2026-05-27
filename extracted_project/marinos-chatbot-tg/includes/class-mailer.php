<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Marinos_Chatbot_Mailer {

    private function get_recipients() {
        $raw  = get_option( 'marinos_chatbot_email', get_option( 'admin_email' ) );
        $list = preg_split( '/[\r\n,]+/', $raw );
        $out  = [];
        foreach ( $list as $email ) {
            $email = sanitize_email( trim( $email ) );
            if ( is_email( $email ) ) $out[] = $email;
        }
        return ! empty( $out ) ? $out : [ get_option( 'admin_email' ) ];
    }

    public function notify( $session_id, $page_url, $ip ) {
        $to     = $this->get_recipients();
        $logger = new Marinos_Chatbot_Logger();
        $logs   = $logger->get_session( $session_id );
        $count  = count( $logs );
        if ( $count === 0 ) return;
        $subject = '[Marinos Chatbot] Yeni Konusma — ' . date_i18n( 'd.m.Y H:i' ) . ' (' . $count . ' mesaj)';
        $body    = $this->build_body( $logs, $page_url, $ip, $count );
        wp_mail( $to, $subject, $body, [ 'Content-Type: text/plain; charset=UTF-8' ] );
    }

    public function notify_visitor_info( $session_id, $type, $value, $page_url ) {
        $to      = $this->get_recipients();
        $label   = $type === 'name' ? 'ISIM' : 'ILETISIM BILGISI';
        $subject = '[Marinos Chatbot] ' . $label . ': ' . $value . ' — ' . date_i18n( 'd.m.Y H:i' );
        $logger  = new Marinos_Chatbot_Logger();
        $logs    = $logger->get_session( $session_id );
        $body    = "==========================================\nZIYARETCI BILGISI ALINDI\n==========================================\n\n";
        $body   .= $label . "  : " . $value . "\nSayfa     : " . $page_url . "\nTarih     : " . date_i18n( 'd.m.Y H:i' ) . "\n\n";
        $body   .= $this->format_logs( $logs );
        $body   .= "\nPanel: " . admin_url( 'admin.php?page=marinos-chatbot-logs&session_id=' . urlencode( $session_id ) ) . "\n==========================================\n";
        wp_mail( $to, $subject, $body, [ 'Content-Type: text/plain; charset=UTF-8' ] );
    }

    private function build_body( $logs, $page_url, $ip, $count ) {
        $body  = "==========================================\nMARINOS CHATBOT - KONUSMA OZETI\n==========================================\n\n";
        $body .= "Tarih        : " . date_i18n( 'd.m.Y H:i' ) . "\nSayfa        : " . $page_url . "\nZiyaretci IP : " . $ip . "\nMesaj Sayisi : " . $count . "\n\n";
        $body .= "------------------------------------------\nKONUSMA\n------------------------------------------\n\n";
        $body .= $this->format_logs( $logs );
        $body .= "\nTum konusmalari gormek icin:\n" . admin_url( 'admin.php?page=marinos-chatbot-logs' ) . "\n==========================================\n";
        return $body;
    }

    private function format_logs( $logs ) {
        $out = '';
        foreach ( $logs as $row ) {
            if ( $row->role === 'user' )          $out .= ">> ZIYARETCI:\n   " . $row->message . "\n\n";
            elseif ( $row->role === 'visitor_info' ) $out .= "** BILGI: " . $row->message . "\n\n";
            else                                   $out .= "   EKIP:\n   " . $row->message . "\n\n";
        }
        return $out;
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Telegram Webhook
 *
 * Telegram bot'a gelen mesajları dinler.
 * Operatör "Yanıtla" (mesaja basılı tut → Reply) kullanarak cevap verdiğinde
 * reply_to_message.message_id aracılığıyla doğru oturuma eşleştirilir.
 *
 * Webhook URL: https://siteniz.com/wp-json/marinos-chatbot/v1/telegram
 * Bu URL admin paneldeki "Webhook Kur" butonu ile otomatik kaydedilir.
 */
class Marinos_Chatbot_Webhook {

    public static function handle( WP_REST_Request $request ) {
        $body    = $request->get_json_params();
        $message = $body['message'] ?? null;

        // Sadece text mesajları işle
        if ( empty( $message ) || empty( $message['text'] ) ) {
            return new WP_REST_Response( 'ok', 200 );
        }

        $reply_text   = trim( $message['text'] );
        $tg_msg_id    = (int) ( $message['message_id'] ?? 0 );

        // Operatör "Yanıtla" kullandıysa hangi mesaja cevap verildiğini biliyoruz
        $replied_to_id = isset( $message['reply_to_message']['message_id'] )
                         ? (int) $message['reply_to_message']['message_id']
                         : null;

        // /start veya bot komutlarını yoksay
        if ( strpos( $reply_text, '/' ) === 0 ) {
            return new WP_REST_Response( 'ok', 200 );
        }

        self::store_reply( $reply_text, $replied_to_id );

        return new WP_REST_Response( 'ok', 200 );
    }

    private static function store_reply( string $text, ?int $replied_to_tg_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'marinos_tg_messages';

        // 1. Öncelik: operatör "Yanıtla" ile cevap verdiyse → doğrudan eşleştir
        if ( $replied_to_tg_id ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM $table WHERE tg_message_id = %d AND reply IS NULL LIMIT 1",
                $replied_to_tg_id
            ) );
            if ( $row ) {
                $wpdb->update(
                    $table,
                    [ 'reply' => $text, 'replied_at' => current_time( 'mysql' ) ],
                    [ 'id'    => $row->id ]
                );
                return;
            }
        }

        // 2. Yedek: en eski cevaplanmamış mesaja ata (FIFO)
        $row = $wpdb->get_row(
            "SELECT id FROM $table WHERE reply IS NULL ORDER BY created_at ASC LIMIT 1"
        );
        if ( $row ) {
            $wpdb->update(
                $table,
                [ 'reply' => $text, 'replied_at' => current_time( 'mysql' ) ],
                [ 'id'    => $row->id ]
            );
        }
        // Bekleyen mesaj yoksa (operatörün kendi Telegram yazışması) görmezden gel
    }
}

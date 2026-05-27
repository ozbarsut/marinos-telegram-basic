<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Marinos_Chatbot_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_post_marinos_test_email',      [ $this, 'send_test_email' ] );
        add_action( 'admin_post_marinos_test_tg',         [ $this, 'send_test_tg' ] );
        add_action( 'admin_post_marinos_setup_webhook',   [ $this, 'setup_webhook' ] );
        add_action( 'admin_post_marinos_delete_session',  [ $this, 'delete_session' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    // ─── Webhook'u otomatik kur ───────────────────────────────────────────────
    public function setup_webhook() {
        check_admin_referer( 'marinos_setup_webhook' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetersiz yetki.' );

        $token       = get_option( 'marinos_chatbot_tg_token', '' );
        $webhook_url = rest_url( 'marinos-chatbot/v1/telegram' );

        if ( empty( $token ) ) {
            wp_redirect( admin_url( 'admin.php?page=marinos-chatbot&tg_webhook=notoken' ) );
            exit;
        }

        $response = wp_remote_post(
            "https://api.telegram.org/bot{$token}/setWebhook",
            [
                'body'    => [ 'url' => $webhook_url ],
                'timeout' => 10,
            ]
        );

        $ok = ( ! is_wp_error( $response )
                && ! empty( json_decode( wp_remote_retrieve_body( $response ), true )['ok'] ) );

        wp_redirect( admin_url( 'admin.php?page=marinos-chatbot&tg_webhook=' . ( $ok ? 'ok' : 'fail' ) ) );
        exit;
    }

    // ─── Test mesajı gönder ───────────────────────────────────────────────────
    public function send_test_tg() {
        check_admin_referer( 'marinos_test_tg' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetersiz yetki.' );

        $token   = get_option( 'marinos_chatbot_tg_token', '' );
        $chat_id = get_option( 'marinos_chatbot_tg_chat_id', '' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            wp_redirect( admin_url( 'admin.php?page=marinos-chatbot&tg_test=noconfig' ) );
            exit;
        }

        $response = wp_remote_post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'body'    => [
                    'chat_id' => $chat_id,
                    'text'    => "[Marinos Chatbot] Test mesajı — " . date_i18n( 'd.m.Y H:i' ) . "\nTelegram köprüsü çalışıyor!",
                ],
                'timeout' => 10,
            ]
        );

        $ok = ( ! is_wp_error( $response )
                && ! empty( json_decode( wp_remote_retrieve_body( $response ), true )['ok'] ) );

        wp_redirect( admin_url( 'admin.php?page=marinos-chatbot&tg_test=' . ( $ok ? 'ok' : 'fail' ) ) );
        exit;
    }

    public function send_test_email() {
        check_admin_referer( 'marinos_test_email' );
        $to   = get_option( 'marinos_chatbot_email', get_option( 'admin_email' ) );
        $sent = wp_mail( $to, '[Marinos Chatbot] Test E-postası', "Bu bir test mesajıdır.\n\nE-posta sistemi çalışıyor!" );
        wp_redirect( admin_url( 'admin.php?page=marinos-chatbot&marinos_test=' . ( $sent ? 'ok' : 'fail' ) ) );
        exit;
    }

    public function delete_session() {
        $session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( $_GET['session_id'] ) : '';
        check_admin_referer( 'marinos_delete_' . $session_id );
        if ( ! current_user_can( 'manage_options' ) || empty( $session_id ) ) wp_die( 'Yetersiz yetki.' );
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'marinos_chatbot_logs',  [ 'session_id' => $session_id ], [ '%s' ] );
        $wpdb->delete( $wpdb->prefix . 'marinos_tg_messages',   [ 'session_id' => $session_id ], [ '%s' ] );
        wp_redirect( admin_url( 'admin.php?page=marinos-chatbot-logs&deleted=1' ) );
        exit;
    }

    public function add_menu() {
        add_menu_page( 'Marinos Chatbot', 'Marinos Chatbot', 'manage_options', 'marinos-chatbot', [ $this, 'render_settings_page' ], 'dashicons-format-chat', 80 );
        add_submenu_page( 'marinos-chatbot', 'Ayarlar', 'Ayarlar', 'manage_options', 'marinos-chatbot', [ $this, 'render_settings_page' ] );
        add_submenu_page( 'marinos-chatbot', 'Konuşma Geçmişi', 'Konuşma Geçmişi', 'manage_options', 'marinos-chatbot-logs', [ $this, 'render_logs_page' ] );
    }

    public function register_settings() {
        $settings = [
            // Genel
            'marinos_chatbot_bot_name'          => 'sanitize_text_field',
            'marinos_chatbot_welcome_message'   => 'sanitize_textarea_field',
            'marinos_chatbot_pending_message'   => 'sanitize_textarea_field',
            'marinos_chatbot_email'             => 'sanitize_textarea_field',
            // CTA
            'marinos_chatbot_whatsapp_number'   => 'sanitize_text_field',
            'marinos_chatbot_phone_number'      => 'sanitize_text_field',
            'marinos_chatbot_cta_enabled'       => 'sanitize_text_field',
            'marinos_chatbot_cta_style'         => 'sanitize_text_field',
            'marinos_chatbot_cta_position'      => 'sanitize_text_field',
            'marinos_chatbot_cta_visibility'    => 'sanitize_text_field',
            // Karşılama
            'marinos_chatbot_auto_open'         => 'sanitize_text_field',
            'marinos_chatbot_greeting_delay'    => 'absint',
            'marinos_chatbot_typing_duration'   => 'absint',
            'marinos_chatbot_pulse_enabled'     => 'sanitize_text_field',
            'marinos_chatbot_quick_replies'     => 'sanitize_textarea_field',
            // Görünüm
            'marinos_chatbot_primary_color'     => 'sanitize_hex_color',
            'marinos_chatbot_header_color'      => 'sanitize_hex_color',
            'marinos_chatbot_send_color'        => 'sanitize_hex_color',
            'marinos_chatbot_bg_color'          => 'sanitize_hex_color',
            'marinos_chatbot_user_bubble_color' => 'sanitize_hex_color',
            'marinos_chatbot_qr_color'          => 'sanitize_hex_color',
            'marinos_chatbot_avatar_url'        => 'esc_url_raw',
            // Pozisyon
            'marinos_chatbot_desktop_right'     => 'sanitize_text_field',
            'marinos_chatbot_desktop_bottom'    => 'sanitize_text_field',
            'marinos_chatbot_mobile_right'      => 'sanitize_text_field',
            'marinos_chatbot_mobile_bottom'     => 'sanitize_text_field',
            'marinos_chatbot_zindex'            => 'absint',
            // Telegram
            'marinos_chatbot_tg_token'          => 'sanitize_text_field',
            'marinos_chatbot_tg_chat_id'        => 'sanitize_text_field',
        ];
        foreach ( $settings as $key => $cb ) {
            register_setting( 'marinos_chatbot_group', $key, [ 'sanitize_callback' => $cb ] );
        }
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'marinos-chatbot' ) === false ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $bot_name        = get_option( 'marinos_chatbot_bot_name', 'Destek Ekibi' );
        $welcome         = get_option( 'marinos_chatbot_welcome_message', 'Merhaba! Size nasıl yardımcı olabilirim?' );
        $pending_msg     = get_option( 'marinos_chatbot_pending_message', 'Mesajınız ekibimize iletildi. Yanıt bekleniyor...' );
        $email           = get_option( 'marinos_chatbot_email', get_option( 'admin_email' ) );
        $whatsapp        = get_option( 'marinos_chatbot_whatsapp_number', '' );
        $phone           = get_option( 'marinos_chatbot_phone_number', '' );
        $cta_enabled     = get_option( 'marinos_chatbot_cta_enabled', '1' );
        $cta_style       = get_option( 'marinos_chatbot_cta_style', 'icon_text' );
        $cta_position    = get_option( 'marinos_chatbot_cta_position', 'above_input' );
        $cta_visibility  = get_option( 'marinos_chatbot_cta_visibility', 'always' );
        $auto_open       = get_option( 'marinos_chatbot_auto_open', '1' );
        $greeting_delay  = get_option( 'marinos_chatbot_greeting_delay', 1 );
        $typing_duration = get_option( 'marinos_chatbot_typing_duration', 1200 );
        $pulse_enabled   = get_option( 'marinos_chatbot_pulse_enabled', '1' );
        $quick_replies   = get_option( 'marinos_chatbot_quick_replies', '' );
        $primary_color   = get_option( 'marinos_chatbot_primary_color', '#1a73e8' );
        $header_color    = get_option( 'marinos_chatbot_header_color', '' );
        $send_color      = get_option( 'marinos_chatbot_send_color', '' );
        $bg_color        = get_option( 'marinos_chatbot_bg_color', '#f8f9fb' );
        $user_bubble     = get_option( 'marinos_chatbot_user_bubble_color', '' );
        $qr_color        = get_option( 'marinos_chatbot_qr_color', '' );
        $avatar_url      = get_option( 'marinos_chatbot_avatar_url', '' );
        $desk_right      = get_option( 'marinos_chatbot_desktop_right', '24px' );
        $desk_bottom     = get_option( 'marinos_chatbot_desktop_bottom', '24px' );
        $mob_right       = get_option( 'marinos_chatbot_mobile_right', '16px' );
        $mob_bottom      = get_option( 'marinos_chatbot_mobile_bottom', '16px' );
        $zindex          = get_option( 'marinos_chatbot_zindex', 99999 );
        $tg_token        = get_option( 'marinos_chatbot_tg_token', '' );
        $tg_chat_id      = get_option( 'marinos_chatbot_tg_chat_id', '' );
        $webhook_url     = rest_url( 'marinos-chatbot/v1/telegram' );
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:26px;">✈️</span>
                Marinos Chatbot V3.1 — Telegram Köprüsü
            </h1>

            <?php /* Bildirimler */ ?>
            <?php if ( isset($_GET['tg_webhook']) ): ?>
                <?php if ($_GET['tg_webhook'] === 'ok'): ?><div class="notice notice-success is-dismissible"><p>Webhook başarıyla kuruldu. Artık Telegram'dan gelen cevaplar widget'a yansıyacak.</p></div>
                <?php elseif ($_GET['tg_webhook'] === 'notoken'): ?><div class="notice notice-error is-dismissible"><p>Bot Token girilmemiş. Önce token'ı kaydedin, sonra webhook kurun.</p></div>
                <?php else: ?><div class="notice notice-error is-dismissible"><p>Webhook kurulamadı. Bot Token'ı kontrol edin.</p></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ( isset($_GET['tg_test']) ): ?>
                <?php if ($_GET['tg_test'] === 'ok'): ?><div class="notice notice-success is-dismissible"><p>Test mesajı Telegram'a gönderildi.</p></div>
                <?php elseif ($_GET['tg_test'] === 'noconfig'): ?><div class="notice notice-error is-dismissible"><p>Bot Token ve Chat ID alanlarını doldurun.</p></div>
                <?php else: ?><div class="notice notice-error is-dismissible"><p>Telegram mesajı gönderilemedi. Token ve Chat ID değerlerini kontrol edin.</p></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ( isset($_GET['marinos_test']) ): ?>
                <?php if ($_GET['marinos_test'] === 'ok'): ?><div class="notice notice-success is-dismissible"><p>Test e-postası gönderildi.</p></div>
                <?php else: ?><div class="notice notice-error is-dismissible"><p>E-posta gönderilemedi.</p></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php settings_errors(); ?>

            <style>
            .mc-section { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px 24px; margin-bottom:20px; }
            .mc-section h2 { margin:0 0 16px; font-size:14px; text-transform:uppercase; letter-spacing:.5px; color:#64748b; border-bottom:1px solid #f1f5f9; padding-bottom:8px; }
            .mc-section.mc-tg { border-color:#2AABEE; }
            .mc-section.mc-tg h2 { color:#1a87c4; }
            .mc-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
            .mc-grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
            .mc-field label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
            .mc-field input,.mc-field textarea,.mc-field select { width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; }
            .mc-field .desc { font-size:12px; color:#6b7280; margin-top:4px; }
            .mc-step { display:flex; gap:10px; align-items:flex-start; padding:10px 0; border-bottom:1px solid #f1f5f9; }
            .mc-step:last-child { border-bottom:none; }
            .mc-step-num { background:#2AABEE; color:#fff; border-radius:50%; width:24px; height:24px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; }
            .mc-step-body { font-size:13px; line-height:1.6; }
            .mc-code { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-family:monospace; font-size:12px; color:#1e40af; }
            @media(max-width:782px){.mc-grid2,.mc-grid3{grid-template-columns:1fr;}}
            </style>

            <form method="post" action="options.php">
                <?php settings_fields( 'marinos_chatbot_group' ); ?>

                <!-- TELEGRAM KÖPRÜSÜ -->
                <div class="mc-section mc-tg">
                    <h2>Telegram Köprüsü — Kurulum</h2>

                    <div class="mc-step">
                        <div class="mc-step-num">1</div>
                        <div class="mc-step-body">
                            Telegram'da <strong>@BotFather</strong>'a yazın → <span class="mc-code">/newbot</span> → bir isim ve kullanıcı adı belirleyin → size <strong>Bot Token</strong> verecek (şu formatta: <span class="mc-code">1234567890:ABCdef...</span>).
                        </div>
                    </div>
                    <div class="mc-step">
                        <div class="mc-step-num">2</div>
                        <div class="mc-step-body">
                            <strong>Chat ID'nizi öğrenin:</strong> Az önce oluşturduğunuz bota bir mesaj yazın (herhangi bir şey), ardından tarayıcıda şu URL'yi açın:<br>
                            <span class="mc-code">https://api.telegram.org/bot<strong>TOKEN_BURAYA</strong>/getUpdates</span><br>
                            Gelen JSON içinde <span class="mc-code">result[0].message.chat.id</span> değerini kopyalayın. Bu sizin Chat ID'niz.
                        </div>
                    </div>
                    <div class="mc-step">
                        <div class="mc-step-num">3</div>
                        <div class="mc-step-body">
                            Token ve Chat ID'yi aşağıya girin, <strong>Kaydet</strong>'e tıklayın, ardından <strong>Webhook Kur</strong> butonuna basın. Bitti.
                        </div>
                    </div>
                    <div class="mc-step">
                        <div class="mc-step-num">4</div>
                        <div class="mc-step-body">
                            Widget'tan gelen her mesajı Telegram'da <strong>basılı tutun → Yanıtla (Reply)</strong> ile cevaplayın. Cevap otomatik olarak doğru ziyaretçinin ekranına yansır.
                        </div>
                    </div>

                    <div class="mc-grid2" style="margin-top:20px;">
                        <div class="mc-field">
                            <label>Bot Token</label>
                            <input type="password" name="marinos_chatbot_tg_token" value="<?php echo esc_attr($tg_token); ?>" autocomplete="off" placeholder="1234567890:ABCdefGHIjklMNOpqrSTUvwxYZ">
                            <p class="desc">@BotFather'dan aldığınız token.</p>
                        </div>
                        <div class="mc-field">
                            <label>Chat ID</label>
                            <input type="text" name="marinos_chatbot_tg_chat_id" value="<?php echo esc_attr($tg_chat_id); ?>" placeholder="123456789">
                            <p class="desc">Mesajların düşeceği hesabın Chat ID'si.</p>
                        </div>
                    </div>

                    <?php if ( $tg_token && $tg_chat_id ): ?>
                    <div style="margin-top:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px 14px;font-size:13px;">
                        <strong>Webhook URL:</strong>
                        <span class="mc-code" style="display:block;margin-top:4px;word-break:break-all;"><?php echo esc_html($webhook_url); ?></span>
                        <p style="margin:6px 0 0;color:#64748b;font-size:12px;">Bu URL "Webhook Kur" butonu ile otomatik kaydedilir. Manuel işlem gerekmez.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- GENEL -->
                <div class="mc-section">
                    <h2>Genel</h2>
                    <div class="mc-grid2">
                        <div class="mc-field">
                            <label>Bot / Ekip Adı</label>
                            <input type="text" name="marinos_chatbot_bot_name" value="<?php echo esc_attr($bot_name); ?>">
                        </div>
                        <div class="mc-field">
                            <label>Bildirim E-postaları</label>
                            <textarea name="marinos_chatbot_email" rows="2" placeholder="info@firma.com"><?php echo esc_textarea($email); ?></textarea>
                            <p class="desc">Konuşma özeti bu adreslere gider.</p>
                        </div>
                    </div>
                    <div class="mc-grid2" style="margin-top:12px;">
                        <div class="mc-field">
                            <label>Karşılama Mesajı</label>
                            <textarea name="marinos_chatbot_welcome_message" rows="2"><?php echo esc_textarea($welcome); ?></textarea>
                        </div>
                        <div class="mc-field">
                            <label>Bekleme Metni (mesaj gönderdikten sonra)</label>
                            <textarea name="marinos_chatbot_pending_message" rows="2"><?php echo esc_textarea($pending_msg); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- KARŞILAMA AKIŞI -->
                <div class="mc-section">
                    <h2>Karşılama Akışı</h2>
                    <div class="mc-grid2">
                        <div class="mc-field">
                            <label>Otomatik Aç</label>
                            <select name="marinos_chatbot_auto_open">
                                <option value="1" <?php selected($auto_open,'1'); ?>>Evet</option>
                                <option value="0" <?php selected($auto_open,'0'); ?>>Hayır</option>
                            </select>
                        </div>
                        <div class="mc-field">
                            <label>Pulse Efekti</label>
                            <select name="marinos_chatbot_pulse_enabled">
                                <option value="1" <?php selected($pulse_enabled,'1'); ?>>Aktif</option>
                                <option value="0" <?php selected($pulse_enabled,'0'); ?>>Pasif</option>
                            </select>
                        </div>
                        <div class="mc-field">
                            <label>Karşılama Gecikmesi (sn)</label>
                            <input type="number" name="marinos_chatbot_greeting_delay" value="<?php echo esc_attr($greeting_delay); ?>" min="0" max="30" style="width:100px;">
                        </div>
                        <div class="mc-field">
                            <label>Yazıyor Animasyonu (ms)</label>
                            <input type="number" name="marinos_chatbot_typing_duration" value="<?php echo esc_attr($typing_duration); ?>" min="300" max="5000" step="100" style="width:100px;">
                        </div>
                    </div>
                    <div class="mc-field" style="margin-top:12px;">
                        <label>Hızlı Cevap Butonları</label>
                        <textarea name="marinos_chatbot_quick_replies" rows="3" placeholder="Her satıra bir buton"><?php echo esc_textarea($quick_replies); ?></textarea>
                    </div>
                </div>

                <!-- CTA -->
                <div class="mc-section">
                    <h2>CTA / İletişim Butonları</h2>
                    <div class="mc-grid2">
                        <div class="mc-field">
                            <label>WhatsApp Numarası</label>
                            <input type="text" name="marinos_chatbot_whatsapp_number" value="<?php echo esc_attr($whatsapp); ?>" placeholder="905405710707">
                        </div>
                        <div class="mc-field">
                            <label>Telefon</label>
                            <input type="text" name="marinos_chatbot_phone_number" value="<?php echo esc_attr($phone); ?>" placeholder="905405710707">
                        </div>
                        <div class="mc-field">
                            <label>CTA Çubuğu</label>
                            <select name="marinos_chatbot_cta_enabled">
                                <option value="1" <?php selected($cta_enabled,'1'); ?>>Aktif</option>
                                <option value="0" <?php selected($cta_enabled,'0'); ?>>Pasif</option>
                            </select>
                        </div>
                        <div class="mc-field">
                            <label>CTA Stil</label>
                            <select name="marinos_chatbot_cta_style">
                                <option value="icon_only" <?php selected($cta_style,'icon_only'); ?>>Sadece İkon</option>
                                <option value="icon_text" <?php selected($cta_style,'icon_text'); ?>>İkon + Yazı</option>
                            </select>
                        </div>
                        <div class="mc-field">
                            <label>CTA Pozisyon</label>
                            <select name="marinos_chatbot_cta_position">
                                <option value="above_input"    <?php selected($cta_position,'above_input'); ?>>Input Üstü</option>
                                <option value="below_messages" <?php selected($cta_position,'below_messages'); ?>>Mesajların Altı</option>
                            </select>
                        </div>
                        <div class="mc-field">
                            <label>CTA Görünürlük</label>
                            <select name="marinos_chatbot_cta_visibility">
                                <option value="always"        <?php selected($cta_visibility,'always'); ?>>Her Zaman</option>
                                <option value="after_first"   <?php selected($cta_visibility,'after_first'); ?>>İlk Mesajdan Sonra</option>
                                <option value="after_inactivity" <?php selected($cta_visibility,'after_inactivity'); ?>>İnaktivite Sonrası</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- RENKLER -->
                <div class="mc-section">
                    <h2>Görünüm — Renkler</h2>
                    <div class="mc-grid3">
                        <div class="mc-field"><label>Ana Renk</label><input type="text" name="marinos_chatbot_primary_color" value="<?php echo esc_attr($primary_color); ?>" class="mc-color"></div>
                        <div class="mc-field"><label>Header</label><input type="text" name="marinos_chatbot_header_color" value="<?php echo esc_attr($header_color); ?>" class="mc-color"><p class="desc">Boşsa Ana Renk.</p></div>
                        <div class="mc-field"><label>Gönder Butonu</label><input type="text" name="marinos_chatbot_send_color" value="<?php echo esc_attr($send_color); ?>" class="mc-color"><p class="desc">Boşsa Ana Renk.</p></div>
                        <div class="mc-field"><label>Mesaj Alanı</label><input type="text" name="marinos_chatbot_bg_color" value="<?php echo esc_attr($bg_color); ?>" class="mc-color"></div>
                        <div class="mc-field"><label>Kullanıcı Balonu</label><input type="text" name="marinos_chatbot_user_bubble_color" value="<?php echo esc_attr($user_bubble); ?>" class="mc-color"><p class="desc">Boşsa Ana Renk.</p></div>
                        <div class="mc-field"><label>Hızlı Cevap</label><input type="text" name="marinos_chatbot_qr_color" value="<?php echo esc_attr($qr_color); ?>" class="mc-color"><p class="desc">Boşsa Ana Renk.</p></div>
                        <div class="mc-field" style="grid-column:1/-1;"><label>Avatar URL</label><input type="text" name="marinos_chatbot_avatar_url" value="<?php echo esc_attr($avatar_url); ?>" placeholder="https://..."></div>
                    </div>
                </div>

                <!-- POZİSYON -->
                <div class="mc-section">
                    <h2>Pozisyon</h2>
                    <div class="mc-grid2">
                        <div>
                            <p style="font-weight:600;font-size:13px;margin:0 0 8px;">Masaüstü</p>
                            <div class="mc-grid2">
                                <div class="mc-field"><label>Sağdan</label><input type="text" name="marinos_chatbot_desktop_right" value="<?php echo esc_attr($desk_right); ?>" style="width:90px;"></div>
                                <div class="mc-field"><label>Alttan</label><input type="text" name="marinos_chatbot_desktop_bottom" value="<?php echo esc_attr($desk_bottom); ?>" style="width:90px;"></div>
                            </div>
                        </div>
                        <div>
                            <p style="font-weight:600;font-size:13px;margin:0 0 8px;">Mobil</p>
                            <div class="mc-grid2">
                                <div class="mc-field"><label>Sağdan</label><input type="text" name="marinos_chatbot_mobile_right" value="<?php echo esc_attr($mob_right); ?>" style="width:90px;"></div>
                                <div class="mc-field"><label>Alttan</label><input type="text" name="marinos_chatbot_mobile_bottom" value="<?php echo esc_attr($mob_bottom); ?>" style="width:90px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mc-field" style="margin-top:12px;"><label>Z-Index</label><input type="number" name="marinos_chatbot_zindex" value="<?php echo esc_attr($zindex); ?>" style="width:100px;"></div>
                </div>

                <?php submit_button( 'Kaydet' ); ?>

                <?php
                $webhook_url_action = wp_nonce_url( admin_url( 'admin-post.php?action=marinos_setup_webhook' ), 'marinos_setup_webhook' );
                $test_tg_url        = wp_nonce_url( admin_url( 'admin-post.php?action=marinos_test_tg' ),       'marinos_test_tg'       );
                $test_email_url     = wp_nonce_url( admin_url( 'admin-post.php?action=marinos_test_email' ),    'marinos_test_email'    );
                ?>
                <a href="<?php echo esc_url($webhook_url_action); ?>" class="button button-primary" style="margin-left:10px;background:#2AABEE;border-color:#1a87c4;">Webhook Kur</a>
                <a href="<?php echo esc_url($test_tg_url); ?>"        class="button" style="margin-left:10px;">Test Telegram Gönder</a>
                <a href="<?php echo esc_url($test_email_url); ?>"      class="button" style="margin-left:10px;">Test E-posta Gönder</a>
            </form>
        </div>
        <script>jQuery(document).ready(function($){ $('.mc-color').wpColorPicker(); });</script>
        <?php
    }

    public function render_logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        global $wpdb;
        $table          = $wpdb->prefix . 'marinos_chatbot_logs';
        $per_page       = 20;
        $page           = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset         = ( $page - 1 ) * $per_page;
        $session_filter = isset( $_GET['session_id'] ) ? sanitize_text_field( $_GET['session_id'] ) : '';

        if ( $session_filter ) {
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE session_id = %s ORDER BY created_at ASC", $session_filter ) );
        } else {
            $sessions = $wpdb->get_results( "SELECT session_id, visitor_ip, visitor_page, MIN(created_at) as started, COUNT(*) as msg_count FROM $table GROUP BY session_id ORDER BY started DESC LIMIT $per_page OFFSET $offset" );
            $total    = $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM $table" );
        }
        ?>
        <div class="wrap">
            <h1>Marinos Chatbot — Konuşma Geçmişi</h1>
            <?php if ( isset($_GET['deleted']) ): ?><div class="notice notice-success is-dismissible"><p>Konuşma silindi.</p></div><?php endif; ?>
            <?php if ( $session_filter ): ?>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=marinos-chatbot-logs')); ?>">&larr; Geri</a>
                    &nbsp;
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=marinos_delete_session&session_id='.urlencode($session_filter)),'marinos_delete_'.$session_filter)); ?>"
                       onclick="return confirm('Silmek istediğinizden emin misiniz?');"
                       class="button button-secondary" style="color:#cc0000;border-color:#cc0000;">Bu Konuşmayı Sil</a>
                </p>
                <table class="widefat striped">
                    <thead><tr><th>Rol</th><th>Mesaj</th><th>Zaman</th></tr></thead>
                    <tbody>
                    <?php foreach ( $rows as $row ): ?>
                        <tr style="<?php echo $row->role === 'user' ? 'background:#f0f4ff;' : ''; ?>">
                            <td><strong><?php echo $row->role === 'user' ? 'Ziyaretçi' : 'Ekip'; ?></strong></td>
                            <td><?php echo nl2br(esc_html($row->message)); ?></td>
                            <td><?php echo esc_html($row->created_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="widefat striped">
                    <thead><tr><th>Tarih</th><th>IP</th><th>Sayfa</th><th>Mesaj</th><th>İşlem</th></tr></thead>
                    <tbody>
                    <?php if ( empty($sessions) ): ?><tr><td colspan="5">Henüz konuşma yok.</td></tr>
                    <?php else: foreach ( $sessions as $s ): ?>
                        <tr>
                            <td><?php echo esc_html($s->started); ?></td>
                            <td><?php echo esc_html($s->visitor_ip); ?></td>
                            <td><?php echo esc_html($s->visitor_page); ?></td>
                            <td><?php echo intval($s->msg_count); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=marinos-chatbot-logs&session_id='.urlencode($s->session_id))); ?>">Görüntüle</a>
                                &nbsp;|&nbsp;
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=marinos_delete_session&session_id='.urlencode($s->session_id)),'marinos_delete_'.$s->session_id)); ?>"
                                   onclick="return confirm('Emin misiniz?');" style="color:#cc0000;">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <?php
                $total_pages = ceil($total/$per_page);
                if ($total_pages > 1) {
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    echo paginate_links(['base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$page,'total'=>$total_pages]);
                    echo '</div></div>';
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

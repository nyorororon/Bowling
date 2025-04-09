<?php
/**
 * Ashe Child Theme functions and definitions
 *
 * @package Asheほお
 */

function ashe_child_enqueue_styles() {
    wp_enqueue_style('ashe-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('ashe-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('ashe-style')
    );
}
add_action('wp_enqueue_scripts', 'ashe_child_enqueue_styles');


// 商品呼び出しのため追加日向20250404
function ashe_custom_product_block_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => ''
    ), $atts, 'ashe_product_block');

    if (empty($atts['id'])) return '';
    if (!function_exists('wc_get_product')) return '';

    $product = wc_get_product($atts['id']);
    if (!$product) return '';

    $product_post = get_post($product->get_id());
    $product_description = isset($product_post->post_content) ? $product_post->post_content : '';
    $short_description = $product->get_short_description();
    $stock = $product->get_stock_quantity();
    $capacity = $stock !== null ? $stock . '名' : '—';

    $button_url = esc_url(add_query_arg('add-to-cart', $product->get_id(), wc_get_cart_url()));
    $button_text = $product->add_to_cart_text();

    ob_start();
    ?>
    <div class="custom-product-block">
        <div class="custom-product-block-row-top">
            <h2 class="custom-product-title"><?php echo esc_html($product->get_name()); ?></h2>
            <div class="custom-product-description">
                <?php echo wpautop($product_description); ?>
            </div>
        </div>

        <div class="custom-product-block-row-bottom">
            <div class="custom-product-short-description">
                <?php echo wpautop($short_description); ?>
            </div>

            <div class="custom-product-capacity">
                <p>残り：<?php echo esc_html($capacity); ?></p>
            </div>

            <div class="custom-product-button">
                <a href="<?php echo $button_url; ?>" class="button add_to_cart_button"><?php echo esc_html($button_text); ?></a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ashe_product_block', 'ashe_custom_product_block_shortcode');


// カートに戻るボタン追加
add_action('woocommerce_before_cart', 'add_back_to_home_button');
function add_back_to_home_button() {
    echo '<div class="back-to-home-wrapper"><a class="back-to-home-button" href="' . esc_url(home_url()) . '">トップページに戻る</a></div>';
}


// チェックアウトページ「その他リクエスト」項目の削除
add_action('wp_footer', function () {
    if (is_checkout()) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const field = document.getElementById('order_comments_field');
            if (field) {
                field.remove();
            }
        });
        </script>
        <?php
    }
});


// ユーザー
add_action('pre_get_posts', 'limit_products_to_own_author_only');
function limit_products_to_own_author_only($query) {
    // 管理画面 ＆ 商品一覧 ＆ メインクエリ
    if (
        is_admin() &&
        $query->is_main_query() &&
        $query->get('post_type') === 'product'
    ) {
        // 管理者以外のユーザーには制限をかける
        if (!current_user_can('administrator')) {
            $query->set('author', get_current_user_id());
        }
    }
}
// カートに商品が既にある場合は、追加を拒否してエラーメッセージ表示日向追記20250409
add_filter('woocommerce_add_to_cart_validation', 'block_multiple_event_additions', 10, 3);
function block_multiple_event_additions($passed, $product_id, $quantity) {
    if (WC()->cart->get_cart_contents_count() > 0) {
        wc_add_notice('1会場ごとにしか購入できません。先に現在の予約をカートから削除してください。', 'error');
        return false;
    }
    return $passed;
}

// 空のカートページ：「ショップに戻る」ボタンを「トップページに戻る」に変更追記日向20250409
add_filter('woocommerce_return_to_shop_text', function () {
    return 'トップページに戻る';
});

add_filter('woocommerce_return_to_shop_redirect', function () {
    return home_url('/');
});
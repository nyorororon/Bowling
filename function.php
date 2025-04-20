<?php
/**
 * Ashe Child Theme functions and definitions
 *
 * @package Ashe
 */

function ashe_child_enqueue_styles() {
    wp_enqueue_style('ashe-style', get_template_directory_uri() . '/style.css');

    wp_enqueue_style(
        'ashe-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('ashe-style'),
        filemtime(get_stylesheet_directory() . '/style.css') // キャッシュ防止
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
/*
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
*/


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

// 予約者情報未入力の際表示させる警告追記日向20250410
add_action('wp_footer', function () {
    if (is_checkout()) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const target = document.body;

            const observer = new MutationObserver(() => {
                const errorList = document.querySelector('.woocommerce-error');
                if (!errorList) return;

                const errors = errorList.querySelectorAll('li');
                let hasFooEventsErrors = false;

                errors.forEach(li => {
                    if (li.textContent.includes('attendee') || li.textContent.includes('is required')) {
                        li.style.display = 'none'; // FooEvents系エラーを非表示
                        hasFooEventsErrors = true;
                    }
                });

                const alreadyExists = [...errors].some(li => li.textContent.includes('＊のついている項目'));
                if (hasFooEventsErrors && !alreadyExists) {
                    const msg = document.createElement('li');
                    msg.textContent = '＊のついている項目は必須項目です。入力して注文ボタンへお進み下さい。';
                    msg.style.fontWeight = 'bold';
                    msg.style.color = '#cc0000';
                    errorList.insertBefore(msg, errorList.firstChild);
                }
            });

            observer.observe(target, { childList: true, subtree: true });
        });
        </script>
        <?php
    }
});

/*
// .woocommerce-additional-fieldsに表示されてしまうオプション非表示のため追記日向

add_action('wp_footer', function () {
    if (is_checkout()) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const additionalFields = document.querySelector('.woocommerce-additional-fields');
            if (additionalFields) {
                const commentField = additionalFields.querySelector('#order_comments_field');
                if (commentField) {
                    commentField.remove();
                }
            }
        });
        </script>
        <?php
    }
});
*/


// 姓名順番入れ替えのため追記日向20250410
add_action('wp_footer', function () {
    if (!is_checkout()) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const reorderNameFields = () => {
            document.querySelectorAll('.fooevents-attendee').forEach(attendee => {
                const lastName = attendee.querySelector('.fooevents-attendee-last-name');
                const firstName = attendee.querySelector('.fooevents-attendee-first-name');

                // 「名」が「姓」の前にある場合だけ順番を修正
                if (lastName && firstName && firstName.compareDocumentPosition(lastName) & Node.DOCUMENT_POSITION_FOLLOWING) {
                    attendee.insertBefore(lastName, firstName);
                }
            });
        };

        const interval = setInterval(() => {
            if (document.querySelector('.fooevents-attendee .fooevents-attendee-first-name')) {
                reorderNameFields();
                clearInterval(interval);
            }
        }, 300);

        setTimeout(() => clearInterval(interval), 10000);
    });
    </script>
    <?php
});

// 郵便番号入力で市町村自動入力日向追記
add_action('wp_footer', function () {
    if (!is_checkout()) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const postcodeInput = document.querySelector('#billing_postcode');
        const stateSelect = document.querySelector('#billing_state');
        const cityInput = document.querySelector('#billing_city');
        const address1Input = document.querySelector('#billing_address_1');

        if (!postcodeInput) return;

        postcodeInput.addEventListener('blur', function () {
            const zipcode = postcodeInput.value.replace(/[^\d]/g, '');
            if (zipcode.length !== 7) return;

            fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${zipcode}`)
                .then(response => response.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        const result = data.results[0];

                        // 都道府県（billing_state）
                        const prefecture = result.address1;
                        for (let i = 0; i < stateSelect.options.length; i++) {
                            if (stateSelect.options[i].text === prefecture) {
                                stateSelect.selectedIndex = i;
                                const event = new Event('change', { bubbles: true });
                                stateSelect.dispatchEvent(event);
                                break;
                            }
                        }

                        // 市区町村（billing_city）→ 住所2と3を結合（例：津市＋桜橋）
                        if (cityInput) {
                            cityInput.value = result.address2 + result.address3;
                        }

                        // 番地欄（billing_address_1）はクリア or 入力しない
                        if (address1Input) {
                            address1Input.value = '';
                        }
                    }
                })
                .catch(error => {
                    console.error('住所の自動補完エラー:', error);
                });
        });
    });
    </script>
    <?php
});

// 予約完了画面固定ページ名カスタム追記日向
// タイトルを変更（今のままでOK）
add_filter('the_title', function($title, $id) {
    if (is_order_received_page() && get_post_type($id) === 'page' && is_main_query()) {
        return '予約完了いたしました。';
    }
    return $title;
}, 10, 2);

// 下部に案内メッセージも追加（こちらが本文）
// 購入完了メッセージ直下に案内文を表示
add_action('woocommerce_before_thankyou', function($order_id) {
    echo '<div class="custom-thankyou-message" style="margin-top: 1.5em; padding: 1.5em; background-color: #f0f4f9; border-left: 4px solid #2B79C9;">';
    echo '<p>登録されたメールアドレス宛に、予約完了の確認メールをお送りしております。</p>';
    echo '<p>まれに、お客様のメール設定（迷惑メールフォルダ等）により、メールが届かない場合があります。</p>';
    echo '<p>ただし、この画面が表示されていれば予約は正常に完了しておりますのでご安心ください。</p>';
    echo '<p>万一メールが届かない場合は、ボウリング場までお電話にてご確認くださいますようお願いいたします。</p>';
    echo '</div>';
});


// ありがとうございました。ご注文を受け付けました。を非表示にする。

add_action('woocommerce_before_thankyou', function() {
    if (is_order_received_page()) {
        echo '<style>.woocommerce-thankyou-order-received { display: none !important; }</style>';
        echo '<div style="height: 1.5em;"></div>'; // ← 空白のスペース行（高さ調整）
    }
});

// 再注文ボタンを非表示にする。
add_action('wp_head', function () {
    if (is_order_received_page()) {
        echo '<style>
        .woocommerce-order-receipt .order-again,
        .woocommerce-order-received .order-again {
            display: none !important;
        }
        </style>';
    }
});


// 新しい注文時、商品投稿者にメール通知を送る（商品ごとに異なる投稿者対応）日向追記
add_filter('woocommerce_email_recipient_new_order', 'custom_email_to_product_author_only', 10, 2);

function custom_email_to_product_author_only($recipient, $order) {
	if (!$order || is_admin()) return $recipient;

	$emails_to_notify = [];

	foreach ($order->get_items() as $item) {
		$product_id = $item->get_product_id();
		$author_id = get_post_field('post_author', $product_id);
		$author = get_user_by('ID', $author_id);
		
		if ($author && !in_array($author->user_email, $emails_to_notify)) {
			$emails_to_notify[] = $author->user_email;
		}
	}

	// 通知先がある場合はそれらにのみ送信（管理者には送らない）
	if (!empty($emails_to_notify)) {
		return implode(',', $emails_to_notify);
	}

	// 念のため、投稿者が見つからない場合はデフォルトの受信者（管理者など）に送信
	return $recipient;
}


// WooCommerce の翻訳だけ自動更新を停止
add_filter( 'auto_update_translation', function( $update, $language_update ) {
    if ( isset($language_update->slug) && $language_update->slug === 'woocommerce' ) {
        return false; // WooCommerce の翻訳だけ自動更新を停止
    }
    return $update; // 他はそのまま
}, 10, 2 );


// チェックアウトフォームから「会社名」を削除、「国」を日本に固定して非表示
add_filter( 'woocommerce_checkout_fields', 'custom_customize_billing_fields' );
function custom_customize_billing_fields( $fields ) {

    // 会社名を削除
    unset( $fields['billing']['billing_company'] );

    // 国を日本に固定・非表示にし、ラベルも消す
    $fields['billing']['billing_country'] = array(
        'type'              => 'hidden',
        'label'             => '', // ラベルを空にする
        'default'           => 'JP',
        'required'          => false,
        'custom_attributes' => array( 'readonly' => 'readonly' )
    );

    return $fields;
}



add_filter( 'woocommerce_checkout_fields', 'move_order_comments_to_billing_section' );
function move_order_comments_to_billing_section( $fields ) {
    if ( isset( $fields['order']['order_comments'] ) ) {
        // 備考欄の内容を取得
        $order_comments = $fields['order']['order_comments'];

        // セクションを billing に変更し、優先順位をメールアドレスの後に設定
        $fields['billing']['order_comments'] = array_merge( $order_comments, array(
            'priority' => 125, // メールアドレスが120なので、その下に表示される
        ) );

        // 元の位置から削除
        unset( $fields['order']['order_comments'] );
    }
    return $fields;
}
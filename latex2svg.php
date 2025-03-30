<?php
/*
Plugin Name: LaTeX to SVG (Inline Only)
Description: 精确转换行内LaTeX公式为SVG，支持智能错误提示
Version: 2.2.2
Author: Lucas2011
*/

// 注册设置页面
add_action('admin_menu', 'latex2svg_add_admin_menu');
add_action('admin_init', 'latex2svg_settings_init');

// 处理文章内容
add_filter('the_content', 'latex2svg_process_content', 9);

// 添加样式
add_action('wp_head', 'latex2svg_add_styles');

function latex2svg_add_admin_menu() {
    add_options_page(
        'LaTeX 设置',
        'LaTeX 转 SVG',
        'manage_options',
        'latex2svg',
        'latex2svg_options_page'
    );
}

function latex2svg_settings_init() {
    register_setting('latex2svg', 'latex2svg_settings');

    add_settings_section(
        'latex2svg_section',
        'API 配置',
        'latex2svg_section_cb',
        'latex2svg'
    );

    add_settings_field(
        'api_token',
        'API 密钥',
        'latex2svg_api_token_render',
        'latex2svg',
        'latex2svg_section'
    );

    add_settings_field(
        'api_url',
        'API 地址',
        'latex2svg_api_url_render',
        'latex2svg',
        'latex2svg_section'
    );

    add_settings_field(
        'inline_scale',
        '缩放比例',
        'latex2svg_inline_scale_render',
        'latex2svg',
        'latex2svg_section'
    );

    add_settings_field(
        'border',
        '渲染后 LaTeX 白边大小',
        'latex2svg_border_render',
        'latex2svg',
        'latex2svg_section'
    );

    add_settings_field(
        'scale_chemfig',
        '常用的 chemfig 键长，即“-[2,0.8]”中的那个0.8。用于估算svg的高度。',
        'latex2svg_scale_chemfig_render',
        'latex2svg',
        'latex2svg_section'
    );
}

function latex2svg_api_token_render() {
    $options = get_option('latex2svg_settings');
    echo '<input type="text" name="latex2svg_settings[api_token]" value="' . esc_attr($options['api_token'] ?? '') . '" style="width: 300px;">';
}

function latex2svg_api_url_render() {
    $options = get_option('latex2svg_settings');
    echo '<input type="url" name="latex2svg_settings[api_url]" value="' . esc_attr($options['api_url'] ?? 'https://example.com') . '" style="width: 300px;">';
}

function latex2svg_inline_scale_render() {
    $options = get_option('latex2svg_settings');
    echo '<input type="number" step="0.1" name="latex2svg_settings[inline_scale]" value="' . esc_attr($options['inline_scale'] ?? 1.2) . '">';
}

function latex2svg_border_render() {
    $options = get_option('latex2svg_settings');
    echo '<input type="number" step="0.1" name="latex2svg_settings[border]" value="' . esc_attr($options['border'] ?? 0.2) . '">';
}

function latex2svg_scale_chemfig_render() {
    $options = get_option('latex2svg_settings');
    echo '<input type="number" step="0.1" name="latex2svg_settings[scale_chemfig]" value="' . esc_attr($options['scale_chemfig'] ?? 1) . '">';
}


function latex2svg_section_cb() {
    echo '<p>Configure API settings and scaling factors for LaTeX rendering.</p>';
}

function latex2svg_options_page() {
    ?>
    <div class="wrap">
        <h2>LaTeX to SVG Settings</h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('latex2svg');
            do_settings_sections('latex2svg');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function latex2svg_process_content($content) {
    // 增强正则匹配：支持 $$...$$ 和 $$$...$$$
    $content = preg_replace_callback('/(\${2,3})(.*?)\1/s', 'latex2svg_replace_inline', $content);
    return $content;
}

function latex2svg_replace_inline($matches) {
    $delimiter = $matches[1]; // 捕获分隔符（$$ 或 $$$）
    $latex_code = trim($matches[2]);
    
    // 根据分隔符长度决定是否添加$符号
    if (strlen($delimiter) === 3) {
        $latex_code = '$' . $latex_code . '$';
    }
    
    return latex2svg_get_svg($latex_code);
}

function latex2svg_get_svg($latex_code) {
    $options = get_option('latex2svg_settings');
    
    // 未配置Token时显示错误
    if (empty($options['api_token'])) {
        return '<span class="latex-error">未配置API密钥</span>';
    }

    // 生成缓存ID和路径
    $cache_id = '_' . md5($latex_code);
    $cache_dir = WP_CONTENT_DIR . '/cache/latex-svg-cache/';
    $filepath = $cache_dir . $cache_id . '.svg';
    $fileurl = content_url('/cache/latex-svg-cache/' . $cache_id . '.svg');

    // 缓存检查
    if (!file_exists($filepath)) {
        $api_params = [
            'token' => $options['api_token'],
            'latex' => rawurlencode($latex_code),
            'superiorcacheid' => $cache_id,
            'border' => $options['border']
        ];
        
        $response = wp_remote_get(add_query_arg($api_params, $options['api_url']));

        // 网络错误处理
        if (is_wp_error($response)) {
            return '<span class="latex-error">网络错误：' . esc_html($response->get_error_message()) . '</span>';
        }

        $status_code = wp_remote_retrieve_response_code($response);
        
        // API错误处理
        if ($status_code !== 200) {
            $error_data = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = $error_data['error'] ?? '未知错误';
            if ($error_msg === "LaTeXCompileFault") {
                $error_detail = $error_data['detail'];
                return '<span class="latex-error">公式错误：' . esc_html($error_msg) . '：'. esc_html($error_detail) .'</span>';
            } else {
                return '<span class="latex-error">公式错误：' . esc_html($error_msg) . '</span>';
            }
        }

        // 保存到缓存
        wp_mkdir_p($cache_dir);
        file_put_contents($filepath, wp_remote_retrieve_body($response));
    }

    // 生成图片标签
    $scale = $options['inline_scale'] ?? 1.2;
    $scale_cf = $options['scale_chemfig']*2.5*$scale ?? 2.5*$scale;
    //调整缩放高度
    $adjustedscale = latex2svg_adjust_scale($scale,$scale_cf ,$latex_code);

    return sprintf(
        '<img src="%s" class="latex-svg" style="height: %sem; vertical-align: middle;" alt="LaTeX公式">',
        esc_url($fileurl),
        floatval($adjustedscale)
    );
}

function latex2svg_adjust_scale($scale,$scale_cf,$latex_code){
    // 根据chemfig来调整
    preg_match_all('/\\\\chemfig\{((?:[^{}]*|\{(?1)\})*)\}/', $latex_code, $matches);
    $count_cf = 0;
    if (count($matches[1]) > 0){
        for ($i=0; $i < count($matches[1]); $i++) {
            $count_cf = max(latex2svg_calculateVerticalDistance($matches[1][$i]), $count_cf);
        }
    }
    // 根据dfrac的数量来调整缩放高度
    $count_df = latex2svg_count_layers($latex_code);
    $adjustedscale = max($scale* (max(1.6 * (1 + $count_df) - 1.2 , 1)), 0.8 * $scale * (1 + $count_cf) + $scale_cf * $count_cf);
    return $adjustedscale;
}

function latex2svg_add_styles() {
    echo '<style>
        .latex-svg {
            margin: 0 0.2em;
            transform: translateY(-0.1em);
        }
        .latex-error {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-family: monospace;
            font-size: 0.9em;
        }
    </style>';
}
function latex2svg_calculateVerticalDistance($chemfigCode) {
    $elements = latex2svg_parseChemfig($chemfigCode);
    $maxY = 0;
    $minY = 0;
    $index = 0;
    $currentY = 0;
    latex2svg_updateMaxMin($currentY, $maxY, $minY);

    latex2svg_processElements($elements, $index, $currentY, $maxY, $minY);

    return $maxY - $minY;
}

function latex2svg_parseChemfig($code) {
    preg_match_all('/(\(|\)|-\d+|-\[\d+.*?\]|\w+)/', $code, $matches);
    return $matches[0];
}

function latex2svg_processElements(&$elements, &$index, &$currentY, &$maxY, &$minY) {
    while ($index < count($elements)) {
        $element = $elements[$index];
        if ($element == '(') {
            $index++;
            $branchY = $currentY;
            latex2svg_processElements($elements, $index, $branchY, $maxY, $minY);
        } elseif ($element == ')') {
            $index++;
            return;
        } elseif (preg_match('/^-/', $element)) {
            $d = latex2svg_getDirectionFromBond($element);
            $dy = latex2svg_calculateDy($d);
            $currentY += $dy;
            $index++;
        } elseif (preg_match('/^\w+$/', $element)) {
            latex2svg_updateMaxMin($currentY, $maxY, $minY);
            $index++;
        } else {
            $index++;
        }
    }
}

function latex2svg_getDirectionFromBond($bondElement) {
    if (preg_match('/^-\[?(\d+)/', $bondElement, $matches)) {
        return (int)$matches[1];
    } elseif (preg_match('/^-(\d+)/', $bondElement, $matches)) {
        return (int)$matches[1];
    } else {
        return 0;
    }
}

function latex2svg_calculateDy($d) {
    switch ($d) {
        case 2:
            return 1;
        case 6:
            return -1;
        case 1:
        case 3:
            return 0.5;
        case 5:
        case 7:
            return -0.5;
        default:
            return 0;
    }
}

function latex2svg_updateMaxMin($y, &$maxY, &$minY) {
    if ($y > $maxY) $maxY = $y;
    if ($y < $minY) $minY = $y;
}

function latex2svg_count_layers($latex) {
    $stack = [];
    $current_depth = 0;
    $max_depth = 0;
    $i = 0;
    $len = strlen($latex);

    while ($i < $len) {
        if (substr($latex, $i, 6) === '\\dfrac') {
            // 遇到\dfrac，层数加一
            $current_depth++;
            if ($current_depth > $max_depth) {
                $max_depth = $current_depth;
            }
            array_push($stack, $current_depth);
            $i += 6;

            // 处理两个参数
            $params_processed = 0;
            while ($params_processed < 2 && $i < $len) {
                if ($latex[$i] === '{') {
                    $params_processed++;
                    $brace_count = 1;
                    $i++;
                    // 处理嵌套的大括号
                    while ($i < $len && $brace_count > 0) {
                        if ($latex[$i] === '\\' && substr($latex, $i, 6) === '\\dfrac') {
                            // 在参数内遇到\dfrac，递归处理
                            $sub_layers = latex2svg_count_layers(substr($latex, $i));
                            $current_sub_depth = $current_depth + $sub_layers;
                            if ($current_sub_depth > $max_depth) {
                                $max_depth = $current_sub_depth;
                            }
                            // 跳过已处理的部分
                            $i += 6;
                        } else {
                            if ($latex[$i] === '{') {
                                $brace_count++;
                            } elseif ($latex[$i] === '}') {
                                $brace_count--;
                            }
                            $i++;
                        }
                    }
                } else {
                    $i++;
                }
            }
            // 恢复之前的层数
            $current_depth = array_pop($stack);
        } else {
            $i++;
        }
    }

    return $max_depth;
}
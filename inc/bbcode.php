<?php
// BBCode-парсер форума.
// Поддерживает: b, i, u, s, quote, code, url, img, color, size, список, спойлер.
declare(strict_types=1);

function bbcode(string $text): string {
    $text = trim($text);
    // Защита от самодельного HTML
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Порядок: сначала спойлер и quote (многострочные), потом остальное.

    // [spoiler] ... [/spoiler]
    $text = preg_replace_callback(
        '/\[spoiler(?:=([^\]]*))?](.*?)\[\/spoiler\]/is',
        function ($m) {
            $title = $m[1] !== '' ? trim($m[1]) : 'Спойлер';
            $inner = $m[2];
            $uniq  = uniqid('sp');
            return '<details class="spoiler"><summary>' . e($title) . '</summary>'
                 . '<div class="spoiler-body">' . $inner . '</div></details>';
        },
        $text
    );

    // [quote]...[/quote] и [quote=Автор]...[/quote]
    $text = preg_replace_callback(
        '/\[quote(?:=([^\]]*))?](.*?)\[\/quote\]/is',
        function ($m) {
            $author = $m[1] !== '' ? '<div class="quote-author">' . e($m[1]) . ' написал(а):</div>' : '';
            return '<blockquote class="bb-quote">' . $author . $m[2] . '</blockquote>';
        },
        $text
    );

    // [code]...[/code]
    $text = preg_replace_callback(
        '/\[code\](.*?)\[\/code\]/is',
        function ($m) {
            return '<pre class="bb-code"><code>' . $m[1] . '</code></pre>';
        },
        $text
    );

    // Список: [list][*]...[/list]
    $text = preg_replace('/\[\*\](?!\[)/', '<li>', $text);
    $text = preg_replace('/\[\/list(?:=([^\]]*))?\]/is', '</ul>', $text);
    $text = preg_replace_callback(
        '/\[list(?:=([^\]]*))?\]/is',
        function ($m) {
            $type = strtolower((string)$m[1]);
            if ($type === 'i' || $type === '1') return '<ol>';
            return '<ul>';
        },
        $text
    );
    $text = preg_replace('/(<\/?(?:ul|ol)>)(\s*)/', '$1', $text);

    // [url]...[/url] и [url=...]
    $text = preg_replace_callback(
        '/\[url=([^\]]+)](.*?)\[\/url\]/is',
        function ($m) {
            $href = e(trim($m[1]));
            $label = $m[2] !== '' ? $m[2] : $href;
            // только http/https
            if (!preg_match('~^https?://~i', $href)) return $label;
            return '<a href="' . $href . '" target="_blank" rel="noopener">' . $label . '</a>';
        },
        $text
    );
    $text = preg_replace_callback(
        '/\[url\](.*?)\[\/url\]/is',
        function ($m) {
            $href = e(trim($m[1]));
            if (!preg_match('~^https?://~i', $href)) return $href;
            return '<a href="' . $href . '" target="_blank" rel="noopener">' . $href . '</a>';
        },
        $text
    );

    // [img]url[/img]
    $text = preg_replace_callback(
        '/\[img\](.*?)\[\/img\]/is',
        function ($m) {
            $src = e(trim($m[1]));
            if (!preg_match('~^https?://~i', $src)) return '';
            return '<img src="' . $src . '" alt="img" loading="lazy">';
        },
        $text
    );

    // Простые теги
    $simple = [
        'b'      => ['<strong>', '</strong>'],
        'i'      => ['<em>', '</em>'],
        'u'      => ['<u>', '</u>'],
        's'      => ['<del>', '</del>'],
    ];
    foreach ($simple as $tag => [$open, $close]) {
        $text = preg_replace('/\[' . $tag . '(?:=[^\]]*)?\]/i', $open, $text);
        $text = preg_replace('/\[\/' . $tag . '\]/i', $close, $text);
    }

    // [color=red]
    $text = preg_replace_callback(
        '/\[color=(#[0-9a-fA-F]{3,6}|[a-zA-Z]+)](.*?)\[\/color\]/is',
        function ($m) {
            $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $m[1])
                ? $m[1]
                : (in_array(strtolower($m[1]), ['red','green','blue','yellow','orange','purple','gray','white','black']) ? strtolower($m[1]) : '');
            if (!$color) return $m[2];
            return '<span style="color:' . e($color) . '">' . $m[2] . '</span>';
        },
        $text
    );

    // [size=...]
    $text = preg_replace_callback(
        '/\[size=(\d{1,2})](.*?)\[\/size\]/is',
        function ($m) {
            $s = (int)$m[1]; if ($s < 10 || $s > 30) return $m[2];
            return '<span style="font-size:' . $s . 'px">' . $m[2] . '</span>';
        },
        $text
    );

    // Убираем незакрытые/лишние теги BBCode, которые не распарсились
    $text = stripUnparsedBbcode($text);

    // Ссылки автоматически
    $text = preg_replace(
        '~(?<!["\'=])\b(https?://[^\s<]+)~i',
        '<a href="$1" target="_blank" rel="noopener">$1</a>',
        $text
    );

    // Перенос строк -> <br>
    $text = nl2br($text, false);
    return $text;
}

function stripUnparsedBbcode(string $text): string {
    return preg_replace('/\[[a-zA-Z0-9=\/ .#%^&*_\-+\(\):"\'@,?]+\]/', '', $text) ?? $text;
}
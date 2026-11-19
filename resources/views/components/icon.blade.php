@props(['name' => 'arrow', 'class' => 'h-6 w-6'])
@php
    $iconNames = [
        'graduation'=>'graduation-cap', 'heart'=>'heart-handshake', 'users'=>'users',
        'shield'=>'shield-check', 'arrow'=>'arrow-right', 'arrow-left'=>'arrow-left',
        'arrow-up-right'=>'arrow-up-right', 'arrow-up'=>'arrow-up', 'arrow-down'=>'arrow-down',
        'chevron'=>'chevron-down', 'check'=>'check', 'clock'=>'clock', 'mail'=>'mail',
        'pin'=>'map-pin', 'grid'=>'layout-dashboard', 'file'=>'file-text', 'menu'=>'menu',
        'lock'=>'lock-keyhole', 'pencil'=>'pencil', 'plus'=>'plus', 'trash'=>'trash-2',
        'eye'=>'eye', 'search'=>'search', 'logout'=>'log-out', 'pages'=>'files',
        'news'=>'newspaper', 'sections'=>'panels-top-left', 'navigation'=>'list-tree',
        'media'=>'images', 'settings'=>'settings-2', 'forms'=>'clipboard-list',
        'category'=>'tags', 'database'=>'database', 'more'=>'ellipsis', 'sparkles'=>'sparkles',
    ];
@endphp
<i data-lucide="{{ $iconNames[$name]??'circle-help' }}" {{ $attributes->merge(['class'=>'app-icon '.$class]) }} aria-hidden="true"></i>

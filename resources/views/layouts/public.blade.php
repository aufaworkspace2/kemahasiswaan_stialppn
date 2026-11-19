<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Portal layanan kemahasiswaan STIA LPPN. Ajukan beasiswa, konseling, kegiatan Ormawa, dan asuransi dengan mudah."><meta name="theme-color" content="#083A28"><title>@yield('title', $settings['site_name']??'Kemahasiswaan STIA LPPN') · STIA LPPN</title><link rel="icon" href="{{ asset('assets/logo.webp') }}">@fonts
@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body><a href="#main" class="skip-link">Lewati ke konten utama</a>
<div class="topline"><div class="container flex items-center justify-between gap-4"><span>Informasi dan layanan mahasiswa STIA LPPN</span><span class="flex items-center gap-2">Portal Kemahasiswaan</span></div></div>
@foreach($displaySections->where('location','top') as $section)@include('portal.section')@endforeach
<header class="site-header"><div class="container header-inner"><a class="brand" href="{{ route('home') }}"><img src="{{ asset('assets/logo.webp') }}" alt="Logo STIA LPPN" width="48" height="52" fetchpriority="high"><span><strong>STIA LPPN</strong><small>{{ $settings['header_label']??'KEMAHASISWAAN' }}</small></span></a>
<button type="button" class="mobile-menu" data-toggle="main-nav" aria-controls="main-nav" aria-expanded="false" aria-label="Buka menu navigasi"><x-icon name="menu"/></button>
@php($activeNav=request()->routeIs('home')?'home':(request()->routeIs('services.*','forms.*','applications.*')?'services':(request()->routeIs('posts.*','pages.*')?'information':'')))
<nav id="main-nav" class="main-nav" aria-label="Navigasi utama" data-page-active="{{ $activeNav }}">
@foreach($menus->whereNull('parent_id') as $menu)
@php($children=$menus->where('parent_id',$menu->id))
@if($children->isNotEmpty())
<details class="nav-dropdown"><summary>{{ $menu->label }} <x-icon name="chevron" class="h-3 w-3"/></summary><div class="dropdown-panel">
@if($menu->url)<a href="{{ $menu->url }}">{{ $menu->label }}</a>@endif
@foreach($children as $child)@if($child->url)<a href="{{ $child->url }}">{{ $child->label }}</a>@endif @endforeach
</div></details>
@elseif($menu->url)<a href="{{ $menu->url }}">{{ $menu->label }}</a>@endif
@endforeach
</nav></div></header>
<main id="main">@yield('content')</main>
@foreach($displaySections->where('location','bottom') as $section)@include('portal.section')@endforeach
<footer class="site-footer">@foreach($displaySections->where('location','footer') as $section)@include('portal.section')@endforeach<div class="container"><div class="footer-top"><div><a class="brand" href="{{ route('home') }}"><span class="logo-white"><img src="{{ asset('assets/logo.webp') }}" width="43" height="48" alt="STIA LPPN" loading="lazy"></span><span><strong>STIA LPPN</strong><small>{{ $settings['header_label']??'KEMAHASISWAAN' }}</small></span></a><p>{{ $settings['footer_text']??'' }}</p></div><div><strong>Layanan mahasiswa</strong><div class="footer-links">@foreach($services as $item)<a class="{{ $activeNav==='services' && isset($service) && $service->id===$item->id?'active':'' }}" href="{{ route('services.show',$item->slug) }}">{{ $item->name }}</a>@endforeach</div></div><div><strong>Kontak layanan</strong><p>{{ $settings['address']??'' }}</p><p>{{ $settings['office_hours']??'' }}</p><a href="{{ route('home') }}#contact">Informasi kontak <x-icon name="arrow-up-right" class="h-4 w-4"/></a></div></div><div class="footer-bottom"><span>© {{ date('Y') }} STIA LPPN. Seluruh hak cipta dilindungi.</span><span>Portal Kemahasiswaan STIA LPPN.</span></div></div></footer>
</body></html>

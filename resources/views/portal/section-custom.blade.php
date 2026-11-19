<section id="{{ $section->anchor??'section-'.$section->id }}" class="section container section-custom-content section-kind-{{ $section->type }} section-image-{{ $config['layout'] }}">
<div class="section-custom-copy">@if($config['eyebrow'])<span class="eyebrow green">{{ $config['eyebrow'] }}</span>@endif<h2>{{ $section->title }}</h2><div class="section-description rich-content">{{ $section->body_html }}</div><div class="rich-content">{{ $section->detail_html }}</div>@include('portal.section-buttons')@if($config['note'])<p class="field-note">{{ $config['note'] }}</p>@endif</div>
@if($section->media || $section->mobileMedia)@include('portal.section-image')@endif
@if($config['items'])@include('portal.section-items')@endif
</section>

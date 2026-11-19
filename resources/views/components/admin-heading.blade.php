@props(['title','actionUrl'=>null,'actionLabel'=>'Tambah'])
<div class="admin-page-title admin-title-simple"><h1>{{ $title }}</h1><div class="admin-title-actions">{{ $slot }}@if($actionUrl)<a class="btn btn-primary" href="{{ $actionUrl }}"><x-icon name="plus" class="h-4 w-4"/>{{ $actionLabel }}</a>@endif</div></div>

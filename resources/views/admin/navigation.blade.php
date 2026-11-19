@extends('layouts.admin')
@section('title','Navigasi')
@section('content')
<x-admin-heading title="Navigasi" :action-url="route('admin.content.create','menus')" action-label="Tambah Menu"/>
<section class="panel admin-list-panel navigation-table-panel" data-navigation-editor data-order-url="{{ route('admin.navigation.order') }}" data-revision="{{ $revision }}" data-token="{{ csrf_token() }}">
<div class="navigation-table-toolbar"><div class="navigation-table-filters"><label class="sr-only" for="navigation-search">Cari menu</label><input id="navigation-search" type="search" placeholder="Cari menu..." data-navigation-search><label class="sr-only" for="navigation-visibility">Visibilitas</label><select id="navigation-visibility" data-navigation-visibility><option value="">Semua visibilitas</option><option value="visible">Tampil</option><option value="hidden">Disembunyikan</option></select></div><button type="button" class="btn btn-primary" data-navigation-save disabled>Simpan Susunan</button></div>
<div class="table-scroll"><table class="navigation-table"><thead><tr><th scope="col"><span class="sr-only">Geser</span></th><th scope="col">Menu</th><th scope="col">Tujuan</th><th scope="col">Induk</th><th scope="col">Status</th><th scope="col">Urutan</th><th scope="col">Aksi</th></tr></thead><tbody class="navigation-tree">
@foreach($menus->whereNull('parent_id') as $menu)
@include('admin.navigation-item',['menu'=>$menu,'isChild'=>false])
@foreach($menus->where('parent_id',$menu->id) as $child)@include('admin.navigation-item',['menu'=>$child,'isChild'=>true])@endforeach
@endforeach
<tr data-navigation-empty @if($menus->isNotEmpty()) hidden @endif><td colspan="7" class="empty">Tidak ada menu yang sesuai.</td></tr>
</tbody></table></div>
<p class="navigation-table-status" data-navigation-status role="status" aria-live="polite">{{ $menus->count() }} menu. Geser untuk urutan, pilih induk untuk submenu.</p>
</section>
@endsection

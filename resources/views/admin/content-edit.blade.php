@extends('layouts.admin')
@section('title',$type==='media'?($record?'Edit Media':'Unggah Media'):(($record?'Edit ':'Tambah ').$label))
@section('content')<x-admin-heading :title="$type==='media'?($record?'Edit Media':'Unggah Media'):(($record?'Edit ':'Tambah ').$label)"/><section class="panel admin-edit-panel">@include('admin.content-form')</section>@endsection

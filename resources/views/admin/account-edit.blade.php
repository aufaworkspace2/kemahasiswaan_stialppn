@extends('layouts.admin')
@section('title',$user?'Edit Akun':'Tambah Akun')
@section('content')<x-admin-heading :title="$user?'Edit Akun':'Tambah Akun'"/><section class="panel admin-edit-panel">@include('admin.account-form')</section>@endsection

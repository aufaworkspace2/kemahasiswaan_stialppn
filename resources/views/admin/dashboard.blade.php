@extends('layouts.admin')
@section('title','Dashboard')
@section('content')<x-admin-heading title="Dashboard"><span class="date-chip">{{ now()->translatedFormat('d F Y') }}</span></x-admin-heading>
<div class="stat-grid">@foreach(['received'=>'Pengajuan baru','under_review'=>'Dalam peninjauan','needs_revision'=>'Perlu perbaikan','approved'=>'Disetujui'] as $status=>$label)<div class="stat-card"><span>{{ $label }}</span><strong>{{ $counts[$status]??0 }}</strong><small><span class="status-dot"></span>{{ \App\Services\PortalWorkflow::STATUSES[$status] }}</small></div>@endforeach</div>
<div class="panel"><div class="panel-heading"><div><h2>Pengajuan terbaru</h2><p>Hanya layanan yang dapat kamu akses.</p></div>@if(auth()->user()->hasPermission('process_applications'))<a class="text-link" href="{{ route('admin.applications.index') }}">Lihat semua <x-icon name="arrow-up-right" class="h-4 w-4"/></a>@endif</div>@include('admin.application-table')</div>@endsection

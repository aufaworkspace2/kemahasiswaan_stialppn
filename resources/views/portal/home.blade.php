@extends('layouts.public')
@section('content')
@foreach($displaySections->where('location','home') as $section)@include('portal.section')@endforeach
@endsection

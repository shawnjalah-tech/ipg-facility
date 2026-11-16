@extends('layouts.app')
@section('title', 'Daily Facility Availability')
@push('styles')<link rel="stylesheet" href="{{ asset('css/facility-calendar.css') }}">@endpush
@section('content')
<x-page-header>
    <x-slot:title>Daily Facility Availability</x-slot:title>
    <x-slot:actions>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.index', ['week' => $day->copy()->startOfWeek()->toDateString(), 'facility' => $facility->id]) }}">&larr; Weekly overview</a>
    </x-slot:actions>
</x-page-header>
<div class="card"><div class="card-body">@include('calendar._day-panel')</div></div>
@endsection

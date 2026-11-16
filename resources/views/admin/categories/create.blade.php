@extends('layouts.app')

@section('title', 'New Category')

@include('partials.admin-ui')

@section('content')

    <a href="{{ route('admin.categories.index') }}" class="admin-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to Categories
    </a>

    <x-page-header>
        <x-slot:title>New Category</x-slot:title>
    </x-page-header>

    <div class="row">
        <div class="col-lg-8 col-xl-7">
            <div class="admin-card">
                <form method="POST" action="{{ route('admin.categories.store') }}" class="admin-form">
                    @csrf
                    @include('admin.categories._form', ['category' => null])

                    <div class="admin-form-footer" style="margin-top: 24px;">
                        <button type="submit" class="btn-icon btn-icon-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Category
                        </button>
                        <a href="{{ route('admin.categories.index') }}" class="btn-icon btn-icon-outline">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

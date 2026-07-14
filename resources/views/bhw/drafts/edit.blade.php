@extends('layouts.portal')

@section('title', 'Edit Field Draft Package - HealthLink')
@section('header', 'Edit Field Draft Package')
@section('subheader', 'Update a pending draft package before the Secretary reviews and codifies it.')

@section('content')
    @include('bhw.drafts._form', [
        'action' => route('bhw.drafts.update', $draft),
        'method' => 'PUT',
    ])
@endsection

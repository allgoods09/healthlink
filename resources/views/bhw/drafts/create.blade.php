@extends('layouts.portal')

@section('title', 'New Field Draft Package - HealthLink')
@section('header', 'New Field Draft Package')
@section('subheader', 'Capture a household survey package for Secretary verification, including residents and environmental health indicators.')

@section('content')
    @include('bhw.drafts._form', [
        'action' => route('bhw.drafts.store'),
    ])
@endsection

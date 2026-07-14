@extends('layouts.portal')

@section('title', 'New Triage Entry - HealthLink')
@section('header', 'New Triage Entry')
@section('subheader', 'Capture pre-consultation vitals and forward them to the PHN/MHO review queue.')

@section('content')
    @include('bhw.triage._form', [
        'action' => route('bhw.triage.store'),
    ])
@endsection

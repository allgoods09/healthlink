@extends('layouts.portal')

@section('title', 'Edit Triage Entry - HealthLink')
@section('header', 'Edit Triage Entry')
@section('subheader', 'Triage entries remain editable only until the PHN/MHO consumes them.')

@section('content')
    @include('bhw.triage._form', [
        'action' => route('bhw.triage.update', $triageRecord),
        'method' => 'PUT',
    ])
@endsection

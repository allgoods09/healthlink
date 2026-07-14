@extends('layouts.portal')

@section('title', 'Edit PHN Encounter - HealthLink')
@section('header', 'Edit Clinical Encounter')
@section('subheader', 'Update consultation findings, follow-up outcomes, or escalation notes for this resident case.')

@section('content')
    @include('phn.encounters._form')
@endsection

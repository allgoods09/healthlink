@extends('layouts.portal')

@section('title', 'New PHN Encounter - HealthLink')
@section('header', 'New Clinical Encounter')
@section('subheader', 'Create a municipal walk-in consultation or consume a pending BHW triage record into the PHN clinical log.')

@section('content')
    @include('phn.encounters._form')
@endsection

@extends('layouts.portal')

@section('title', 'Create MHO Review - HealthLink')
@section('header', 'New Municipal Review')
@section('subheader', 'Finalize the PHN escalation with the MHO’s clinical assessment, prescriptions, referral decision, and follow-up outcome.')

@section('content')
    @include('mho.reviews._form')
@endsection


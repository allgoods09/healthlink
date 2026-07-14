@extends('layouts.portal')

@section('title', 'Edit MHO Review - HealthLink')
@section('header', 'Edit Municipal Review')
@section('subheader', 'Update the MHO’s final assessment, referral instructions, and follow-up outcome for this escalated case.')

@section('content')
    @include('mho.reviews._form')
@endsection

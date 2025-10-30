@extends('layouts.app')

@section('title','Novo evento')

@section('content')
    <form method="POST" action="{{ route('events.store') }}">
        @csrf
        @include('events._form')
    </form>
@endsection

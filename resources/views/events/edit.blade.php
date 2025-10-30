@extends('layouts.app')

@section('title','Editar evento')

@section('content')
    <form method="POST" action="{{ route('events.update', $event) }}">
        @csrf @method('PUT')
        @include('events._form', ['event' => $event])
    </form>
@endsection

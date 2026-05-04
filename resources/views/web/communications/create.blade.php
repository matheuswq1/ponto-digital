@extends('web.layout')
@section('title', 'Novo Comunicado')
@section('page-title', 'Novo Comunicado')

@section('content')
<div class="max-w-2xl">
@if($errors->any())
<div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="post" action="{{ route('painel.communications.store') }}" class="space-y-5">
    @csrf
    @include('web.communications._form')
    <div class="flex gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700">
            Publicar comunicado
        </button>
        <a href="{{ route('painel.communications.index') }}" class="text-sm text-slate-500 py-2.5 px-2">Cancelar</a>
    </div>
</form>
</div>
@endsection

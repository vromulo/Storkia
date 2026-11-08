@extends('layouts.logistics', ['title' => 'Storkia - Seller Messages'])

{{--
    Logistics <-> Seller chat.
    All behaviour lives in resources/js/chat/chat-window.js; the data comes from
    LogisticsMessageController (HandlesUserChat::chatConfig).
--}}
@section('content')
    <x-chat.window :config="$chatConfig" />
@endsection
@extends('layouts.app', ['title' => 'Messages'])

{{--
    Buyer <-> Seller chat.
    All behaviour lives in resources/js/chat/chat-window.js; the data comes from
    BuyerMessageController (HandlesUserChat::chatConfig).
--}}
@section('content')
    <x-chat.window :config="$chatConfig"
                   :framed="false"
                   height="calc(100vh - 135px)"
                   class="border-t border-gray-200" />
@endsection
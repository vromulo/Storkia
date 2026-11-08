@extends('layouts.seller', ['title' => 'Storkia - Messages'])

{{--
    Seller <-> Buyer and Seller <-> Logistics chat.
    All behaviour lives in resources/js/chat/chat-window.js; the data comes from
    SellerMessageController (HandlesUserChat::chatConfig).
--}}
@section('content')
    <x-chat.window :config="$chatConfig" />
@endsection
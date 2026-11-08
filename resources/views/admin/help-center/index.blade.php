@extends('layouts.admin', ['title' => 'Storkia Admin - Help Center'])

{{--
    Help Center inbox: members write in from their own Help Center page and the
    support team answers here. Data comes from Admin\HelpCenterController::index.
--}}
@section('content')
    <x-chat.window :config="$chatConfig" height="calc(100vh - 170px)" />
@endsection
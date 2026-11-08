{{--
    Help Center for Buyer, Seller and Logistics accounts: one running
    conversation with the Storkia support team.

    $layout is chosen by Support\HelpCenterController (layouts.app, layouts.seller
    or layouts.logistics). The buyer layout has no content padding, so the chat
    runs edge to edge there, exactly like the buyer messages page.
--}}
@extends($layout, ['title' => 'Help Center'])

@section('content')
    @if ($layout === 'layouts.app')
        <x-chat.window :config="$chatConfig"
                       :framed="false"
                       height="calc(100vh - 135px)"
                       class="border-t border-gray-200" />
    @else
        <x-chat.window :config="$chatConfig" />
    @endif
@endsection
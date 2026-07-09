@if(!empty($payments) && count($payments) > 0)
<table class="table table-slim mb-0 bg-light-gray" @if(!empty($for_pdf)) style="width: 100%;" @endif>
    <thead>
        <tr>
            <th>@lang('lang_v1.date')</th>
            <th>@lang('purchase.ref_no')</th>
            <th>@lang('lang_v1.payment_method')</th>
            <th class="text-right">@lang('account.debit')</th>
            <th class="text-right">@lang('account.credit')</th>
            <th>@lang('report.others')</th>
        </tr>
    </thead>
    <tbody>
        @foreach($payments as $payment)
            <tr>
                <td>{{ @format_datetime($payment['date']) }}</td>
                <td>{{ $payment['ref_no'] }}</td>
                <td>{{ $payment['payment_method'] }}</td>
                <td class="ws-nowrap text-right">@if($payment['debit'] != '') @format_currency($payment['debit']) @endif</td>
                <td class="ws-nowrap text-right">@if($payment['credit'] != '') @format_currency($payment['credit']) @endif</td>
                <td>{!! $payment['others'] !!}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

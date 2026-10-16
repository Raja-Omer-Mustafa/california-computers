<!-- app css -->
@if(!empty($for_pdf))
	<link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}">
@endif
<div class="col-md-12 col-sm-12 @if(!empty($for_pdf)) width-100 align-right @endif">
    <p class="text-right align-right"><strong>{{$contact->business->name}}</strong>
    	<br>
    	@if(!empty($location))
    		{!! $location->location_address !!}
    	@else
    		{!! $contact->business->business_address !!}
    	@endif
    </p>
</div>
<div class="col-md-6 col-sm-6 col-xs-6 @if(!empty($for_pdf)) width-50 f-left @endif">
	<p class="blue-heading p-4 width-50">@lang('lang_v1.to'):</p>
	<p><strong>{{$contact->name}}</strong><br> {!! $contact->contact_address !!} @if(!empty($contact->email)) <br>@lang('business.email'): {{$contact->email}} @endif
	<br>@lang('contact.mobile'): {{$contact->mobile}}
	@if(!empty($contact->tax_number)) <br>@lang('contact.tax_no'): {{$contact->tax_number}} @endif
	</p>
</div>
<div class="col-md-6 col-sm-6 col-xs-6 text-right align-right @if(!empty($for_pdf)) width-50 f-left @endif">
	<h3 class="mb-0 blue-heading p-4">@lang('lang_v1.account_summary')</h3>
	<small>{{$ledger_details['start_date']}} @lang('lang_v1.to') {{$ledger_details['end_date']}}</small>
	<hr>
	<table class="table table-condensed text-left align-left no-border @if(!empty($for_pdf)) table-pdf @endif">
		<tr>
			<td>@lang('lang_v1.opening_balance')</td>
			<td class="align-right">@format_currency($ledger_details['beginning_balance'])</td>
		</tr>
	@if( $contact->type == 'supplier' || $contact->type == 'both')
		<tr>
			<td>@lang('report.total_purchase')</td>
			<td class="align-right">@format_currency($ledger_details['total_purchase'])</td>
		</tr>
	@endif
	@if( $contact->type == 'customer' || $contact->type == 'both')
		<tr>
			<td>@lang('lang_v1.total_invoice_without_discount')</td>
			<td class="align-right">@format_currency($ledger_details['total_invoice'] + $ledger_details['product_discount'] + $ledger_details['invoice_discount'] - $ledger_details['invoice_discount_return'])</td>
		</tr>
		<tr>
			<td>@lang('lang_v1.total_invoice')</td>
			<td class="align-right">@format_currency($ledger_details['total_invoice'] - $ledger_details['ledger_discount'])</td>
		</tr>
	@endif
	<tr>
		<td>@lang('sale.total_paid')</td>
		<td class="align-right">@format_currency($ledger_details['total_paid'])</td>
	</tr>
	<tr>
		<td>@lang('lang_v1.advance_balance')</td>
		<td class="align-right">@format_currency($contact->balance - $ledger_details['total_reverse_payment'])</td>
	</tr>
	<tr>
		<td><strong>@lang('lang_v1.balance_due')</strong></td>
		<td class="align-right">
			<strong>
				@format_currency($ledger_details['balance_due'] - $ledger_details['ledger_discount'])
			</strong>
		</td>
	</tr>
	{{-- Discounts --}}
	<tr>
		<td colspan="2" style="padding-top: 10px; border-bottom: 1px solid #ddd;"><strong>@lang('lang_v1.discounts')</strong></td>
	</tr>
	<tr>
		<td style="padding-left: 20px;">@lang('lang_v1.ledger_discount')</td>
		<td class="align-right">@format_currency($ledger_details['ledger_discount'])</td>
	</tr>
	<tr>
		<td style="padding-left: 20px;">@lang('lang_v1.invoice_discount')</td>
		<td class="align-right">@format_currency($ledger_details['invoice_discount'])</td>
	</tr>
	<tr>
		<td style="padding-left: 20px;">@lang('lang_v1.invoice_discount_return')</td>
		<td class="align-right">- @format_currency($ledger_details['invoice_discount_return'])</td>
	</tr>
	<tr>
		<td style="padding-left: 20px;">@lang('lang_v1.product_discount')</td>
		<td class="align-right">@format_currency($ledger_details['product_discount'])</td>
	</tr>
	@php
		$total_discount = $ledger_details['ledger_discount'] + $ledger_details['invoice_discount'] - $ledger_details['invoice_discount_return'] + $ledger_details['product_discount'];
	@endphp
	<tr>
		<td style="border-top: 1px solid #ddd;"><strong>@lang('lang_v1.total_discount')</strong></td>
		<td class="align-right" style="border-top: 1px solid #ddd;"><strong>@format_currency($total_discount)</strong></td>
	</tr>
	<tr>
		<td colspan="2" style="padding: 0; height: 10px;"></td>
	</tr>
	</table>
</div>
<div class="col-md-12 col-sm-12 @if(!empty($for_pdf)) width-100 @endif">
	<p class="text-center" style="text-align: center;"><strong>@lang('lang_v1.ledger_table_heading', ['start_date' => $ledger_details['start_date'], 'end_date' => $ledger_details['end_date']])</strong></p>
	@if(empty($for_pdf) && collect($ledger_details['ledger'])->whereIn('transaction_type', ['sell', 'purchase'])->isNotEmpty())
		<div class="text-right no-print" style="margin-bottom: 10px;">
			<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary" id="toggle_all_product_details" data-show-label="@lang('lang_v1.show_all_product_details')" data-hide-label="@lang('lang_v1.hide_all_product_details')"><i class="fas fa-chevron-right"></i> <span>@lang('lang_v1.show_all_product_details')</span></button>
		</div>
	@endif
	<div class="table-responsive">
	<table class="table @if(!empty($for_pdf)) table-pdf td-border @endif" id="ledger_table">
		<thead>
			<tr class="row-border blue-heading">
				<th width="18%" class="text-center">@lang('lang_v1.date')</th>
				<th width="9%" class="text-center">@lang('purchase.ref_no')</th>
				<th width="8%" class="text-center">@lang('lang_v1.type')</th>
				<th width="10%" class="text-center">@lang('sale.location')</th>
				<th width="5%" class="text-center">@lang('sale.payment_status')</th>
				{{--<th width="10%" class="text-center">@lang('sale.total')</th>--}}
				<th width="10%" class="text-center">@lang('account.debit')</th>
				<th width="10%" class="text-center">@lang('account.credit')</th>
				<th width="10%" class="text-center">@lang('lang_v1.balance')</th>
				<th width="5%" class="text-center">@lang('lang_v1.payment_method')</th>
				<th width="15%" class="text-center">@lang('report.others')</th>
			</tr>
		</thead>
		<tbody>
			@foreach($ledger_details['ledger'] as $data)
				@php
					$has_product_details = !empty($data['transaction_type']) && in_array($data['transaction_type'], ['sell', 'purchase']);
				@endphp
				<tr @if(!empty($data['transaction_type']) && in_array($data['transaction_type'], ['sell', 'purchase']))
					class="bg-gray"
					@if(!empty($for_pdf)) style="color: #000;background-color: #d2d6de!important;" @endif
				@elseif(!empty($data['is_payment']))
					class="bg-info-light"
					@if(!empty($for_pdf)) style="background-color: #e8f4fc!important;" @endif
				@endif>
					<td class="row-border">
						@if($has_product_details && empty($for_pdf))
							<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-ghost toggle_product_details no-print" data-target="{{$loop->index}}"><i class="fas fa-chevron-right"></i></button>
						@endif
						{{@format_datetime($data['date'])}}
					</td>
					<td>{{$data['ref_no']}}</td>
					<td>{{$data['type']}}</td>
					<td>{{$data['location']}}</td>
					<td>{{$data['payment_status']}}</td>
					{{--<td class="ws-nowrap align-right">@if($data['total'] !== '') @format_currency($data['total']) @endif</td>--}}
					<td class="ws-nowrap align-right">@if($data['debit'] != '') @format_currency($data['debit']) @endif</td>
					<td class="ws-nowrap align-right">@if($data['credit'] != '') @format_currency($data['credit']) @endif</td>
					<td class="ws-nowrap align-right">{{$data['balance']}}</td>
					<td>{{$data['payment_method']}}</td>
					<td>
						{!! $data['others'] !!}

						@if(!empty($is_admin) && !empty($data['transaction_id']) && $data['transaction_type'] == 'ledger_discount')
							<br>
							<button type="button" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_ledger_discount" data-href="{{action([\App\Http\Controllers\LedgerDiscountController::class, 'destroy'], ['ledger_discount' => $data['transaction_id']])}}"><i class="fas fa-trash"></i></button>
							<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-href="{{action([\App\Http\Controllers\LedgerDiscountController::class, 'edit'], ['ledger_discount' => $data['transaction_id']])}}" data-container="#edit_ledger_discount_modal"><i class="fas fa-edit"></i></button>
						@endif
					</td>
				</tr>
				@if(!empty($data['transaction_type']) && $data['transaction_type'] == 'sell')
					<tr @if(empty($for_pdf)) class="product_details_row hide" data-row="{{$loop->index}}" @endif>
						<td colspan="10" class="bg-light-gray" style="padding: 0 20px 10px;">
							@include('sale_pos.partials.sale_line_details', ['sell' => (object)$data, 'enabled_modules' => [], 'is_warranty_enabled' => false, 'for_ledger' => true])
						</td>
					</tr>
				@endif

				@if(!empty($data['transaction_type']) && $data['transaction_type'] == 'purchase')
					<tr @if(empty($for_pdf)) class="product_details_row hide" data-row="{{$loop->index}}" @endif>
						<td colspan="10" class="bg-light-gray" style="padding: 0 20px 10px;">
							@include('contact.partials.ledger_purchase_lines_details', ['purchase' => (object)$data])
						</td>
					</tr>
				@endif

				@if(!empty($data['payments']))
					<tr>
						<td colspan="10" class="bg-light-gray" style="padding: 0 20px 10px;">
							<strong class="text-muted">@lang('sale.payments')</strong>
							@include('contact.partials.ledger_transaction_payments', ['payments' => $data['payments'], 'for_pdf' => !empty($for_pdf)])
						</td>
					</tr>
				@endif
			@endforeach
		</tbody>
	</table>
	</div>
</div>
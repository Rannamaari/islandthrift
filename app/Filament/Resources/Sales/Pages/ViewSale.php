<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Services\CustomerLedgerService;
use App\Services\SalesService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('updateWebsiteOrder')
                ->label('Update Website Order')
                ->icon('heroicon-o-truck')
                ->visible(fn (Sale $record): bool => $record->sales_channel === 'website')
                ->fillForm(fn (Sale $record): array => [
                    'order_status' => $record->order_status,
                    'payment_status' => $record->payment_status,
                ])
                ->schema([
                    Select::make('order_status')->required()->options([
                        'pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing',
                        'ready' => 'Ready', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
                    ]),
                    Select::make('payment_status')->required()->options([
                        'unpaid' => 'Unpaid', 'pending' => 'Pending', 'paid' => 'Paid', 'refunded' => 'Refunded',
                    ]),
                ])
                ->action(function (Sale $record, array $data, SalesService $sales, CustomerLedgerService $ledger): void {
                    if ($data['order_status'] === 'cancelled' && $record->order_status !== 'cancelled') {
                        if ($record->payment_status === 'paid') {
                            Notification::make()
                                ->title('Refund the payment before cancelling this order')
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->load('items');
                        $sales->returnSale(
                            $record->id,
                            $record->items->mapWithKeys(fn ($item): array => [$item->id => $item->quantity])->all(),
                            [
                                'notes' => 'Website order cancelled',
                                'created_by' => auth()->id(),
                            ],
                        );
                        $data['payment_status'] = 'refunded';
                    }

                    if ($data['payment_status'] === 'paid' && $record->payment_status !== 'paid' && (float) $record->balance_due > 0) {
                        $ledger->recordPayment(
                            $record->company_id,
                            $record->customer_id,
                            $record->balance_due,
                            $record->website_payment_method ?? 'website',
                            [
                                'sale_id' => $record->id,
                                'notes' => 'Website order payment recorded',
                                'created_by' => auth()->id(),
                            ],
                        );
                    }

                    $record->update($data);
                    Notification::make()->title('Website order updated')->success()->send();
                }),
            Action::make('thermalReceipt')
                ->label('Reprint Thermal Receipt')
                ->icon('heroicon-o-printer')
                ->url(fn (Sale $record): string => route('admin.sales.receipt', ['sale' => $record, 'format' => 'thermal']))
                ->openUrlInNewTab(),
            Action::make('a4Invoice')
                ->label('Reprint A4 Tax Invoice')
                ->icon('heroicon-o-document-text')
                ->url(fn (Sale $record): string => route('admin.sales.receipt', ['sale' => $record, 'format' => 'a4']))
                ->openUrlInNewTab(),
        ];
    }
}
